<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PhoneChangeApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $newPhone;

    /**
     * Create a new message instance.
     */
    public function __construct($user, $newPhone)
    {
        $this->user = $user;
        $this->newPhone = $newPhone;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '✅ Phone Number Change Approved',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.phone-change-approved',
            with: [
                'userName' => $this->user->first_name . ' ' . $this->user->last_name,
                'newPhone' => $this->newPhone,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
