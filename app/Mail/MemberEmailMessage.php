<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An admin's message to members. Each member gets their own copy, so nobody sees anyone else's address;
 * replies go to the admin who sent it.
 */
class MemberEmailMessage extends Mailable
{
    public function __construct(
        public string $messageSubject,
        public string $messageBody,
        public User $sender,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->messageSubject,
            replyTo: [new Address($this->sender->email, $this->sender->name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.member-email',
            text: 'mail.member-email-text',
        );
    }
}
