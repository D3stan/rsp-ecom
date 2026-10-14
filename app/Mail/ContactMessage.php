<?php

namespace App\Mail;

use App\Mail\Traits\HasLogo;
use App\Support\Privacy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ContactMessage extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, HasLogo;

    public int $tries = 5;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public array $contactData
    ) {
        // Header values must never carry line breaks (header injection); the message body keeps them.
        foreach (['firstName', 'lastName', 'subject'] as $field) {
            if (isset($this->contactData[$field])) {
                $this->contactData[$field] = self::singleLine((string) $this->contactData[$field]);
            }
        }

        $this->afterCommit();
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function failed(?Throwable $e): void
    {
        Log::error('Contact form message permanently failed', [
            'visitor' => Privacy::maskEmail($this->contactData['email'] ?? null),
            'error' => $e?->getMessage(),
        ]);
    }

    /**
     * Collapse control characters (CR, LF, tabs...) into single spaces.
     */
    public static function singleLine(string $value): string
    {
        return trim(preg_replace('/[\p{Cc}\p{Cf}\x{2028}\x{2029}]+/u', ' ', $value) ?? '');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $visitorName = trim($this->contactData['firstName'] . ' ' . $this->contactData['lastName']);

        // Sent From our own domain (SPF/DKIM/DMARC aligned); the visitor is only the display name and Reply-To.
        // Characters that could make the display name look like another address are dropped.
        $displayName = trim(str_replace(['<', '>', '"', '@', ';'], '', $visitorName).' via '.settings('store.name'));

        return new Envelope(
            from: new Address(config('mail.from.address'), $displayName),
            replyTo: [new Address($this->contactData['email'], str_replace(['<', '>', '"'], '', $visitorName))],
            subject: 'Contact Form: ' . $this->contactData['subject'],
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
                'appName' => settings('store.name'),
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
