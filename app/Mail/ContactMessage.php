<?php

namespace App\Mail;

use App\Mail\Traits\HasLogo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class ContactMessage extends Mailable
{
    use Queueable, SerializesModels, HasLogo;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public array $contactData
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->contactData['email'], $this->contactData['firstName'] . ' ' . $this->contactData['lastName']),
            replyTo: [new Address($this->contactData['email'], $this->contactData['firstName'] . ' ' . $this->contactData['lastName'])],
            subject: 'Contact Form: ' . $this->contactData['subject'],
            tags: ['contact-form'],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-message',
            with: [
                'contactData' => $this->contactData,
                'logoUrl' => $this->getLogoUrl(),
                'appName' => config('app.name'),
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
        return $this->getLogoAttachment();
    }
}
