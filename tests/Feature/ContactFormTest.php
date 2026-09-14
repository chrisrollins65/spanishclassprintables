<?php

namespace Tests\Feature;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    /** A form as a person sends it: shown a minute ago, and touched, so the script answered. */
    private function send(array $fields = [])
    {
        return $this->post('/contact', array_merge([
            'name' => 'Ms. García',
            'email' => 'garcia@example.com',
            'topic' => 'idea',
            'message' => 'Could you make a packet about the weather?',
            'started' => $this->startedSecondsAgo(60),
            'human' => 'blue',
        ], $fields));
    }

    private function startedSecondsAgo(int $seconds): string
    {
        return Crypt::encryptString((string) (time() - $seconds));
    }

    public function test_a_message_is_saved_and_the_teacher_is_thanked(): void
    {
        Mail::fake();

        $this->send()
            ->assertRedirect(url('/').'#contact')
            ->assertSessionHas('contact_sent', true);

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'garcia@example.com',
            'topic' => 'idea',
        ]);
    }

    public function test_name_and_email_are_optional(): void
    {
        $this->send(['name' => '', 'email' => ''])->assertSessionHasNoErrors();

        $this->assertSame(1, ContactMessage::count());
    }

    public function test_a_message_and_a_known_topic_are_required(): void
    {
        $this->send(['message' => '', 'topic' => 'nonsense'])
            ->assertRedirect(url('/').'#contact')
            ->assertSessionHasErrors(['message', 'topic']);

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_the_honeypot_is_thanked_but_nothing_is_saved(): void
    {
        $this->send(['website' => 'http://spam.example'])
            ->assertSessionHas('contact_sent', true);

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_the_message_is_emailed_when_an_inbox_is_configured(): void
    {
        Mail::fake();
        config(['site.contact_inbox' => 'inbox@example.com']);

        $this->send();

        Mail::assertSent(ContactMessageReceived::class, function (ContactMessageReceived $mail) {
            return $mail->hasTo('inbox@example.com') && $mail->hasReplyTo('garcia@example.com');
        });
    }

    public function test_nothing_is_emailed_without_an_inbox(): void
    {
        Mail::fake();
        config(['site.contact_inbox' => '']);

        $this->send();

        Mail::assertNothingSent();
        $this->assertSame(1, ContactMessage::count());
    }

    public function test_a_post_without_a_rendered_form_is_thanked_but_nothing_is_saved(): void
    {
        foreach ([null, 'not-encrypted', Crypt::encryptString('yesterday')] as $started) {
            $this->send(['started' => $started])->assertSessionHas('contact_sent', true);
        }

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_a_post_that_never_ran_the_page_script_is_turned_back(): void
    {
        $this->send(['human' => ''])
            ->assertRedirect(url('/').'#contact')
            ->assertSessionHasErrors('human');

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_a_visitor_without_javascript_can_answer_the_question_themselves(): void
    {
        $this->send(['human' => '', 'human_answer' => ' Blue '])->assertSessionHasNoErrors();

        $this->assertSame(1, ContactMessage::count());
    }

    public function test_suspicious_messages_are_kept_but_not_emailed(): void
    {
        Mail::fake();
        config(['site.contact_inbox' => 'inbox@example.com']);

        $this->send(['started' => $this->startedSecondsAgo(1)]);
        $this->send(['message' => 'Buy now https://a.example https://b.example https://c.example']);
        $this->send(['name' => 'www.cheap-pills.example']);

        Mail::assertNothingSent();
        $this->assertSame(
            ['sent too fast', 'too many links', 'link in name'],
            ContactMessage::orderBy('id')->pluck('spam_reason')->all(),
        );
    }

    public function test_a_couple_of_links_are_fine(): void
    {
        $this->send(['message' => 'This one https://tpt.example/a is great, and so is https://tpt.example/b'])
            ->assertSessionHasNoErrors();

        $this->assertNull(ContactMessage::sole()->spam_reason);
    }

    public function test_the_homepage_only_loads_turnstile_when_it_has_keys(): void
    {
        config(['services.turnstile.site_key' => null, 'services.turnstile.secret_key' => null]);
        $this->get('/')->assertOk()->assertDontSee('cf-turnstile')->assertSee('name="started"', false);

        config(['services.turnstile.site_key' => 'site-key', 'services.turnstile.secret_key' => 'secret']);
        $this->get('/')->assertOk()->assertSee('data-sitekey="site-key"', false);
    }

    public function test_turnstile_must_pass_when_it_is_on(): void
    {
        config(['services.turnstile.site_key' => 'site-key', 'services.turnstile.secret_key' => 'secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()
            ->push(['success' => false])
            ->push(['success' => true])]);

        $this->send(['cf-turnstile-response' => 'bad'])->assertSessionHasErrors('turnstile');
        $this->assertSame(0, ContactMessage::count());

        $this->send(['cf-turnstile-response' => 'good'])->assertSessionHasNoErrors();
        $this->assertNull(ContactMessage::sole()->spam_reason);
    }

    public function test_a_message_is_held_not_lost_when_cloudflare_is_down(): void
    {
        config(['services.turnstile.site_key' => 'site-key', 'services.turnstile.secret_key' => 'secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response('', 503)]);

        $this->send(['cf-turnstile-response' => 'token'])->assertSessionHasNoErrors();

        $this->assertSame('captcha unavailable', ContactMessage::sole()->spam_reason);
    }
}
