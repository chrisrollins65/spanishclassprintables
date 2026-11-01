<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Teacher accounts: Fortify's back end behind our own pages
 * (docs/teacher-games.md, Phase 1).
 */
class TeacherAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_account_page_renders(): void
    {
        foreach (['/login', '/register', '/forgot-password', '/reset-password/some-token'] as $page) {
            $this->get($page)->assertOk();
        }
    }

    public function test_signing_up_sends_a_confirmation_link_and_logs_the_teacher_in(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Ana Profe',
            'email' => 'ana@example.com',
            'password' => 'a long enough password',
            'password_confirmation' => 'a long enough password',
        ])->assertRedirect('/my-games');

        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_a_short_password_is_refused(): void
    {
        $this->post('/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'short1',
            'password_confirmation' => 'short1',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_my_games_needs_a_login_and_then_a_confirmed_email(): void
    {
        $this->get('/my-games')->assertRedirect('/login');

        $unconfirmed = User::factory()->unverified()->create();
        $this->actingAs($unconfirmed)->get('/my-games')->assertRedirect('/email/verify');

        $this->actingAs(User::factory()->create())->get('/my-games')->assertOk();
    }

    public function test_an_unconfirmed_teacher_can_still_reach_the_page_that_fixes_their_address(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get('/account')
            ->assertOk();
    }

    public function test_the_confirmation_link_confirms_the_address(): void
    {
        $user = User::factory()->unverified()->create();

        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($link)->assertRedirect();
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_a_new_teacher_who_once_tried_the_admin_pages_is_not_sent_there(): void
    {
        config(['site.admins' => ['shop@example.com']]);
        $this->get('/admin')->assertRedirect('/login');

        $this->post('/register', [
            'name' => 'New Teacher',
            'email' => 'new@example.com',
            'password' => 'a long enough password',
            'password_confirmation' => 'a long enough password',
            'terms' => '1',
        ])->assertRedirect('/my-games');

        $user = User::where('email', 'new@example.com')->sole();
        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get('/my-games')->assertRedirect('/email/verify');
        $this->get($link)->assertRedirect('/my-games');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_confirming_an_address_forgets_an_admin_page_left_in_the_session(): void
    {
        config(['site.admins' => ['shop@example.com']]);
        $user = User::factory()->unverified()->create();

        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get('/admin')->assertRedirect('/email/verify');
        $this->get($link)->assertRedirect('/my-games?verified=1');
    }

    public function test_a_teacher_logging_in_is_not_sent_to_the_admin_pages(): void
    {
        config(['site.admins' => ['shop@example.com']]);
        $user = User::factory()->create(['password' => 'the right password']);

        $this->get('/admin')->assertRedirect('/login');

        $this->post('/login', ['email' => $user->email, 'password' => 'the right password'])
            ->assertRedirect('/my-games');
    }

    public function test_an_admin_logging_in_still_goes_back_to_the_admin_pages(): void
    {
        config(['site.admins' => ['shop@example.com']]);
        $admin = User::factory()->create(['email' => 'shop@example.com', 'password' => 'the right password']);

        $this->get('/admin/insights')->assertRedirect('/login');

        $this->post('/login', ['email' => $admin->email, 'password' => 'the right password'])
            ->assertRedirect('/admin/insights');
    }

    public function test_a_buyer_signing_up_still_comes_back_to_the_game_they_were_customizing(): void
    {
        $user = User::factory()->unverified()->create();

        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get('/my-games?code=ABCD')->assertRedirect('/email/verify');
        $this->get($link)->assertRedirect('/my-games?code=ABCD');
    }

    public function test_logging_in_and_out(): void
    {
        $user = User::factory()->create(['password' => 'the right password']);

        $this->post('/login', ['email' => $user->email, 'password' => 'the wrong password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'the right password'])
            ->assertRedirect('/my-games');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');
        $this->assertGuest();
    }

    public function test_logins_are_throttled(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong wrong wrong']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong wrong wrong'])
            ->assertStatus(429);
    }

    public function test_forms_that_send_email_are_capped_per_connection(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);
        }

        $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors('email');

        // The broker's own one-a-minute limit means only the first went out;
        // the cap is what stops a script cycling through many addresses.
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_changing_the_email_asks_for_it_to_be_confirmed_again(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->put('/user/profile-information', [
            'name' => $user->name,
            'email' => 'new@example.com',
        ])->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
