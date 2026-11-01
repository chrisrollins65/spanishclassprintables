<?php

namespace App\Support;

use App\Models\CreditEntry;
use App\Models\Feedback;
use App\Models\GameEvent;
use App\Models\Room;
use App\Models\TeacherGame;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The figures behind the admin's Insights page, for one period.
 *
 * Grouped in PHP where the question lives inside the JSON column: it reads the
 * same on MySQL and on the tests' SQLite, and the rows are few. Each read is
 * capped, so a busy year makes a figure approximate rather than the page slow.
 */
class Insights
{
    /** The periods on offer, in days. The first is the default. */
    public const PERIODS = [30, 90, 365];

    /** A clue seen less often than this says nothing about the clue. */
    private const MIN_CLUE_PLAYS = 3;

    private const ROW_CAP = 20000;

    public function __construct(private CarbonInterface $since) {}

    /** @return Builder<GameEvent> */
    private function events(string ...$names): Builder
    {
        return GameEvent::query()->whereIn('event', $names)->where('created_at', '>=', $this->since);
    }

    /**
     * The headline numbers.
     *
     * @return array{opened: int, started: int, finished: int, finish_rate: ?int, median_minutes: ?int}
     */
    public function totals(): array
    {
        $started = $this->events('game_started')->count();
        $finished = $this->events('game_finished')->count();

        return [
            'opened' => $this->events('room_opened')->count(),
            'started' => $started,
            'finished' => $finished,
            'finish_rate' => $this->percent($finished, $started),
            'median_minutes' => $this->medianMinutes(),
        ];
    }

    /**
     * A median, not an average: a game left on a shared screen until Monday
     * counts as one long game, and an average would believe it.
     */
    private function medianMinutes(): ?int
    {
        $durations = $this->events('game_finished')->limit(self::ROW_CAP)->pluck('data')
            ->map(fn (?array $data): ?int => isset($data['duration']) ? (int) $data['duration'] : null)
            ->filter()
            ->sort()
            ->values();

        return $durations->isEmpty() ? null : (int) round($durations[(int) floor($durations->count() / 2)] / 60);
    }

    /**
     * From account to repeat use, each step a smaller group of teachers.
     *
     * Accounts created in the period, followed through whatever they did next,
     * so every row is a share of the same people.
     *
     * @return list<array{label: string, count: int, percent: ?int}>
     */
    public function funnel(): array
    {
        $teachers = User::query()->where('created_at', '>=', $this->since)->select('id');
        $count = fn (Builder $query): int => $query->whereIn('user_id', (clone $teachers))->distinct()->count('user_id');

        $signedUp = (clone $teachers)->count();
        $steps = [
            'Signed up' => $signedUp,
            'Added or made a game' => $count(TeacherGame::withTrashed()),
            'Published one' => $count(TeacherGame::withTrashed()->whereNotNull('published_at')),
            'Played one' => $count(GameEvent::query()->where('event', 'game_started')),
            'Played twice or more' => GameEvent::query()->where('event', 'game_started')
                ->whereIn('user_id', (clone $teachers))
                ->selectRaw('user_id')->groupBy('user_id')->havingRaw('count(*) > 1')->get()->count(),
            'Bought credits' => $count(CreditEntry::query()->where('reason', CreditEntry::PURCHASE)),
        ];

        return array_map(
            fn (string $label, int $number): array => ['label' => $label, 'count' => $number, 'percent' => $this->percent($number, $signedUp)],
            array_keys($steps),
            $steps,
        );
    }

    /**
     * Started and finished, by game.
     *
     * @return list<array{game: string, started: int, finished: int, finish_rate: ?int}>
     */
    public function byGame(): array
    {
        $rows = [];
        foreach (TeacherGame::LABELS as $key => $label) {
            $started = $this->events('game_started')->where('game', $key)->count();
            $finished = $this->events('game_finished')->where('game', $key)->count();
            $rows[] = ['game' => $label, 'started' => $started, 'finished' => $finished, 'finish_rate' => $this->percent($finished, $started)];
        }

        return $rows;
    }

    /**
     * The rooms and games played most, with how they were rated.
     *
     * @return Collection<int, array{theme: string, code: ?string, started: int, finished: int, ratings: int, average: ?float}>
     */
    public function topGames(int $limit = 10): Collection
    {
        $key = fn (object $row): string => $row->room_code ?? $row->teacher_game_id ?? '';

        $plays = $this->events('game_started', 'game_finished')
            ->limit(self::ROW_CAP)
            ->get(['event', 'room_code', 'teacher_game_id'])
            ->groupBy($key)
            ->forget('')
            ->sortByDesc(fn (Collection $rows): int => $rows->where('event', 'game_started')->count())
            ->take($limit);

        $ratings = Feedback::query()->where('created_at', '>=', $this->since)->whereNull('spam_reason')
            ->get(['room_code', 'teacher_game_id', 'rating'])
            ->groupBy($key);

        $rooms = Room::query()->whereIn('code', $plays->keys())->pluck('theme', 'code');
        $games = TeacherGame::withTrashed()->whereIn('id', $plays->keys())->pluck('theme', 'id');

        return $plays->map(fn (Collection $rows, string $id): array => [
            'theme' => $rooms[$id] ?? $games[$id] ?? $id,
            'code' => $rooms->has($id) ? $id : null,
            'started' => $rows->where('event', 'game_started')->count(),
            'finished' => $rows->where('event', 'game_finished')->count(),
            'ratings' => $ratings->get($id)?->count() ?? 0,
            'average' => $ratings->has($id) ? round($ratings[$id]->avg('rating'), 1) : null,
        ])->values();
    }

    /**
     * The signs of a teacher having trouble.
     *
     * @return array{abandoned: int, no_voice: int, how_to: int, wrong_codes: Collection<string, int>, errors: Collection<string, int>}
     */
    public function struggles(): array
    {
        $opened = $this->events('room_opened')->whereNotNull('play_id')->distinct()->pluck('play_id');
        $playing = $this->events('game_started', 'game_resumed')->whereNotNull('play_id')->distinct()->pluck('play_id');

        return [
            'abandoned' => $opened->diff($playing)->count(),
            'no_voice' => $this->events('no_spanish_voice')->count(),
            'how_to' => $this->events('how_to_opened')->count(),
            'wrong_codes' => $this->tally('code_not_found', 'code'),
            'errors' => $this->tally('js_error', 'message'),
        ];
    }

    /** @return Collection<string, int> */
    private function tally(string $event, string $key, int $limit = 10): Collection
    {
        return $this->events($event)->limit(self::ROW_CAP)->pluck('data')
            ->map(fn (?array $data): string => (string) ($data[$key] ?? ''))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take($limit);
    }

    /**
     * The clues classes got right least often: a bad clue, or a hard one.
     *
     * @return Collection<int, array{category: string, clue: string, plays: int, right: int}>
     */
    public function hardestClues(int $limit = 10): Collection
    {
        return $this->events('clue_resolved')->limit(self::ROW_CAP)->pluck('data')
            ->filter(fn (?array $data): bool => isset($data['clue']))
            ->groupBy(fn (array $data): string => ($data['category'] ?? '').'|'.$data['clue'])
            ->filter(fn (Collection $plays): bool => $plays->count() >= self::MIN_CLUE_PLAYS)
            ->map(fn (Collection $plays): array => [
                'category' => (string) ($plays->first()['category'] ?? ''),
                'clue' => (string) $plays->first()['clue'],
                'plays' => $plays->count(),
                'right' => (int) $this->percent($plays->where('answered', true)->count(), $plays->count()),
            ])
            ->sortBy('right')
            ->take($limit)
            ->values();
    }

    /**
     * How AI's drafts fared once a teacher had them.
     *
     * @return array{published: int, kept: ?int, requests: Collection<int, string>}
     */
    public function aiWriting(): array
    {
        $published = $this->events('ai_draft_published')->limit(self::ROW_CAP)->pluck('data');
        $written = $published->sum(fn (?array $data): int => (int) ($data['written'] ?? 0));
        $kept = $published->sum(fn (?array $data): int => (int) ($data['kept'] ?? 0));

        return [
            'published' => $published->count(),
            'kept' => $this->percent($kept, $written),
            'requests' => $this->events('ai_edit_requested')->latest('id')->limit(10)->pluck('data')
                ->map(fn (?array $data): string => (string) ($data['request'] ?? ''))
                ->filter()
                ->values(),
        ];
    }

    /**
     * How many of each rating, best first.
     *
     * @return array<int, int>
     */
    public function ratings(): array
    {
        $counts = Feedback::query()->where('created_at', '>=', $this->since)->whereNull('spam_reason')
            ->selectRaw('rating, count(*) as total')->groupBy('rating')->pluck('total', 'rating');

        return array_map(
            fn (int $rating): int => (int) ($counts[$rating] ?? 0),
            array_combine(array_keys(Feedback::RATINGS), array_keys(Feedback::RATINGS)),
        );
    }

    /** @return Collection<int, Feedback> */
    public function latestFeedback(int $limit = 30): Collection
    {
        return Feedback::query()->with('user')
            ->where('created_at', '>=', $this->since)
            ->whereNull('spam_reason')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    private function percent(int $part, int $whole): ?int
    {
        return $whole === 0 ? null : (int) round(100 * $part / $whole);
    }
}
