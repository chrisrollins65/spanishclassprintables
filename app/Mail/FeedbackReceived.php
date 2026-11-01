<?php

namespace App\Mail;

use App\Models\Feedback;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Feedback worth answering, forwarded to the store's inbox.
 *
 * Reply-To is the teacher when there is an address to use, so a quick reply to
 * someone whose lesson went badly is just pressing reply.
 */
class FeedbackReceived extends Mailable
{
    public function __construct(public Feedback $feedback) {}

    public function envelope(): Envelope
    {
        $email = $this->feedback->email ?: $this->feedback->user?->email;

        return new Envelope(
            subject: 'Game feedback: '.$this->feedback->ratingLabel(),
            replyTo: $email ? [new Address($email, (string) $this->feedback->user?->name)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.feedback-received');
    }
}
