<?php

namespace App\Models;

use Database\Factories\TeacherGameFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * One teacher's private game: a copy of a room they bought, or (later) one
 * they created. See docs/teacher-games.md.
 *
 * Its payload is a room payload whose `code` is this game's id, never the
 * room's. The games save their progress in the browser under that code, so a
 * copy sharing the room's code would pick up — and overwrite — the saved state
 * of the original game on the same computer.
 */
class TeacherGame extends Model
{
    /** @use HasFactory<TeacherGameFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /** What each game in a payload is called on the teacher's pages. */
    public const LABELS = [
        'bingo' => 'Bingo',
        'jeopardy' => 'Team quiz',
    ];

    protected $fillable = ['theme', 'games', 'kind', 'source_code', 'payload', 'written_by'];

    protected function casts(): array
    {
        return ['locked_at' => 'datetime', 'published_at' => 'datetime'];
    }

    /** @return HasOne<CreditUnit, $this> */
    public function creditUnit(): HasOne
    {
        return $this->hasOne(CreditUnit::class, 'game_id');
    }

    /**
     * A draft is not playable, not printable, and deletable to get the credit
     * back; publishing is the moment a teacher gets what they paid for.
     */
    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /** A game a refund has taken back: kept, but not playable. */
    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /*
     * forceFill, not update: locking is ours to do, never a teacher's, so the
     * columns stay out of $fillable — and a non-fillable column handed to
     * update() is dropped in silence, which is a lock that never happened.
     */
    public function lock(string $reason): void
    {
        $this->forceFill(['locked_at' => now(), 'locked_reason' => $reason])->save();
    }

    public function unlock(): void
    {
        $this->forceFill(['locked_at' => null, 'locked_reason' => null])->save();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The payload as an array, or an empty one for a game with nothing in it yet. */
    public function data(): array
    {
        return json_decode((string) $this->payload, true) ?: [];
    }

    public function setData(array $payload): void
    {
        // The copy's code is this game's id and nothing else decides it.
        $payload['code'] = $this->id;

        $this->update(['payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    /**
     * A new game with nothing written in it yet.
     *
     * Empty rather than absent: the editor reads a payload of this shape, so a
     * teacher writing their own game by hand meets the same screen as one who
     * asked AI, with blank rows instead of written ones.
     */
    /**
     * @param  string  $type  The deck type id. Empty means vocabulary, as it does
     *                        everywhere else, and is what every game made before
     *                        deck types existed is.
     * @param  list<string>  $clueTypes  The deck's clue types, from the builder's
     *                                   registry (Builder::types()). The default
     *                                   is vocabulary's, used only when rebuilding
     *                                   a payload for a game that predates types.
     */
    /**
     * @param  array<string, mixed>  $flags  What the deck tells the ROOM, as
     *      opposed to what it tells the writer: whether more than one answer is
     *      right, and which language the clues are in. The builder stamps these
     *      on when it publishes; a game made here never passes through that, so
     *      they have to be written in when it is created or the room plays
     *      without them.
     */
    public static function blank(
        string $kind,
        string $theme,
        string $type = '',
        array $clueTypes = ['en', 'sentence', 'definition'],
        array $flags = [],
    ): array {
        $game = $kind === 'bingo'
            ? ['title' => $theme, 'cardSets' => [['size' => 4, 'count' => 48], ['size' => 3, 'count' => 48]]]
            : ['title' => $theme, 'categories' => []];

        $blank = [
            'theme' => $theme,
            'items' => [],
            /* Both kinds, not bingo alone.
             *
             * These are a property of the DECK — the ways a word of this type can
             * be clued — not of the game it feeds, and the quiz needs them too
             * now: the teacher's word editor decides from them whether a word has
             * a formula box. Left empty, a grammar quiz was generated with its
             * formulas and then offered no way to edit one. */
            'clueTypes' => $clueTypes,
            'games' => [$kind => $game],
        ];

        // Written only when it is not vocabulary, so nothing that exists churns.
        if ($type !== '' && $type !== 'vocabulario') {
            $blank['type'] = $type;
        }

        foreach (['answerIsOpen'] as $flag) {
            if (! empty($flags[$flag])) {
                $blank[$flag] = true;
            }
        }

        /* Whether a leading article is its own field or part of the answer.
         *
         * Stored only when it is FALSE, the opposite way round from the flags
         * above, because splitting the article is what every deck did before
         * sentences existed: absent has to keep meaning "split", or every game
         * already saved changes behaviour. tidyItems is the only thing that
         * reads it. */
        if (($flags['stripsArticles'] ?? true) === false) {
            $blank['stripsArticles'] = false;
        }

        return $blank;
    }

    /**
     * Put what a model wrote into this game.
     *
     * Merged into the payload rather than replacing it, so a teacher who has
     * already typed a theme or edited a word does not lose it to a later
     * generation.
     */
    public function applyWriting(array $written, string $writtenBy): void
    {
        $payload = $this->data() ?: self::blank((string) $this->kind, (string) $this->theme);

        // A sentence deck's articles belong to the sentence; see tidyItems.
        $payload['items'] = self::tidyItems(
            $written['items'] ?? [],
            ($payload['stripsArticles'] ?? true) !== false,
        );

        /* The study sheet, for a deck whose bank is its answers.
         *
         * A sentence pack cannot show its own bank to the class — that is the
         * answer list — so the model writes a short glossary beside the
         * sentences and it rides back in the same reply. Dropped here, the pack
         * had nothing for the class to revise from and the printed page fell
         * back to listing the answers.
         */
        if (isset($written['reference']['rows']) && is_array($written['reference']['rows'])) {
            $payload['reference'] = $written['reference'];
        }

        if ($this->kind === 'jeopardy') {
            $payload['games']['jeopardy']['categories'] = $written['categories'] ?? [];
            $payload['games']['jeopardy']['final'] = $written['final'] ?? null;
        }

        $this->forceFill(['written_by' => $writtenBy])->save();
        $this->setData($payload);
    }

    /**
     * Why this game cannot be played yet, in a teacher's words.
     *
     * Structural only. Whether a clue is any good is the teacher's judgement,
     * and the checks panel already advises on it; this is about a game that
     * would fall over in front of a class — a bingo card with too few words,
     * a square with no answer.
     *
     * @return list<string>
     */
    public function reasonsItCannotBePlayed(): array
    {
        $payload = $this->data();
        $items = $payload['items'] ?? [];
        $problems = [];

        if ($this->kind === 'bingo') {
            $biggest = max(array_map(
                fn (array $set): int => (int) ($set['size'] ?? 4),
                $payload['games']['bingo']['cardSets'] ?? [['size' => 4]],
            ));
            $needed = $biggest * $biggest;

            if (count($items) < $needed) {
                $problems[] = "A {$biggest}×{$biggest} card needs ".$needed.' words; this game has '.count($items).'.';
            }

            foreach ($items as $index => $item) {
                if (trim((string) ($item['face'] ?? '')) === '') {
                    $problems[] = 'Word '.($index + 1).' has no Spanish word.';
                }
            }
        }

        if ($this->kind === 'jeopardy') {
            $categories = $payload['games']['jeopardy']['categories'] ?? [];

            if ($categories === []) {
                $problems[] = 'The board has no categories yet.';
            }

            foreach ($categories as $category) {
                $name = trim((string) ($category['name'] ?? '')) ?: 'A category';

                foreach ($category['clues'] ?? [] as $clue) {
                    if (trim((string) ($clue['prompt'] ?? '')) === '' || trim((string) ($clue['answer'] ?? '')) === '') {
                        $problems[] = "{$name} has a square with no clue or no answer.";
                        break;
                    }
                }
            }
        }

        return array_values(array_unique($problems));
    }

    /**
     * Make it playable.
     *
     * The bingo cards are built here rather than while drafting: they are
     * seeded from the game's id, so they are the same cards every time, and a
     * teacher who redrew their words ten times would otherwise have paid for
     * ten sets nobody printed.
     */
    public function publish(): void
    {
        $this->dealCardsNow();

        $this->forceFill(['published_at' => now()])->save();
    }

    /**
     * Deal this game's cards into its payload.
     *
     * Also called after an AI edit to a game that is already published: the
     * cards are dealt FROM the words, so changing a word leaves every printed
     * set calling a word that is on no card. An edit clears them, and a
     * published game has to get them straight back — otherwise the next press
     * of "Print the cards" renders a set with nothing on it.
     *
     * A draft is left without them on purpose, because publishing deals them
     * anyway and a teacher redrawing their words ten times should not pay for
     * ten sets nobody printed.
     */
    public function dealCardsNow(): void
    {
        if ($this->kind !== 'bingo') {
            return;
        }

        $payload = $this->data();

        if (($payload['items'] ?? []) === []) {
            return;
        }

        $payload['games']['bingo']['cards'] = $this->dealCards($payload);
        $this->setData($payload);
    }

    /**
     * Deal this game's cards, using the builder's own card code.
     *
     * Through Node rather than ported to PHP: the algorithm lives in
     * public/game/shared/bingoCards.js, the editor deals with it in the
     * browser, and two implementations would sooner or later deal two
     * different packs from the same words.
     */
    private function dealCards(array $payload): array
    {
        $faces = array_values(array_filter(array_map(
            fn (array $item): string => trim(implode(' ', array_filter([$item['article'] ?? '', $item['face'] ?? '']))),
            $payload['items'] ?? [],
        )));

        $request = json_encode([
            'gameId' => $this->id,
            'faces' => $faces,
            'sets' => $payload['games']['bingo']['cardSets'] ?? [['size' => 4, 'count' => 48], ['size' => 3, 'count' => 48]],
        ], JSON_UNESCAPED_UNICODE);

        $node = Process::timeout(60)
            ->input($request)
            ->run([config('cards.node', 'node'), resource_path('scripts/build-cards.cjs')]);

        if ($node->failed()) {
            throw new RuntimeException('Could not deal the cards: '.substr($node->errorOutput(), 0, 200));
        }

        return json_decode($node->output(), true) ?: [];
    }

    /**
     * Whether it is worth offering this game's bingo cards as a download.
     *
     * A game made here has no cards anywhere else, so they are always worth
     * offering: they are half of what the credit bought.
     *
     * A game CLAIMED from TpT is the other way round. The teacher already has
     * a printed set in their download, and it is right until they change a
     * word — so offering a download to someone who has changed nothing is
     * offering them the set they already have, and invites a pointless render
     * and a pointless trip to the printer.
     *
     * Compared against the room it was claimed from rather than tracked with a
     * flag, so it stays true through an edit that puts a word back: a teacher
     * who changes "vaca" to "toro" and then back again is, correctly, told
     * nothing needs printing.
     */
    public function cardsAreWorthPrinting(): bool
    {
        if ($this->kind !== 'bingo' && ! str_contains((string) $this->games, 'bingo')) {
            return false;
        }

        if ($this->source_code === null) {
            return true;
        }

        $room = Room::find($this->source_code);
        if ($room === null) {
            // The room it came from is gone, so nothing can be compared to it.
            // Offer the cards: a download nobody needed beats a teacher who
            // cannot reach the one they do.
            return true;
        }

        return self::faceList($this->data()) !== self::faceList(json_decode((string) $room->payload, true) ?: []);
    }

    /**
     * Whether to offer this game's team answer sheet.
     *
     * Only for a game made here, and — unlike the bingo cards — NOT for a
     * claimed pack even once its board has changed. The two differ because of
     * what is printed on them: a card carries the words, so changing a word
     * makes the printed set wrong and a new one worth having. The answer sheet
     * is blank. Its headings can go stale and it still works, because what a
     * team writes in the boxes is the same either way.
     *
     * So a teacher who bought the pack on TpT already has the sheet they need,
     * and the site has nothing better to give them.
     */
    public function answerSheetIsAvailable(): bool
    {
        if ($this->source_code !== null) {
            return false;
        }

        if ($this->kind !== 'jeopardy' && ! str_contains((string) $this->games, 'jeopardy')) {
            return false;
        }

        return ($this->data()['games']['jeopardy']['categories'] ?? []) !== [];
    }

    /** The words a payload prints on a card, in order, for comparing two. */
    private static function faceList(array $payload): string
    {
        return implode('|', array_map(
            fn (array $item): string => trim(($item['article'] ?? '').' '.($item['face'] ?? '')),
            array_filter($payload['items'] ?? [], 'is_array'),
        ));
    }

    /**
     * The article is its own field, and a model sometimes forgets.
     *
     * "la camisa" in `face` reads as "la la camisa" on screen once the article
     * is put back, so it is taken apart here. The prompt says so too; this is
     * the half that does not depend on the model having listened.
     *
     * NOT for every deck. Where an entry is a whole sentence the leading
     * article belongs to it: "La enfermera trabaja en el hospital" came back
     * as article "la" and a face beginning "enfermera", which is not the
     * sentence the board answers with and matches nothing in the bank. The
     * deck says which it is (`stripsArticles`), the same flag the board's own
     * check reads — see `bare()` in jeopardyBoard.js.
     */
    private static function tidyItems(array $items, bool $splitArticles = true): array
    {
        return array_values(array_map(function (array $item) use ($splitArticles): array {
            $face = trim((string) ($item['face'] ?? ''));

            if ($splitArticles && preg_match('/^(el|la|los|las)\s+(.+)$/iu', $face, $found)) {
                // `??`, not `?:` — a deck whose items have no article field at
                // all reached here and raised "Undefined array key".
                $item['article'] = ($item['article'] ?? '') ?: strtolower($found[1]);
                $face = $found[2];
            }

            $item['face'] = $face;

            return $item;
        }, array_filter($items, 'is_array')));
    }

    /**
     * Build an unsaved copy of a room for a teacher.
     */
    public static function copyOf(Room $room): self
    {
        $game = new self;
        $game->id = $game->newUniqueId();

        $payload = json_decode($room->payload, true);
        $payload['code'] = $game->id;

        // Published from the start: it was paid for on TpT, it costs no credit
        // here, and a teacher who claims a game expects to play it now.
        $game->published_at = now();

        $game->fill([
            'theme' => (string) ($payload['theme'] ?? $room->theme ?? ''),
            'games' => implode(',', array_keys($payload['games'] ?? [])),
            'source_code' => $room->code,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return $game;
    }

    /**
     * The games in this payload, named for the teacher.
     *
     * @return list<string>
     */
    public function gameLabels(): array
    {
        return array_values(array_map(
            fn (string $key): string => self::LABELS[$key] ?? ucfirst($key),
            array_filter(explode(',', $this->games)),
        ));
    }

    /**
     * Those names as one phrase. A pack sold on TpT holds one game, but a room
     * may hold both, and "Bingo · Team quiz" reads like two products.
     */
    public function gamesLabel(): string
    {
        $labels = $this->gameLabels();
        if (count($labels) < 2) {
            return $labels[0] ?? '';
        }

        $last = array_pop($labels);

        return implode(', ', $labels).' and '.strtolower($last);
    }
}
