<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A contact form message, forwarded to the store's inbox.
 *
 * Reply-To is the teacher's own address when they gave one, so answering is
 * just pressing reply.
 */
class ContactMessageReceived extends Mailable
{
    public function __construct(public ContactMessage $contactMessage) {}

    public function envelope(): Envelope
    {
        $email = $this->contactMessage->email;

        return new Envelope(
            subject: 'Website message: '.$this->contactMessage->topicLabel(),
            replyTo: $email ? [new Address($email, $this->contactMessage->name)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.contact-message');
    }
}
