<?php

namespace App\Console\Commands;

use App\Models\CreditEntry;
use App\Models\CreditUnit;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Reads Paddle and puts right anything the webhook missed.
 *
 * A webhook is a push, and a push can be missed: a machine asleep, a deploy
 * mid-delivery, a tunnel that was not running. Paddle retries for about three
 * days and then stops, so the site needs a way to ask rather than only being
 * told. This is that way, and it is the same ledger either way — every row is
 * keyed by the Paddle id that caused it, so asking twice changes nothing.
 *
 * Run by the scheduler every hour, and by hand after anything goes wrong:
 *
 *   php artisan paddle:sync                  # the last week
 *   php artisan paddle:sync --days=90        # as far back as Paddle keeps
 *   php artisan paddle:sync --pretend        # say what it would do
 */
class PaddleSyncCommand extends Command
{
    protected $signature = 'paddle:sync
        {--days=7 : How far back to look}
        {--pretend : Report what is missing without changing anything}';

    protected $description = 'Bring credits in line with Paddle, in case a webhook was missed';

    public function handle(): int
    {
        if ((string) config('paddle.api_key') === '') {
            $this->error('No Paddle API key is set here.');

            return self::FAILURE;
        }

        $since = now()->subDays((int) $this->option('days'))->toRfc3339String();
        $pretend = (bool) $this->option('pretend');

        $granted = $this->purchases($since, $pretend);
        $revoked = $this->refunds($pretend);
        $restored = $this->missingUnits($pretend);

        $this->newLine();
        $this->line(match (true) {
            $granted === 0 && $revoked === 0 && $restored === 0 => '  <fg=green>Nothing missing.</> Paddle and the ledger agree.',
            $pretend => "  <fg=yellow>{$granted} purchase(s), {$revoked} refund(s) and {$restored} credit(s) are missing.</> Run again without --pretend.",
            default => "  <fg=cyan>Added {$granted} purchase(s), {$revoked} refund(s) and {$restored} credit(s).</>",
        });

        return self::SUCCESS;
    }

    /**
     * Completed transactions that never became credits.
     */
    private function purchases(string $since, bool $pretend): int
    {
        $added = 0;

        foreach ($this->pages('/transactions', ['status' => 'completed', 'updated_at[GTE]' => $since]) as $transaction) {
            $id = (string) ($transaction['id'] ?? '');
            $userId = $transaction['custom_data']['user_id'] ?? null;

            // A sale with nobody attached is not ours to guess at — it may be
            // a test made in Paddle's own dashboard.
            if ($id === '' || $userId === null) {
                continue;
            }

            if (CreditEntry::where('reason', CreditEntry::PURCHASE)->where('reference', $id)->exists()) {
                continue;
            }

            $user = User::find($userId);
            $credits = $this->creditsIn($transaction);

            if ($user === null || $credits < 1) {
                $this->warn("  {$id}: ".($user === null ? "no teacher {$userId}" : 'nothing we sell'));

                continue;
            }

            $this->line("  <fg=yellow>missing</> {$id} → {$user->email}, {$credits} credit(s)");

            if (! $pretend) {
                $user->grantCredits($credits, CreditEntry::PURCHASE, $id, 'Found by paddle:sync');
            }

            $added++;
        }

        return $added;
    }

    /**
     * Approved refunds and chargebacks whose credits are still on an account.
     */
    private function refunds(bool $pretend): int
    {
        $taken = 0;

        foreach ($this->pages('/adjustments', ['status' => 'approved', 'action' => 'refund,chargeback']) as $adjustment) {
            $id = (string) ($adjustment['id'] ?? '');
            $transaction = (string) ($adjustment['transaction_id'] ?? '');

            if ($id === '' || CreditEntry::where('reason', CreditEntry::REFUND)->where('reference', $id)->exists()) {
                continue;
            }

            $purchase = CreditEntry::where('reason', CreditEntry::PURCHASE)->where('reference', $transaction)->first();
            if ($purchase === null) {
                continue;
            }

            $this->line("  <fg=yellow>missing refund</> {$id} → {$purchase->user->email}, {$purchase->delta} credit(s) back");

            if (! $pretend) {
                $purchase->user->revokeCredits($purchase, $id, "Refund of {$transaction}, found by paddle:sync");
            }

            $taken++;
        }

        return $taken;
    }

    /**
     * Credits a teacher paid for but cannot spend.
     *
     * The ledger is the record of money and the units are the thing a teacher
     * spends; a purchase with fewer units than it granted is a teacher who
     * paid and got nothing. Refunded purchases are left alone — their credits
     * are missing on purpose.
     */
    private function missingUnits(bool $pretend): int
    {
        $restored = 0;

        $entries = CreditEntry::whereIn('reason', [CreditEntry::PURCHASE, CreditEntry::ADMIN])
            ->where('delta', '>', 0)
            ->whereNull('refunded_at')
            ->with('user')
            ->get();

        foreach ($entries as $entry) {
            $have = CreditUnit::where('credit_entry_id', $entry->id)->count();
            $short = $entry->delta - $have;

            if ($short < 1 || $entry->user === null) {
                continue;
            }

            $this->line("  <fg=yellow>missing credits</> {$entry->reference} → {$entry->user->email}, {$short} of {$entry->delta}");

            if (! $pretend) {
                foreach (range(1, $short) as $ignored) {
                    $entry->user->creditUnits()->create(['credit_entry_id' => $entry->id]);
                }
            }

            $restored += $short;
        }

        return $restored;
    }

    /**
     * Every page of a Paddle list, as plain arrays.
     *
     * @param  array<string, string>  $query
     * @return iterable<array<string, mixed>>
     */
    private function pages(string $path, array $query): iterable
    {
        $after = null;

        do {
            $response = $this->paddle()->get($path, $query + array_filter(['after' => $after]));

            if ($response->failed()) {
                $this->error('  Paddle said: '.$response->body());

                return;
            }

            foreach ((array) $response->json('data', []) as $row) {
                yield $row;
            }

            $rows = (array) $response->json('data', []);
            $last = end($rows);
            $after = $response->json('meta.pagination.has_more') ? ($last['id'] ?? null) : null;
        } while ($after !== null);
    }

    private function paddle(): PendingRequest
    {
        return Http::withToken((string) config('paddle.api_key'))
            ->acceptJson()
            ->timeout(30)
            ->baseUrl(rtrim((string) config('paddle.api_url'), '/'));
    }

    /**
     * What a transaction is worth here — the same mapping the webhook uses,
     * from config rather than from Paddle.
     *
     * @param  array<string, mixed>  $transaction
     */
    private function creditsIn(array $transaction): int
    {
        $prices = (array) config('paddle.credits');
        $credits = 0;

        foreach ((array) ($transaction['items'] ?? []) as $item) {
            $priceId = (string) ($item['price']['id'] ?? '');
            $credits += ($prices[$priceId] ?? 0) * max(1, (int) ($item['quantity'] ?? 1));
        }

        return $credits;
    }
}
