<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The pages a buyer — and Paddle's reviewer, before they approve the account —
 * has to be able to read without logging in.
 *
 * Paddle checks the live site for pricing, terms, privacy, a refund policy and
 * a way to make contact before letting you sell. Before these existed the only
 * page describing what a credit costs was behind `auth` + `verified`, so
 * nobody could see what was for sale, let alone buy it.
 */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function pages(): array
    {
        return [
            'pricing' => ['/pricing'],
            'terms' => ['/terms'],
            'privacy' => ['/privacy'],
            'refunds' => ['/refunds'],
        ];
    }

    #[DataProvider('pages')]
    public function test_a_logged_out_visitor_can_read_it(string $path): void
    {
        $this->get($path)->assertOk();
    }

    public function test_the_pricing_page_states_both_prices_and_who_sells(): void
    {
        $this->get('/pricing')
            ->assertOk()
            ->assertSee('$4.50')
            ->assertSee('$30')
            // Paddle is the merchant of record, and a buyer is entitled to
            // know before they reach a checkout with someone else's name on it.
            ->assertSee('Paddle');
    }

    /**
     * Spain's LSSI asks a commercial site to make its operator findable. The
     * brand is used everywhere else; this is the one place the person behind
     * it is named, and it has to be on each document.
     */
    #[DataProvider('legalPages')]
    public function test_the_legal_pages_identify_the_operator(string $path): void
    {
        $legal = config('site.legal');

        $this->get($path)
            ->assertOk()
            ->assertSee($legal['name'])
            ->assertSee($legal['email'])
            ->assertSee($legal['address'][0])
            ->assertSee('Paddle.com Market Ltd');
    }

    /** @return array<string, array{string}> */
    public static function legalPages(): array
    {
        return [
            'terms' => ['/terms'],
            'privacy' => ['/privacy'],
            'refunds' => ['/refunds'],
        ];
    }

    /** The refund window is one number, and every page that states it agrees. */
    public function test_the_refund_window_comes_from_one_place(): void
    {
        $days = config('site.legal.refund_days');

        $this->get('/refunds')->assertOk()->assertSee($days.' days');
        $this->get('/pricing')->assertOk()->assertSee($days.' days');
    }

    /** Reachable, or they may as well not exist — Paddle's reviewer has to find them. */
    public function test_the_footer_links_to_them(): void
    {
        $home = $this->get('/')->assertOk();

        foreach ([route('pricing'), route('terms'), route('privacy'), route('refunds')] as $url) {
            $home->assertSee($url, false);
        }
    }

    /**
     * The terms and the refund policy on the page the teacher buys from.
     *
     * A consumer is entitled to these BEFORE they are bound, not after they go
     * looking — and the person who wants a refund is already unhappy. Someone
     * who cannot find the policy opens a chargeback instead, which costs more
     * than the refund and counts against us with Paddle.
     */
    public function test_the_buy_page_links_the_terms_and_the_refund_policy(): void
    {
        $teacher = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($teacher)
            ->get('/credits')
            ->assertOk()
            ->assertSee(route('terms'), false)
            ->assertSee(route('refunds'), false);
    }

    /**
     * The homepage has to say the product exists.
     *
     * Everything was built and reachable by exactly one footer link, which is
     * the same as not being for sale. A visitor lands here from a code on a
     * packet they bought; this is what tells them they can make their own.
     */
    public function test_the_homepage_pitches_making_a_game(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Make your own game', false)
            ->assertSee(route('pricing'), false)
            // The prices, so nobody has to click to learn what it costs.
            ->assertSee('$4.50', false);
    }

    public function test_the_privacy_policy_names_who_else_handles_the_data(): void
    {
        $this->get('/privacy')
            ->assertOk()
            // Every processor a teacher's data actually reaches. A policy that
            // omits one is worse than none.
            ->assertSee('Paddle')
            ->assertSee('DigitalOcean')
            ->assertSee('Gemini')
            ->assertSee('OpenAI')
            ->assertSee('MailerLite')
            ->assertSee('Agencia Española', false);
    }
}
