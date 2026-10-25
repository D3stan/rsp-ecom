<?php

namespace App\Mail;

use App\Mail\Traits\HasLogo;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WelcomeEmail extends Mailable
{
    use HasLogo;

    public $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.welcome.subject', ['app' => settings('store.name')]),
        );
    }

    public function content(): Content
    {
        $name = trim((string) $this->user->name);

        return new Content(
            view: 'emails.welcome',
            text: 'emails.welcome-text',
            with: [
                'user' => $this->user,
                'greeting' => $name !== ''
                    ? __('emails.common.greeting', ['name' => $name])
                    : __('emails.common.greeting_generic'),
                'accountUrl' => route('dashboard'),
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
