<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PhoneChangeRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $requestedPhone;

    /**
     * Create a new message instance.
     */
    public function __construct($user, $requestedPhone)
    {
        $this->user = $user;
        $this->requestedPhone = $requestedPhone;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '❌ Phone Number Change Request Rejected',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.phone-change-rejected',
            with: [
                'userName' => $this->user->first_name . ' ' . $this->user->last_name,
                'requestedPhone' => $this->requestedPhone,
                'currentPhone' => $this->user->phone,
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
