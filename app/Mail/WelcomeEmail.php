<?php

namespace App\Mail;

use App\Mail\Traits\HasLogo;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WelcomeEmail extends Mailable
{
    use HasLogo;

    public $user;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.welcome.subject', ['app' => config('app.name')]),
            tags: ['welcome'],
            metadata: [
                'user_id' => $this->user->id,
            ],
        );
    }

    /**
     * Get the message content definition.
     */
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
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return $this->getLogoAttachment();
    }
}
