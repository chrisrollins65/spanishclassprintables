<?php

namespace App\Mail;

use App\Models\Feedback;
use App\Models\GameEvent;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

/**
 * The one email asking a teacher how their first game went.
 *
 * A face per link, each opening the feedback page with that face chosen. Sent
 * once per account and never again (AskForFeedback), and it says so.
 */
class HowDidItGo extends Mailable
{
    /** Long enough for a teacher who gets to their inbox at half term. */
    private const LINK_DAYS = 30;

    public function __construct(public User $teacher, public GameEvent $play) {}

    public function envelope(): Envelope
    {
        $inbox = (string) config('site.contact_inbox');

        return new Envelope(
            subject: 'How did your game go?',
            replyTo: $inbox !== '' ? [new Address($inbox)] : [],
        );
    }

    public function content(): Content
    {
        $faces = [];
        foreach (Feedback::RATINGS as $rating => $option) {
            $faces[] = $option + ['url' => URL::temporarySignedRoute('feedback.show', now()->addDays(self::LINK_DAYS), [
                'user' => $this->teacher,
                'about' => $this->play->id,
                'rating' => $rating,
                'from' => 'email',
            ])];
        }

        return new Content(
            view: 'mail.how-did-it-go',
            text: 'mail.how-did-it-go-text',
            with: ['faces' => $faces, 'theme' => $this->play->theme()],
        );
    }
}
