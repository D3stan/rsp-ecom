<?php

namespace App\Mail;

use App\Mail\Traits\HasLogo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestEmail extends Mailable
{
    use HasLogo, Queueable, SerializesModels;

    public function __construct() {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.test.subject', ['app' => settings('store.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.test-email',
            text: 'emails.test-email-text',
            with: [
                'logoUrl' => $this->getLogoUrl(),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return $this->getLogoAttachment();
    }
}
