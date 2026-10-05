<?php

namespace App\Http\Controllers;

use App\Ai\Builder;
use App\Ai\Writer;
use App\Jobs\EditGame;
use App\Jobs\RenderAnswerSheet;
use App\Jobs\RenderBingoCards;
use App\Jobs\WriteGame;
use App\Models\CreditUnit;
use App\Models\Room;
use App\Models\TeacherGame;
use App\Models\User;
use App\Support\PrintablePdf;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * A teacher's own games: the list, claiming a bought game by its room code,
 * and playing one. See docs/teacher-games.md, Phase 1.
 *
 * Every game is looked up through the logged-in teacher's own relation, never
 * by id alone, so another teacher's game is simply not found: a 404, which
 * also says nothing about whether that id exists.
 */
class TeacherGameController extends Controller
{
    /** The same ceiling a published room has (RoomController). */
    private const MAX_PAYLOAD_BYTES = 2_000_000;

    /**
     * How long two identical "start this game" presses count as one press.
     *
     * Long enough to cover a slow post and an impatient second tap, short
     * enough that a teacher who meant to make a second game on the same topic
     * is not made to wait. See justStarted().
     */
    private const REPEAT_PRESS_SECONDS = 30;

    public function index(Request $request): View
    {
        // Everything the list shows except the payload: a bingo payload is
        // ~60KB and nothing here reads it. Anything a row displays has to be
        // named, or it silently reads as empty — which is how a locked game
        // first appeared on this page as a playable one.
        $games = $request->user()->teacherGames()
            ->select(['id', 'user_id', 'theme', 'games', 'kind', 'source_code', 'locked_at', 'locked_reason', 'published_at', 'created_at', 'updated_at'])
            ->latest('updated_at')
            ->get();

        return view('games.index', [
            'games' => $games,
            'credits' => $request->user()->availableCredits(),
            'held' => $request->user()->heldCredits(),
        ]);
    }

    /**
     * Copy a bought game into the teacher's account.
     *
     * The room code is the only proof of purchase there is: TpT gives sellers
     * no way to check a buyer. That is acceptable because anyone holding the
     * code can already play the game and see every answer; the copy adds only
     * the right to change it. Claims per code are counted by source_code.
     */
    public function claim(Request $request): RedirectResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $request->validate(
            ['code' => ['required', 'regex:/^[A-Z0-9]{4,8}$/']],
            ['code.regex' => 'A game code is 4 to 8 letters and numbers.'],
        );
        $code = $request->input('code');

        // The demo games are free to everyone, so a copy of one would be a free
        // game once games are sold here (site.unclaimable_codes).
        if (in_array($code, (array) config('site.unclaimable_codes'), true)) {
            return back()->withInput()->withErrors([
                'code' => "That's the demo game, which can't be customized. Use the code from a game you bought.",
            ]);
        }

        $room = Room::find($code);
        if ($room === null) {
            return back()->withInput()->withErrors([
                'code' => "We couldn't find a game with that code. It's printed on the Play Online page of your download.",
            ]);
        }

        // Claiming the same game twice would leave two copies to tell apart.
        $existing = $request->user()->teacherGames()->where('source_code', $code)->first();
        if ($existing !== null) {
            return redirect()->route('my-games')
                ->with('status', "\"{$existing->theme}\" is already in your games.");
        }

        $game = TeacherGame::copyOf($room);
        $request->user()->teacherGames()->save($game);

        return redirect()->route('my-games')
            ->with('status', "\"{$game->theme}\" is now in your games.");
    }

    /** Choose what to make: which game, what it is about, and who writes it. */
    public function create(Request $request): View
    {
        return view('games.create', [
            'credits' => $request->user()->availableCredits(),
            'held' => $request->user()->heldCredits(),
            'kinds' => TeacherGame::LABELS,
            'aiAvailable' => Writer::withinDailyLimit() && config('ai.keys.gemini') !== '',
        ]);
    }

    /**
     * Read what kind of game the description asks for, and show it.
     *
     * Nothing is created and no credit is taken here. The kind of bank was
     * always worked out before anything was written; this stops in between and
     * says so, because it is the one decision a teacher cannot discover any
     * other way. "The subjunctive" is as true of a board of verb forms as of a
     * board of whole sentences to translate, and a teacher who did not know
     * the second exists used to find out after the credit was spent.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(TeacherGame::LABELS))],
            'theme' => ['required', 'string', 'max:120'],
            /* What the lesson is for, in the teacher's words. The kind of bank
             * is read from it rather than asked for — see Builder::classify. */
            'describe' => ['nullable', 'string', 'max:500'],
            'written_by' => ['required', Rule::in(['me', 'ai'])],
        ]);

        $theme = trim($data['theme']);
        $chosen = app(Builder::class)->classify(
            $theme,
            (string) ($data['describe'] ?? ''),
            app(Writer::class),
            $data['kind'],
        );

        /* Skipped unless there is really something to choose.
         *
         * The screen exists for one question: whether the class recalls items
         * or produces whole sentences. That is a choice about the ACTIVITY,
         * and it is the only one a teacher cannot discover any other way. The
         * difference between a bank of words, of verb forms and of little
         * words is not that question — it is our own distinction about how a
         * pack is BUILT, which this form deliberately stopped asking about
         * (see the note on the description box in create.blade.php). A bingo
         * can only be played one way, so a bingo would be shown three cards it
         * has no basis to choose between on its way to the thing it asked for.
         *
         * Read off the decks rather than hardcoded, so a quiz-only deck added
         * later turns the screen on by itself: `answerIsOpen` is what makes an
         * answer something the teacher judges rather than a bank face to match,
         * which is the production/recognition split. Fewer than two decks also
         * means no choice — the registry comes over the node bridge, which can
         * be down, and a confirm screen with an empty picker is a dead end
         * whose one button posts a `type` that is required and absent.
         */
        $decks = $this->decksFor($data['kind']);
        $activities = array_unique(array_map(
            fn (array $row): string => empty($row['answerIsOpen']) ? 'recall' : 'produce',
            $decks,
        ));

        if (count($decks) < 2 || count($activities) < 2) {
            $data['type'] = $chosen['type'];
            $data['classified'] = $chosen['type'];
            $data['why'] = $chosen['why'];

            return $this->makeGame($request->user(), $data, $theme);
        }

        return redirect()->route('my-games.confirm')->with('pendingGame', [
            'kind' => $data['kind'],
            'theme' => $theme,
            'describe' => trim((string) ($data['describe'] ?? '')),
            'written_by' => $data['written_by'],
            'type' => $chosen['type'],
            'why' => $chosen['why'],
        ]);
    }

    /**
     * What we are about to build, with the chance to change it.
     *
     * Reached only from store(), whose classification it carries, so this
     * screen costs no second model call. A refresh keeps it (reflash); a
     * teacher who arrives with nothing pending goes back to the form.
     */
    public function confirm(Request $request): View|RedirectResponse
    {
        $pending = $request->session()->get('pendingGame');
        if (! is_array($pending)) {
            return redirect()->route('my-games.create');
        }
        $request->session()->reflash();

        return view('games.confirm', [
            'pending' => $pending,
            'types' => $this->decksFor($pending['kind']),
        ]);
    }

    /**
     * The decks a game of this kind can be played as.
     *
     * A sentence deck is quiz-only, and a bingo built on one would have no way
     * to call a card. The classifier is filtered the same way, but a teacher
     * overruling it is not — see `games` on the deck type.
     *
     * Note the rename: a game's kind here is "jeopardy" and a deck type calls
     * the same thing "quiz", which is the translation `classifyPrompt` does on
     * the way into the model. Without it nothing matches a quiz at all and
     * every quiz quietly falls back to vocabulary.
     *
     * @return list<array<string, mixed>>
     */
    private function decksFor(string $kind): array
    {
        $played = $kind === 'jeopardy' ? 'quiz' : $kind;

        return array_values(array_filter(
            app(Builder::class)->types(),
            fn (array $row): bool => in_array($played, $row['games'] ?? ['bingo', 'quiz'], true),
        ));
    }

    /**
     * Start a game, which is where a credit goes.
     *
     * The credit is taken before anything is written, so every model call has
     * one behind it — and handed straight back if there is nothing to write
     * with, so a teacher never pays for a button that did not work.
     */
    public function begin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(TeacherGame::LABELS))],
            'theme' => ['required', 'string', 'max:120'],
            'describe' => ['nullable', 'string', 'max:500'],
            'written_by' => ['required', Rule::in(['me', 'ai'])],
            // The answer to the confirm screen: the classifier's, or the
            // teacher's correction of it. `classified` is what the classifier
            // said, so `why` can be dropped when they are no longer the same.
            'type' => ['required', 'string', 'max:40'],
            'classified' => ['nullable', 'string', 'max:40'],
            'why' => ['nullable', 'string', 'max:300'],
        ]);

        return $this->makeGame($request->user(), $data, trim($data['theme']));
    }

    /**
     * One press at a time, per teacher and per press.
     *
     * The repeat check inside reads before it writes, and a double-tap on a
     * phone arrives as two requests at once: both read nothing, both create,
     * and the teacher has paid twice. Serialising them is what makes the check
     * mean anything — without it the guard passes a sequential test and fails
     * the case it exists for.
     *
     * @param  array<string, mixed>  $data
     */
    private function makeGame(User $teacher, array $data, string $theme): RedirectResponse
    {
        $key = 'start-game:'.$teacher->id.':'.$data['kind'].':'.md5($theme.'|'.($data['describe'] ?? ''));

        try {
            return Cache::lock($key, 30)->block(
                10,
                fn (): RedirectResponse => $this->startGame($teacher, $data, $theme),
            );
        } catch (LockTimeoutException) {
            // Ten seconds behind another press of the same button. Something
            // is wrong upstream, but nothing here has been charged.
            return redirect()->route('my-games')
                ->with('status', 'That took too long to start. Nothing has been charged — please try again.');
        }
    }

    /** The making of the game itself, with the press held (see begin()). */
    private function startGame(User $teacher, array $data, string $theme): RedirectResponse
    {
        if ($again = $this->justStarted($teacher, $data['kind'], $theme)) {
            return redirect()->route('my-games.edit', $again);
        }

        /* What kind of bank this lesson needs — read from the description by
         * store() and confirmed by the teacher on the way here, so by this
         * point it is an answer rather than a guess. It decides which fields a
         * word has in the editor and not only how it is generated, which is
         * why a teacher writing it themselves is asked the same question.
         *
         * Checked against the registry rather than trusted: the id arrives in
         * a form, and a deck this kind cannot play would make a game that
         * cannot be called. Anything unknown lands on vocabulary, as it does
         * everywhere else.
         */
        $type = null;
        foreach ($this->decksFor($data['kind']) as $row) {
            if (($row['id'] ?? '') === $data['type']) {
                $type = $row;
                break;
            }
        }

        $game = new TeacherGame;
        $game->id = $game->newUniqueId();
        $game->user_id = $teacher->id;
        $game->fill([
            'theme' => $theme,
            'kind' => $data['kind'],
            'games' => $data['kind'],
            'payload' => json_encode(
                TeacherGame::blank(
                    $data['kind'],
                    $theme,
                    $type['id'] ?? '',
                    $type['clueTypes'] ?? [],
                    [
                        'answerIsOpen' => $type['answerIsOpen'] ?? false,
                        // So applyWriting knows not to split an article off a
                        // sentence — see TeacherGame::tidyItems.
                        'stripsArticles' => $type['stripsArticles'] ?? true,
                    ],
                )
                    + [
                        'code' => $game->id,
                        // Kept so the generation prompts can use it and the edit
                        // screen can say why this kind was chosen.
                        'describe' => trim((string) ($data['describe'] ?? '')) ?: null,
                        /* Why this kind was chosen, for the edit screen to
                         * repeat — but only while it is still the classifier's
                         * own reading. A teacher who changed it on the confirm
                         * screen has overruled that, and the reason given for a
                         * deck nobody chose would be a lie on the page. */
                        'typeWhy' => ($data['type'] === ($data['classified'] ?? ''))
                            ? (trim((string) ($data['why'] ?? '')) ?: null)
                            : null,
                    ],
                JSON_UNESCAPED_UNICODE,
            ),
        ]);
        $game->save();

        $unit = CreditUnit::claimFor($teacher, $game);

        if ($unit === null) {
            // Nothing was taken and nothing was written; say so where credits
            // are bought rather than leaving an unpaid draft behind.
            $game->forceDelete();

            return redirect()->route('credits')
                ->with('status', 'You need a credit to make a game. One credit makes one game.');
        }

        if ($data['written_by'] === 'ai') {
            WriteGame::markWorking($game->id);
            WriteGame::dispatch($game->id);
        }

        return redirect()->route('my-games.edit', $game);
    }

    /**
     * The same game this teacher started a moment ago, if they did.
     *
     * One press must not cost two credits. The button disables itself
     * (public/site/once.js), but that is courtesy, not a guarantee: it is gone
     * on a refresh onto the POST, on the back button, in a second tab, and in
     * the gap before the script has loaded. A double-tap on a phone cost a
     * teacher a credit and left them a duplicate draft.
     *
     * Matched on what the press said rather than on a token, because the
     * failure being guarded is the SAME press arriving twice — same teacher,
     * same kind, same topic, seconds apart. A token would also have to survive
     * a back button, and a teacher who legitimately wants a second go meets an
     * expired form instead of a game.
     *
     * Deliberately narrow: only a draft, only an untouched one, and only
     * within the window. A teacher who really wants two games on one topic
     * waits half a minute, or edits the first — and either way still has both
     * their credits.
     */
    private function justStarted(User $teacher, string $kind, string $theme): ?TeacherGame
    {
        return $teacher->teacherGames()
            ->whereNull('published_at')
            ->where('kind', $kind)
            ->where('theme', $theme)
            ->where('created_at', '>=', now()->subSeconds(self::REPEAT_PRESS_SECONDS))
            ->latest('created_at')
            ->first();
    }

    /**
     * Ask AI to write this game — the button on a draft that was started by
     * hand, or a second try after a failure.
     */
    public function write(Request $request, string $game): JsonResponse
    {
        $game = $this->draft($request, $game);

        if (! Writer::withinDailyLimit()) {
            return response()->json(['status' => 'failed', 'message' => 'Writing is paused for today. Nothing has been charged — please try again tomorrow.'], 503);
        }

        $unit = $game->creditUnit;
        if ($unit !== null && $unit->aiCentsLeft() <= 0) {
            return response()->json(['status' => 'failed', 'message' => 'This credit has used all of its AI. You can still write and edit the game yourself.'], 422);
        }

        // Pressing twice is one generation: the second press finds it working
        // and waits with the first.
        if (WriteGame::statusOf($game->id)['status'] === 'working') {
            return response()->json(['status' => 'working']);
        }

        WriteGame::markWorking($game->id);
        WriteGame::dispatch($game->id);

        return response()->json(['status' => 'working'], 202);
    }

    /** What the page watches while a model writes. */
    /**
     * Ask AI for one change to a game that already exists.
     *
     * The repeated half of writing a game, and what the per-credit AI budget
     * is for: a teacher who does not like three of the words says so, rather
     * than generating the whole thing again and losing the twenty-seven they
     * were happy with.
     *
     * Allowed on a published game as well as a draft. A teacher who finds a
     * bad clue the night before a lesson should be able to fix it; the cards
     * are dealt from the words, so a bingo edit clears them and publishing
     * deals them again.
     */
    public function ask(Request $request, string $game): JsonResponse
    {
        $game = $this->playable($request, $game);

        $data = $request->validate([
            'request' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'request.required' => 'Tell us what to change.',
            'request.min' => 'Tell us a little more about what to change.',
        ]);

        if (! Writer::withinDailyLimit()) {
            return response()->json(['status' => 'failed', 'message' => 'Writing is paused for today. Nothing has been charged — please try again tomorrow.'], 503);
        }

        $unit = $game->creditUnit;
        if ($unit !== null && $unit->aiCentsLeft() <= 0) {
            return response()->json(['status' => 'failed', 'message' => 'This credit has used all of its AI. You can still change the game yourself.'], 422);
        }

        if ($game->data() === []) {
            return response()->json(['status' => 'failed', 'message' => 'There is nothing to change yet — write the game first.'], 422);
        }

        // One change at a time. Two at once would each be written against the
        // game as it was before the other, and the second to finish would
        // quietly throw the first away.
        if (EditGame::statusOf($game->id)['status'] === 'working') {
            return response()->json(['status' => 'working']);
        }

        EditGame::markWorking($game->id);
        EditGame::dispatch($game->id, trim($data['request']));

        return response()->json(['status' => 'working'], 202);
    }

    /** Where an Ask AI change is up to, and what it moved. */
    public function askingStatus(Request $request, string $game): JsonResponse
    {
        $game = $this->owned($request, $game);
        $status = EditGame::statusOf($game->id);
        $unit = $game->creditUnit;

        return response()->json([
            'status' => $status['status'],
            'message' => $status['message'] ?? null,
            // Which entries the model rewrote, so the editor can point at
            // them: an edit nobody can find is an edit nobody reviews.
            'changed' => $status['changed'] ?? [],
            'renamed' => $status['renamed'] ?? [],
            'removed' => $status['removed'] ?? 0,
            'final_changed' => $status['final_changed'] ?? false,
            // An edit is what makes a claimed pack's printed cards out of
            // date, so the page learns it without a reload.
            'cards_worth_printing' => $game->cardsAreWorthPrinting(),
            'ai_percent_used' => $this->meterFor($game),
        ]);
    }

    public function writingStatus(Request $request, string $game): JsonResponse
    {
        $game = $this->owned($request, $game);
        $status = WriteGame::statusOf($game->id);
        $unit = $game->creditUnit;

        return response()->json([
            'status' => $status['status'],
            'message' => $status['message'] ?? null,
            // Only once most of it is gone: a teacher who never approaches the
            // limit never learns there is one.
            'ai_percent_used' => $this->meterFor($game),
        ]);
    }

    /**
     * Publish: the moment a draft becomes the thing the teacher paid for.
     *
     * Structural checks only — enough words for a card, every square answered.
     * How well it is written stays the teacher's business, but a game that
     * cannot be played would be discovered in front of a class.
     */
    public function publish(Request $request, string $game): RedirectResponse
    {
        $game = $this->draft($request, $game);
        $problems = $game->reasonsItCannotBePlayed();

        if ($problems !== []) {
            return back()->withErrors(['publish' => $problems]);
        }

        $game->publish();

        return redirect()->route('my-games')
            ->with('status', "\"{$game->theme}\" is ready to play.");
    }

    /** A game of this teacher's that is still a draft. */
    private function draft(Request $request, string $id): TeacherGame
    {
        $game = $this->playable($request, $id);

        abort_if($game->isPublished(), 403, 'This game has already been published.');

        return $game;
    }

    public function edit(Request $request, string $game): View
    {
        $game = $this->playable($request, $game);

        return view('games.edit', [
            'game' => $game,
            // So the editor can name the kind of bank this game uses and offer
            // the others, without keeping its own copy of the list.
            'deckTypes' => app(Builder::class)->types(),
            // On the page itself, not only in the reply to an ask: a teacher
            // who comes back the next day to a credit with no AI left should
            // meet a closed box that says so, rather than an open one that
            // takes their request and refuses it.
            'aiPercentUsed' => $this->meterFor($game),
            // A claimed game's printed cards are right until a word changes,
            // so the download only appears once it has something new to give.
            'cardsWorthPrinting' => $game->cardsAreWorthPrinting(),
            // The quiz's equivalent, and a stricter rule: never for a claimed
            // pack, which came with one (answerSheetIsAvailable).
            'answerSheetAvailable' => $game->answerSheetIsAvailable(),
        ]);
    }

    /**
     * How much of this credit's AI is gone, or null while it does not matter.
     *
     * Below the threshold this returns nothing at all, so a teacher who never
     * approaches the limit never learns there is one.
     */
    private function meterFor(TeacherGame $game): ?int
    {
        $unit = $game->creditUnit;

        return $unit && $unit->aiPercentUsed() >= (int) config('ai.meter_from_percent')
            ? $unit->aiPercentUsed()
            : null;
    }

    /**
     * Save an edited game.
     *
     * The editor in the browser judges the writing — it runs the builder's own
     * validators (public/game/shared) and shows what they say — while this
     * checks only the shape and the size. A second copy of every writing rule
     * in PHP would be a copy to keep in step with the builder for no gain: a
     * payload that breaks a writing rule breaks nobody's game but the owner's.
     */
    public function update(Request $request, string $game): JsonResponse
    {
        $game = $this->playable($request, $game);

        $request->validate([
            'payload' => ['required', 'array'],
            'payload.theme' => ['required', 'string', 'max:120'],
            'payload.games' => ['required', 'array', 'min:1'],
            'payload.items' => ['sometimes', 'array', 'max:500'],
        ]);

        /* The whole payload, not the validated subset.
         *
         * A payload carries more than the editor touches — `clueTypes`, which
         * decides how bingo calls a word, `reference`, `grades` — and taking
         * only the validated keys quietly dropped every one of them on the
         * first save. Validation here says what must be present, never what may
         * be kept.
         */
        $payload = (array) $request->input('payload');

        $unknown = array_diff(array_keys($payload['games']), array_keys(TeacherGame::LABELS));
        if ($unknown !== []) {
            return response()->json(['message' => 'That is not a game this site can play.'], 422);
        }

        // The copy's code is this game's id and nothing else decides it: the
        // browser could otherwise point this game — and the teacher's saved
        // progress in it — at somebody else's room.
        $payload['code'] = $game->id;

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || strlen($json) > self::MAX_PAYLOAD_BYTES) {
            return response()->json(['message' => 'That game is too big to save.'], 422);
        }

        $game->update([
            'theme' => $payload['theme'],
            'games' => implode(',', array_keys($payload['games'])),
            'payload' => $json,
        ]);

        return response()->json(['saved_at' => $game->updated_at->toIso8601String()]);
    }

    public function play(Request $request, string $game): Response
    {
        $game = $this->published($request, $game);

        /* Whether this game was BOUGHT, which decides one thing on the screen
         * at the end: the ask to review it on TpT. A game made here with a
         * credit was never sold on TpT, so asking its author to review their
         * purchase of it is the same mistake the demo room already guards
         * against. A claimed pack is a real purchase and keeps the ask.
         */
        $shell = RoomController::shell(route('my-games.payload', $game), [
            'data-made-here' => $game->source_code === null ? '1' : '',
        ]);

        return response($shell)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * The game's data, for the shell and for the editor.
     *
     * Not cached anywhere, unlike a room's: a teacher who edits a clue and
     * presses play expects the clue they just typed.
     *
     * Owned rather than published, because the editor reads this too — and a
     * draft is what an editor is usually open on. Publishing gates the PAGES
     * it unlocks (play, the cards); it has no business gating a teacher's own
     * data. Gating it here left every draft with an editor that could not load
     * the game it was editing.
     */
    public function payload(Request $request, string $game): Response
    {
        $game = $this->playable($request, $game);

        return response($game->payload)
            ->header('Content-Type', 'application/json; charset=UTF-8')
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * Ask for a set of bingo cards as a PDF.
     *
     * The file is named after what the cards were when it was made, so asking
     * twice for an unedited game is a download rather than a second render,
     * and an edit makes a new name rather than serving yesterday's words. The
     * render itself is queued: it is a whole browser, and one worker runs.
     */
    public function cards(Request $request, string $game): JsonResponse
    {
        $game = $this->published($request, $game);
        $size = (int) $request->input('size');
        $stamp = $this->cardsStamp($game, $size);

        if ($stamp === null) {
            return response()->json(['message' => 'This game has no cards that size.'], 422);
        }

        if (Storage::disk('local')->exists(RenderBingoCards::pathFor($game->id, $size, $stamp))) {
            return response()->json(['status' => 'ready', 'url' => route('my-games.cards.download', [$game, $size])]);
        }

        RenderBingoCards::dispatch($game->id, $size, $stamp);

        return response()->json(['status' => 'working'], 202);
    }

    /** Whether the cards asked for have finished rendering. */
    public function cardsStatus(Request $request, string $game, int $size): JsonResponse
    {
        $game = $this->published($request, $game);
        $stamp = $this->cardsStamp($game, $size);

        $ready = $stamp !== null && Storage::disk('local')->exists(RenderBingoCards::pathFor($game->id, $size, $stamp));

        return response()->json([
            'status' => $ready ? 'ready' : 'working',
            'url' => $ready ? route('my-games.cards.download', [$game, $size]) : null,
        ]);
    }

    /**
     * The team answer sheet: the quiz's equivalent of the bingo cards.
     *
     * Only for a game made here — a claimed pack came with one, and this one
     * is blank, so there is nothing better to give (answerSheetIsAvailable).
     */
    public function answerSheet(Request $request, string $game): JsonResponse
    {
        $game = $this->published($request, $game);
        $stamp = $this->answerSheetStamp($game);

        if ($stamp === null) {
            return response()->json(['message' => 'This game has no board to make a sheet from.'], 422);
        }

        if (Storage::disk('local')->exists(RenderAnswerSheet::pathFor($game->id, $stamp))) {
            return response()->json(['status' => 'ready', 'url' => route('my-games.answer-sheet.download', $game)]);
        }

        RenderAnswerSheet::dispatch($game->id, $stamp);

        return response()->json(['status' => 'working'], 202);
    }

    public function answerSheetStatus(Request $request, string $game): JsonResponse
    {
        $game = $this->published($request, $game);
        $stamp = $this->answerSheetStamp($game);
        $ready = $stamp !== null && Storage::disk('local')->exists(RenderAnswerSheet::pathFor($game->id, $stamp));

        return response()->json([
            'status' => $ready ? 'ready' : 'working',
            'url' => $ready ? route('my-games.answer-sheet.download', $game) : null,
        ]);
    }

    public function answerSheetDownload(Request $request, string $game): Response
    {
        $game = $this->published($request, $game);
        $stamp = $this->answerSheetStamp($game);
        abort_if($stamp === null, 404);

        $path = RenderAnswerSheet::pathFor($game->id, $stamp);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, Str::slug($game->theme ?: 'quiz').'-answer-sheet.pdf');
    }

    /**
     * What the sheet was made from, so an edited board makes a new file rather
     * than serving yesterday's.
     *
     * Only the headings, the money and whether there is a final, because that
     * is all the sheet prints. Rewriting a clue leaves every team's page
     * identical, so it must not cost a render.
     */
    private function answerSheetStamp(TeacherGame $game): ?string
    {
        if (! $game->answerSheetIsAvailable()) {
            return null;
        }

        $board = $game->data()['games']['jeopardy'] ?? [];

        $shape = array_map(fn (array $category): array => [
            $category['name'] ?? '',
            array_map(fn (array $clue): mixed => $clue['value'] ?? null, array_filter($category['clues'] ?? [], 'is_array')),
        ], array_filter($board['categories'] ?? [], 'is_array'));

        return substr(sha1((string) json_encode([
            $game->theme,
            $shape,
            ! empty($board['final']['prompt']),
            PrintablePdf::templateStamp('games.answer-sheet'),
        ])), 0, 16);
    }

    public function cardsDownload(Request $request, string $game, int $size): Response
    {
        $game = $this->published($request, $game);
        $stamp = $this->cardsStamp($game, $size);
        abort_if($stamp === null, 404);

        $path = RenderBingoCards::pathFor($game->id, $size, $stamp);
        abort_unless(Storage::disk('local')->exists($path), 404);

        $name = Str::slug($game->theme ?: 'bingo')."-cards-{$size}x{$size}.pdf";

        return Storage::disk('local')->download($path, $name);
    }

    /**
     * A fingerprint of the cards of one size: what they are, not when they
     * were made. Two teachers' games, or one game before and after an edit,
     * can never share a file.
     */
    private function cardsStamp(TeacherGame $game, int $size): ?string
    {
        $payload = json_decode($game->payload, true) ?: [];
        $cards = array_values(array_filter(
            $payload['games']['bingo']['cards'] ?? [],
            fn (array $card): bool => (int) ($card['size'] ?? 0) === $size,
        ));

        if ($cards === []) {
            return null;
        }

        // The template too: a change to how a card is drawn must reach a
        // teacher who already downloaded one. See PrintablePdf::templateStamp.
        return substr(sha1((string) json_encode([
            $game->theme, $cards, PrintablePdf::templateStamp('games.cards'),
        ])), 0, 16);
    }

    public function destroy(Request $request, string $game): RedirectResponse
    {
        $game = $this->owned($request, $game);
        $wasDraft = ! $game->isPublished();

        // A draft hands its credit back; a published game keeps it spent,
        // because the teacher has had the thing they bought.
        if ($wasDraft) {
            $game->creditUnit?->release();
        }

        $game->delete();

        return redirect()->route('my-games')->with('status', $wasDraft
            ? "\"{$game->theme}\" was deleted and your credit is free again."
            : "\"{$game->theme}\" was removed from your games.");
    }

    private function owned(Request $request, string $id): TeacherGame
    {
        return $request->user()->teacherGames()->findOrFail($id);
    }

    /**
     * The game, and only if it still belongs to the teacher in the sense that
     * matters: a refunded purchase locks what it paid for.
     *
     * 403 rather than 404 — the game is theirs and they can see it on the
     * list, so pretending it does not exist would be a lie they can check.
     */
    private function playable(Request $request, string $id): TeacherGame
    {
        $game = $this->owned($request, $id);

        abort_if($game->isLocked(), 403, 'This game is locked because its purchase was refunded.');

        return $game;
    }

    /**
     * A game that has been published: playing it, its data and its printables
     * are what publishing unlocks. Editing works on a draft too.
     */
    private function published(Request $request, string $id): TeacherGame
    {
        $game = $this->playable($request, $id);

        abort_if(! $game->isPublished(), 403, 'This game is still a draft. Publish it to play or print it.');

        return $game;
    }
}
