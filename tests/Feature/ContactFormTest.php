<?php

namespace Tests\Feature;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    private function send(array $fields = [])
    {
        return $this->post('/contact', array_merge([
            'name' => 'Ms. García',
            'email' => 'garcia@example.com',
            'topic' => 'idea',
            'message' => 'Could you make a packet about the weather?',
        ], $fields));
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
}
