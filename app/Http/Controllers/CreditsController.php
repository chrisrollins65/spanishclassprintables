<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Buying credits (docs/teacher-games.md, Phase 2).
 *
 * The page opens Paddle's own checkout over itself; Paddle takes the money and
 * the card details, and tells the site through the webhook. Nothing here ever
 * sees a card, and nothing here grants a credit — a teacher who closed the tab
 * mid-payment, or a browser that blocked the redirect, would otherwise decide
 * whether they got what they paid for.
 */
class CreditsController extends Controller
{
    /*
     * The balance here is availableCredits(), the same count the create screen
     * gates on, and NOT the ledger total — this page said "10 credits" to a
     * teacher whose ten were all spent, and sent them here when they pressed
     * create. Drafts are named separately because a held credit is neither
     * spent nor spendable, and a teacher told only "9" would be missing eight.
     */
    public function show(Request $request): View
    {
        return view('credits.buy', [
            'credits' => $request->user()->availableCredits(),
            'held' => $request->user()->heldCredits(),
            'packs' => $this->packs(),
            'token' => (string) config('paddle.client_token'),
            'environment' => (string) config('paddle.environment'),
            'justBought' => $request->boolean('bought'),
        ]);
    }

    /**
     * The balance, for the page to watch after a purchase.
     *
     * Paddle's webhook usually lands within a second or two of the checkout
     * closing, but it is a different connection arriving on its own schedule,
     * so the page waits for it rather than assuming.
     */
    public function balance(Request $request): JsonResponse
    {
        return response()->json(['credits' => $request->user()->availableCredits()]);
    }

    /**
     * What is on sale, from config/paddle.php: the prices are Paddle's, the
     * credits are ours, and the two are paired in one place.
     *
     * @return list<array{price_id: string, credits: int, label: string}>
     */
    private function packs(): array
    {
        $packs = [];

        foreach ((array) config('paddle.credits') as $priceId => $credits) {
            if (! str_starts_with((string) $priceId, 'pri_')) {
                continue; // Not configured yet in this environment.
            }

            $packs[] = [
                'price_id' => (string) $priceId,
                'credits' => (int) $credits,
                'label' => $credits === 1 ? '1 game' : $credits.' games',
            ];
        }

        usort($packs, fn (array $a, array $b): int => $a['credits'] <=> $b['credits']);

        return $packs;
    }
}
