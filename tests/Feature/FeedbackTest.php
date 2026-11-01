<?php

namespace Tests\Feature;

use App\Mail\FeedbackReceived;
use App\Mail\HowDidItGo;
use App\Models\Feedback;
use App\Models\GameEvent;
use App\Models\Room;
use App\Models\TeacherGame;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Teachers telling us how a game went: from the game screen, from a signed
 * link, and the one email that asks.
 */
class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Room::create(['code' => 'ABCD', 'theme' => 'La familia', 'payload' => '{}']);
        config(['site.contact_inbox' => 'shop@example.com']);
        Mail::fake();
    }

    /** @param  array<string, mixed>  $overrides */
    private function answer(array $overrides = []): void
    {
        $this->postJson('/api/feedback', $overrides + [
            'token' => '9f3b2c1e-0000-4000-8000-000000000001',
            'rating' => 3,
            'room_code' => 'ABCD',
            'game' => 'bingo',
            'source' => 'game',
        ])->assertNoContent();
    }

    public function test_a_tap_on_a_face_is_saved_on_its_own(): void
    {
        $this->answer();

        $feedback = Feedback::sole();
        $this->assertSame(3, $feedback->rating);
        $this->assertSame('ABCD', $feedback->room_code);
        $this->assertSame('game', $feedback->source);
    }

    public function test_the_comment_lands_on_the_row_the_tap_made(): void
    {
        $this->answer(['rating' => 2]);
        $this->answer(['rating' => 2, 'issues' => ['audio', 'long'], 'comment' => 'The voice was too fast']);

        $feedback = Feedback::sole();
        $this->assertSame(['audio', 'long'], $feedback->issues);
        $this->assertSame('The voice was too fast', $feedback->comment);
        $this->assertSame(['Audio didn’t work', 'Took too long'], $feedback->issueLabels());
    }

    public function test_what_worked_lands_on_the_row_the_tap_made(): void
    {
        $this->answer();
        $this->answer(['highlights' => ['engaged', 'time'], 'comment' => 'They begged to play again']);

        $feedback = Feedback::sole();
        $this->assertSame(['engaged', 'time'], $feedback->highlights);
        $this->assertSame(['Students were engaged', 'Saved me time'], $feedback->highlightLabels());
        $this->assertNull($feedback->issues);
    }

    public function test_a_late_rating_does_not_blank_what_worked(): void
    {
        $this->answer(['highlights' => ['engaged']]);
        $this->answer();

        $this->assertSame(['engaged'], Feedback::sole()->highlights);
    }

    public function test_an_invented_highlight_keeps_nothing(): void
    {
        $this->answer(['highlights' => ['made_up']]);

        $this->assertDatabaseEmpty('feedback');
    }

    public function test_the_page_can_ask_both_what_worked_and_what_got_in_the_way(): void
    {
        $this->get($this->link(User::factory()->create(), ['rating' => 3]))
            ->assertSee('Students were engaged')
            ->assertSee('Audio didn’t work');
    }

    public function test_one_answer_can_say_both_what_worked_and_what_got_in_the_way(): void
    {
        $this->answer(['highlights' => ['engaged'], 'issues' => ['long']]);

        $feedback = Feedback::sole();
        $this->assertSame(['Students were engaged'], $feedback->highlightLabels());
        $this->assertSame(['Took too long'], $feedback->issueLabels());
    }

    public function test_answering_what_worked_from_a_link_is_saved(): void
    {
        $teacher = User::factory()->create();

        $this->post($this->link($teacher), [
            'token' => (string) Str::uuid(),
            'rating' => 3,
            'highlights' => ['practice'],
        ])->assertRedirect();

        $this->assertSame(['practice'], Feedback::sole()->highlights);
    }

    public function test_a_late_rating_does_not_blank_a_comment_that_overtook_it(): void
    {
        $this->answer(['comment' => 'Great game']);
        $this->answer();

        $this->assertSame('Great game', Feedback::sole()->comment);
    }

    public function test_a_later_post_cannot_move_an_answer_to_another_room(): void
    {
        Room::create(['code' => 'WXYZ', 'theme' => 'Otro', 'payload' => '{}']);

        $this->answer();
        $this->answer(['room_code' => 'WXYZ']);

        $this->assertSame('ABCD', Feedback::sole()->room_code);
    }

    public function test_the_honeypot_keeps_nothing(): void
    {
        $this->answer(['website' => 'http://spam.example']);

        $this->assertDatabaseEmpty('feedback');
    }

    public function test_a_room_that_does_not_exist_keeps_nothing(): void
    {
        $this->answer(['room_code' => 'NOPE']);

        $this->assertDatabaseEmpty('feedback');
    }

    public function test_an_invented_issue_keeps_nothing(): void
    {
        $this->answer(['issues' => ['made_up']]);

        $this->assertDatabaseEmpty('feedback');
    }

    public function test_someone_elses_game_keeps_nothing(): void
    {
        $game = TeacherGame::factory()->create();

        $this->actingAs(User::factory()->create());
        $this->answer(['room_code' => null, 'own_game' => $game->id]);

        $this->assertDatabaseEmpty('feedback');
    }

    public function test_a_bad_game_someone_described_reaches_the_inbox_once(): void
    {
        $this->answer(['rating' => 1, 'comment' => 'The board did not load']);
        $this->answer(['rating' => 1, 'comment' => 'The board did not load', 'issues' => ['broke']]);

        Mail::assertSent(FeedbackReceived::class, 1);
    }

    public function test_an_address_left_for_a_reply_reaches_the_inbox(): void
    {
        $this->answer(['comment' => 'Do you have one on food?', 'email' => 'teacher@example.com']);

        Mail::assertSent(FeedbackReceived::class, fn (FeedbackReceived $mail): bool => $mail->hasReplyTo('teacher@example.com'));
    }

    public function test_a_happy_tap_bothers_nobody(): void
    {
        $this->answer(['comment' => 'Lovely']);
        $this->answer(['token' => (string) Str::uuid(), 'rating' => 1]);

        Mail::assertNothingSent();
    }

    public function test_a_comment_full_of_links_is_held_back(): void
    {
        $this->answer(['rating' => 1, 'comment' => 'http://a.example http://b.example http://c.example']);

        $this->assertSame('too many links', Feedback::sole()->spam_reason);
        Mail::assertNothingSent();
    }

    public function test_opening_a_link_saves_nothing_because_a_mail_scanner_opens_them_too(): void
    {
        $teacher = User::factory()->create();

        $this->get($this->link($teacher, ['rating' => 3]))
            ->assertOk()
            ->assertSee('value="3" required checked', false);

        $this->assertDatabaseEmpty('feedback');
    }

    public function test_a_link_without_its_signature_is_refused(): void
    {
        $teacher = User::factory()->create();

        $this->get("/feedback/{$teacher->id}?rating=3")->assertForbidden();
        $this->post("/feedback/{$teacher->id}", ['token' => (string) Str::uuid(), 'rating' => 3])->assertForbidden();
    }

    public function test_answering_from_a_link_is_tied_to_the_teacher_and_the_game(): void
    {
        $teacher = User::factory()->create();
        $play = GameEvent::factory()->create(['user_id' => $teacher->id, 'game' => 'bingo', 'room_code' => 'ABCD']);

        $this->post($this->link($teacher, ['about' => $play->id, 'from' => 'my_games']), [
            'token' => (string) Str::uuid(),
            'rating' => 2,
            'comment' => 'Too many words',
        ])->assertRedirect();

        $feedback = Feedback::sole();
        $this->assertSame($teacher->id, $feedback->user_id);
        $this->assertSame('ABCD', $feedback->room_code);
        $this->assertSame('my_games', $feedback->source);
    }

    public function test_a_link_cannot_be_pointed_at_another_teachers_play(): void
    {
        $teacher = User::factory()->create();
        $play = GameEvent::factory()->create(['user_id' => User::factory()->create()->id, 'room_code' => 'ABCD']);

        $this->post($this->link($teacher, ['about' => $play->id]), [
            'token' => (string) Str::uuid(),
            'rating' => 2,
        ]);

        $this->assertNull(Feedback::sole()->room_code);
    }

    /** @param  array<string, mixed>  $query */
    private function link(User $teacher, array $query = []): string
    {
        return URL::signedRoute('feedback.show', ['user' => $teacher] + $query);
    }

    private function teacherWhoPlayed(string $when = '-25 hours'): User
    {
        $teacher = User::factory()->create();
        GameEvent::factory()->create(['user_id' => $teacher->id, 'created_at' => now()->modify($when)]);

        return $teacher;
    }

    public function test_the_day_after_a_game_the_teacher_is_asked_once(): void
    {
        $teacher = $this->teacherWhoPlayed();

        Artisan::call('feedback:ask');
        Artisan::call('feedback:ask');

        Mail::assertSent(HowDidItGo::class, 1);
        Mail::assertSent(HowDidItGo::class, fn (HowDidItGo $mail): bool => $mail->hasTo($teacher->email));
        $this->assertNotNull($teacher->fresh()->feedback_asked_at);
    }

    public function test_a_game_played_today_is_not_asked_about_yet(): void
    {
        $this->teacherWhoPlayed('-2 hours');

        Artisan::call('feedback:ask');

        Mail::assertNothingSent();
    }

    public function test_a_game_from_weeks_ago_is_not_asked_about(): void
    {
        $this->teacherWhoPlayed('-8 days');

        Artisan::call('feedback:ask');

        Mail::assertNothingSent();
    }

    public function test_a_teacher_who_already_told_us_is_not_asked(): void
    {
        Feedback::factory()->create(['user_id' => $this->teacherWhoPlayed()->id]);

        Artisan::call('feedback:ask');

        Mail::assertNothingSent();
    }

    public function test_an_unconfirmed_address_is_not_written_to(): void
    {
        $this->teacherWhoPlayed()->forceFill(['email_verified_at' => null])->save();

        Artisan::call('feedback:ask');

        Mail::assertNothingSent();
    }

    public function test_the_email_links_open_the_page_with_a_face_chosen(): void
    {
        $teacher = $this->teacherWhoPlayed();
        $mail = new HowDidItGo($teacher, $teacher->gameEvents()->first());

        $mail->assertSeeInHtml('Loved it');
        $mail->assertSeeInHtml('signature=', false);
        $mail->assertSeeInText('only ask this once');
    }

    public function test_my_games_asks_about_a_finished_game_and_stops_once_answered(): void
    {
        $teacher = User::factory()->create();
        GameEvent::factory()->create(['user_id' => $teacher->id, 'event' => 'game_finished', 'created_at' => now()->subHours(3)]);

        $this->actingAs($teacher)->get('/my-games')->assertSee('Loved it');

        Feedback::factory()->create(['user_id' => $teacher->id]);

        $this->actingAs($teacher)->get('/my-games')->assertDontSee('Loved it');
    }

    public function test_my_games_stays_quiet_while_the_game_may_still_be_going(): void
    {
        $teacher = User::factory()->create();
        GameEvent::factory()->create(['user_id' => $teacher->id, 'event' => 'game_finished', 'created_at' => now()->subMinutes(10)]);

        $this->actingAs($teacher)->get('/my-games')->assertDontSee('Loved it');
    }
}
