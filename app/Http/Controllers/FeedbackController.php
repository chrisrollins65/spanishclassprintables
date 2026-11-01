<?php

namespace App\Http\Controllers;

use App\Mail\FeedbackReceived;
use App\Models\Feedback;
use App\Models\GameEvent;
use App\Models\Room;
use App\Models\TeacherGame;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * What teachers tell us about a game, from two doors: the game screen, which
 * has no account or CSRF token (`game`), and a signed link from an email or My
 * games (`show`, `store`). Both end in save().
 */
class FeedbackController extends Controller
{
    /** More links than this, and it reads like SEO spam, as on the contact form. */
    private const MAX_LINKS = 2;

    /** A flood on this form must not become a flood in the store's inbox. */
    private const MAX_ALERTS_A_DAY = 20;

    /** The game screen's door. Whatever is wrong with a post, the reply is the same. */
    public function game(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            ...$this->rules(),
            'room_code' => ['nullable', 'string', 'regex:/^[A-Z0-9]{4,8}$/'],
            'own_game' => ['nullable', 'string', 'size:26'],
            'game' => ['nullable', Rule::in(array_keys(TeacherGame::LABELS))],
            'source' => ['required', Rule::in(['game', 'next_visit', 'anytime'])],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        // Honeypot: a field no person can see.
        if ($validator->fails() || filled($request->input('website'))) {
            return response()->noContent();
        }

        $fields = $validator->validated();
        $user = $request->user();
        $ownGame = $fields['own_game'] ?? null;
        $code = $fields['room_code'] ?? null;

        if ($ownGame !== null) {
            if (! $user?->teacherGames()->whereKey($ownGame)->exists()) {
                return response()->noContent();
            }
            $code = null;
        } elseif ($code === null || ! Room::whereKey($code)->exists()) {
            return response()->noContent();
        }

        $this->save($fields, [
            'user_id' => $user?->id,
            'game' => $fields['game'] ?? null,
            'room_code' => $code,
            'teacher_game_id' => $ownGame,
            'source' => $fields['source'],
        ]);

        return response()->noContent();
    }

    /**
     * The page a link lands on, with the face it was pressed on already chosen.
     *
     * Opening it saves nothing by itself, because a mail scanner opens every
     * link in an email: a rating saved here would be the scanner's, always the
     * first face. The page's script saves the rating, and a scanner runs none.
     */
    public function show(Request $request, User $user): View
    {
        $about = GameEvent::where('user_id', $user->id)->find($request->query('about'));
        $rating = (int) $request->query('rating');

        return view('feedback.show', [
            'theme' => $about?->theme(),
            'rating' => array_key_exists($rating, Feedback::RATINGS) ? $rating : null,
            'token' => (string) Str::uuid(),
            'action' => $request->fullUrl(),
            'ratings' => Feedback::RATINGS,
            'issues' => Feedback::ISSUES,
            'highlights' => Feedback::HIGHLIGHTS,
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $fields = $request->validate($this->rules(), [
            'rating.required' => 'Please pick a face first.',
        ]);

        $about = GameEvent::where('user_id', $user->id)->find($request->query('about'));
        $source = $request->query('from') === 'my_games' ? 'my_games' : 'email';

        $this->save($fields, [
            'user_id' => $user->id,
            'game' => $about?->game,
            'room_code' => $about?->room_code,
            'teacher_game_id' => $about?->teacher_game_id,
            'source' => $source,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['saved' => true]);
        }

        return back()->with('status', 'Thank you — that really helps.');
    }

    /** @return array<string, list<mixed>> */
    private function rules(): array
    {
        return [
            'token' => ['required', 'uuid'],
            'rating' => ['required', 'integer', Rule::in(array_keys(Feedback::RATINGS))],
            'issues' => ['nullable', 'array', 'max:'.count(Feedback::ISSUES)],
            'issues.*' => [Rule::in(array_keys(Feedback::ISSUES))],
            'highlights' => ['nullable', 'array', 'max:'.count(Feedback::HIGHLIGHTS)],
            'highlights.*' => [Rule::in(array_keys(Feedback::HIGHLIGHTS))],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * One token, one row, however many posts arrive and in whatever order.
     *
     * An upsert rather than a look-then-write, because the unique index is what
     * makes it hold against both halves of a double-tap arriving at once (see
     * .ai/rules/repeated-presses.md). Only what this post actually says is
     * written over the row, so a slow rating cannot blank a comment that
     * overtook it. Who and where are set on the first post and never again.
     *
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $context
     */
    private function save(array $fields, array $context): void
    {
        $comment = trim((string) ($fields['comment'] ?? '')) ?: null;
        $answers = array_filter([
            'rating' => (int) $fields['rating'],
            'issues' => empty($fields['issues']) ? null : json_encode(array_values($fields['issues'])),
            'highlights' => empty($fields['highlights']) ? null : json_encode(array_values($fields['highlights'])),
            'comment' => $comment,
            'email' => $fields['email'] ?? null,
            'spam_reason' => $comment !== null && preg_match_all('~https?://|www\.~i', $comment) > self::MAX_LINKS
                ? 'too many links'
                : null,
        ], fn (mixed $answer): bool => $answer !== null);

        Feedback::upsert(
            [['token' => $fields['token']] + $context + $answers + ['issues' => null, 'highlights' => null, 'comment' => null, 'email' => null, 'spam_reason' => null]],
            ['token'],
            array_keys($answers),
        );

        $this->alert($fields['token']);
    }

    /**
     * Tell the store about the answers that want one back: a bad game someone
     * took the time to describe, or anyone who left an address.
     *
     * Saved first, mailed second, as on the contact form: a mail failure never
     * costs the teacher their answer or shows them an error for it.
     */
    private function alert(string $token): void
    {
        $inbox = (string) config('site.contact_inbox');
        $feedback = Feedback::with('user')->where('token', $token)->first();

        if ($inbox === '' || $feedback === null || $feedback->spam_reason !== null || $feedback->comment === null) {
            return;
        }

        if ($feedback->rating !== 1 && $feedback->email === null) {
            return;
        }

        if (! Cache::add("feedback-alert:{$token}", true, now()->addDay())
            || ! RateLimiter::attempt('feedback-alerts', self::MAX_ALERTS_A_DAY, fn (): bool => true, 86400)) {
            return;
        }

        try {
            Mail::to($inbox)->send(new FeedbackReceived($feedback));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
