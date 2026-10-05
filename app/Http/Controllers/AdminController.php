<?php

namespace App\Http\Controllers;

use App\Models\CreditEntry;
use App\Models\TeacherGame;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The shop's back office (docs/teacher-games.md, Phase 2).
 *
 * Whatever is done here ends up going the same way a refund pressed in
 * Paddle's dashboard does: this asks Paddle to refund, and Paddle's webhook is
 * what moves the credits. Nothing here writes a refund straight into the
 * ledger, because then the money and the account could disagree.
 *
 * Who may reach it is the `admin` gate (config/site.php), never a column.
 */
class AdminController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q'));

        $teachers = User::query()
            ->when($search !== '', fn ($query) => $query->where('email', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"))
            // Units with no game on them, not the sum of the ledger: the
            // column is headed "Credits" and a ledger sum would be what they
            // bought in total, which reads as a balance and isn't one.
            ->withCount(['creditUnits as credits' => fn ($query) => $query->whereNull('game_id')])
            ->withCount('teacherGames as games')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.index', ['teachers' => $teachers, 'search' => $search]);
    }

    public function teacher(User $user): View
    {
        return view('admin.teacher', [
            'teacher' => $user,
            'entries' => $user->creditEntries()->latest()->get(),
            'games' => $user->teacherGames()->withTrashed()->latest()->get(),
        ]);
    }

    /**
     * Give or take credits by hand: an apology, a test account, a sale that
     * arrived without its webhook.
     *
     * Never used to undo a purchase — that is a refund, which owes the teacher
     * their money back as well.
     */
    public function credits(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'delta' => ['required', 'integer', 'between:-100,100', 'not_in:0'],
            'note' => ['required', 'string', 'max:200'],
        ]);

        $user->grantCredits(
            (int) $data['delta'],
            CreditEntry::ADMIN,
            'admin-'.Str::ulid(),
            $data['note'],
        );

        return back()->with('status', 'Ledger updated.');
    }

    /**
     * Ask Paddle to refund a purchase.
     *
     * Paddle decides — a refund can sit in review — and tells us through the
     * webhook, which is where the credits come off and the games it paid for
     * are locked. So this screen does not pretend it is already done.
     */
    public function refund(Request $request, CreditEntry $entry): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);

        abort_unless($entry->reason === CreditEntry::PURCHASE && $entry->reference !== null, 404);

        $key = (string) config('paddle.api_key');
        if ($key === '') {
            return back()->with('status', 'Paddle is not configured here, so nothing was sent.');
        }

        $response = Http::withToken($key)
            ->acceptJson()
            ->post(rtrim((string) config('paddle.api_url'), '/').'/adjustments', [
                'action' => 'refund',
                'transaction_id' => $entry->reference,
                'type' => 'full',
                'reason' => $data['reason'],
            ]);

        if ($response->failed()) {
            return back()->with('status', 'Paddle refused the refund: '.$response->body());
        }

        return back()->with('status', 'Paddle has the refund. The credits come off when it is approved.');
    }

    public function lock(Request $request, TeacherGame $game): RedirectResponse
    {
        $game->lock((string) $request->input('reason', 'Locked by the shop'));

        return back()->with('status', 'Game locked.');
    }

    public function unlock(TeacherGame $game): RedirectResponse
    {
        $game->unlock();

        return back()->with('status', 'Game unlocked.');
    }
}
