<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin's Messages page.
 */
class MessagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        config(['site.admins' => ['shop@example.com']]);

        return User::factory()->create(['email' => 'shop@example.com']);
    }

    /** @param  array<string, mixed>  $attributes */
    private function contactMessage(array $attributes = []): ContactMessage
    {
        return ContactMessage::forceCreate($attributes + ['topic' => 'games', 'message' => 'A question about bingo']);
    }

    public function test_only_an_admin_gets_in(): void
    {
        $this->admin();

        $this->get('/admin/messages')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/messages')->assertForbidden();
    }

    public function test_an_empty_site_still_draws(): void
    {
        $this->actingAs($this->admin())->get('/admin/messages')->assertOk()->assertSee('Nothing here yet.');
    }

    public function test_both_forms_arrive_together_newest_first(): void
    {
        $this->contactMessage(['message' => 'The older contact message', 'created_at' => now()->subDays(3)]);
        Feedback::factory()->create(['comment' => 'The newest feedback', 'created_at' => now()->subDay()]);
        $this->contactMessage(['message' => 'The middle contact message', 'created_at' => now()->subDays(2)]);

        $this->actingAs($this->admin())->get('/admin/messages')
            ->assertOk()
            ->assertSeeInOrder(['The newest feedback', 'The middle contact message', 'The older contact message']);
    }

    public function test_a_rating_with_nothing_else_is_not_a_message(): void
    {
        Feedback::factory()->create();
        Feedback::factory()->create(['issues' => ['audio']]);
        Feedback::factory()->create(['highlights' => ['engaged']]);

        $this->actingAs($this->admin())->get('/admin/messages')
            ->assertOk()
            ->assertSee('Audio didn’t work')
            ->assertSee('Students were engaged')
            ->assertViewHas('messages', fn ($messages): bool => $messages->total() === 2);
    }

    public function test_each_filter_shows_its_own(): void
    {
        $admin = $this->admin();
        $this->contactMessage(['message' => 'Plain contact']);
        $this->contactMessage(['message' => 'Spam contact', 'spam_reason' => 'too many links']);
        Feedback::factory()->create(['comment' => 'Plain feedback']);
        Feedback::factory()->create(['comment' => 'Spam feedback', 'spam_reason' => 'too many links']);

        $this->actingAs($admin)->get('/admin/messages')
            ->assertSee(['Plain contact', 'Plain feedback'])->assertDontSee(['Spam contact', 'Spam feedback']);
        $this->actingAs($admin)->get('/admin/messages?show=contact')
            ->assertSee('Plain contact')->assertDontSee(['Plain feedback', 'Spam contact']);
        $this->actingAs($admin)->get('/admin/messages?show=feedback')
            ->assertSee('Plain feedback')->assertDontSee(['Plain contact', 'Spam feedback']);
        $this->actingAs($admin)->get('/admin/messages?show=held')
            ->assertSee(['Spam contact', 'Spam feedback', 'Held: too many links'])->assertDontSee(['Plain contact', 'Plain feedback']);
    }

    public function test_an_unknown_filter_falls_back_to_all(): void
    {
        $this->contactMessage(['message' => 'Plain contact']);

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/messages?show=nonsense')->assertOk()->assertSee('Plain contact');
        $this->actingAs($admin)->get('/admin/messages?show[]=held')->assertOk()->assertSee('Plain contact');
    }

    public function test_it_pages_across_both_tables_and_keeps_the_filter(): void
    {
        foreach (range(1, 15) as $number) {
            $this->contactMessage(['created_at' => now()->subMinutes($number)]);
            Feedback::factory()->create(['comment' => "Comment {$number}", 'created_at' => now()->subMinutes($number)]);
        }

        $this->actingAs($this->admin())->get('/admin/messages?show=all&page=2')
            ->assertOk()
            ->assertViewHas('messages', fn ($messages): bool => $messages->total() === 30 && $messages->count() === 5)
            ->assertSee('show=all', false);
    }

    public function test_what_a_stranger_wrote_is_escaped(): void
    {
        $this->contactMessage(['message' => '<script>alert(1)</script>']);

        $this->actingAs($this->admin())->get('/admin/messages')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_a_teachers_feedback_links_to_them_and_can_be_answered(): void
    {
        $teacher = User::factory()->create(['email' => 'teacher@example.com']);
        Feedback::factory()->create(['user_id' => $teacher->id, 'comment' => 'Lovely']);
        $this->contactMessage(['name' => 'Ana', 'email' => 'ana@example.com']);

        $this->actingAs($this->admin())->get('/admin/messages')
            ->assertSee(route('admin.teacher', $teacher), false)
            ->assertSee('mailto:teacher@example.com', false)
            ->assertSee('mailto:ana@example.com', false);
    }
}
