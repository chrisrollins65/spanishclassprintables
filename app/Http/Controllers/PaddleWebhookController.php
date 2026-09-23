<?php

namespace App\Http\Controllers;

use App\Models\CreditEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * What Paddle tells us (docs/teacher-games.md, Phase 2).
 *
 * Paddle is the seller; this endpoint is the only way a purchase or a refund
 * reaches the site, whoever started it. A refund pressed in Paddle's dashboard
 * and one started from our own admin screens both arrive here, so a teacher's
 * account ends up in the same state either way.
 *
 * Nothing here trusts the body until the signature is checked, and every
 * handler is written to run twice: Paddle retries anything it does not hear
 * back from, and the ledger's unique index is what settles it.
 */
class PaddleWebhookController extends Controller
{
    /*
     * How stale a delivery may be.
     *
     * Paddle's own SDKs allow five seconds. This is looser on purpose: a clock
     * a minute out on either side would otherwise reject every webhook we are
     * ever sent, and the thing a tight window defends against — a captured body
     * replayed — is already harmless here, because the ledger credits a
     * transaction id once however many times it arrives.
     */
    private const MAX_AGE_SECONDS = 60;

    public function __invoke(Request $request): Response
    {
        $secret = (string) config('paddle.webhook_secret');
        abort_if($secret === '', 503, 'Paddle is not configured.');

        if (! $this->signatureIsValid($request, $secret)) {
            return response('Bad signature', 403);
        }

        $event = (string) $request->input('event_type');
        $data = (array) $request->input('data', []);

        match ($event) {
            'transaction.completed' => $this->paid($data),
            'adjustment.created', 'adjustment.updated' => $this->adjusted($data),
            default => null,
        };

        // Always 200 once the signature is good: anything else and Paddle
        // retries for days over an event we have decided not to act on.
        return response('ok');
    }

    /**
     * Paddle signs the raw body with a timestamp, as `ts=…;h1=…`.
     *
     * HMAC-SHA256 over "<ts>:<raw body>", compared with hash_equals. The raw
     * body, never a re-encoded one: any reordering or re-spacing of the JSON
     * changes the bytes that were signed.
     *
     * A header may carry MORE than one h1 — that is how Paddle rotates a
     * secret, signing one delivery with both the old and the new one — so
     * every h1 is checked and any match will do. Reading just one would make
     * every rotation an outage.
     */
    private function signatureIsValid(Request $request, string $secret): bool
    {
        $ts = '';
        $signatures = [];

        foreach (explode(';', (string) $request->header('Paddle-Signature', '')) as $piece) {
            [$key, $value] = array_pad(explode('=', $piece, 2), 2, '');
            $key = trim($key);
            $value = trim($value);

            if ($key === 'ts') {
                $ts = $value;
            } elseif ($key === 'h1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($ts === '' || $signatures === []) {
            return false;
        }

        if (abs(time() - (int) $ts) > self::MAX_AGE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $ts.':'.$request->getContent(), $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A completed sale: give the teacher what the prices in it are worth.
     *
     * The buyer is named by `custom_data.user_id`, which the checkout sets from
     * the logged-in teacher. A transaction without one is a sale we cannot
     * attribute — better logged and left alone than credited to a guess.
     */
    private function paid(array $data): void
    {
        $user = $this->buyer($data);
        $transaction = (string) ($data['id'] ?? '');
        if ($user === null || $transaction === '') {
            Log::warning('Paddle transaction with no teacher attached', ['transaction' => $transaction]);

            return;
        }

        $credits = $this->creditsIn($data);
        if ($credits < 1) {
            Log::warning('Paddle transaction bought nothing we sell', ['transaction' => $transaction]);

            return;
        }

        $user->grantCredits($credits, CreditEntry::PURCHASE, $transaction, 'Paddle transaction');
    }

    /**
     * A refund (or a chargeback), which Paddle sends as an adjustment.
     *
     * Only once it is actually approved: a refund Paddle is still reviewing has
     * taken nothing from anybody, and locking a teacher's games in the middle
     * of a lesson over a decision that may not be made is the wrong way round.
     */
    private function adjusted(array $data): void
    {
        $action = (string) ($data['action'] ?? '');
        $status = (string) ($data['status'] ?? '');
        if (! in_array($action, ['refund', 'chargeback'], true) || $status !== 'approved') {
            return;
        }

        $transaction = (string) ($data['transaction_id'] ?? '');
        $adjustment = (string) ($data['id'] ?? '');
        if ($transaction === '' || $adjustment === '') {
            return;
        }

        $purchase = CreditEntry::where('reason', CreditEntry::PURCHASE)
            ->where('reference', $transaction)
            ->first();

        if ($purchase === null) {
            Log::warning('Paddle refunded a transaction we never credited', ['transaction' => $transaction]);

            return;
        }

        $purchase->user->revokeCredits($purchase, $adjustment, "Refund of {$transaction}");
    }

    private function buyer(array $data): ?User
    {
        $id = $data['custom_data']['user_id'] ?? null;

        return $id === null ? null : User::find($id);
    }

    /** What the prices in a transaction are worth here (config/paddle.php). */
    private function creditsIn(array $data): int
    {
        $prices = (array) config('paddle.credits');
        $credits = 0;

        foreach ((array) ($data['items'] ?? []) as $item) {
            $priceId = (string) ($item['price']['id'] ?? $item['price_id'] ?? '');
            $quantity = (int) ($item['quantity'] ?? 1);
            $credits += ($prices[$priceId] ?? 0) * max(1, $quantity);
        }

        return $credits;
    }
}
