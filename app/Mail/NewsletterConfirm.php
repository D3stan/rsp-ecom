<?php

namespace App\Mail;

use App\Mail\Traits\HasLogo;
use App\Models\NewsletterSubscriber;
use App\Services\Newsletter\MarketingConsent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterConfirm extends Mailable
{
    use HasLogo, Queueable, SerializesModels;

    public string $confirmUrl;

    public function __construct(public NewsletterSubscriber $subscriber, string $token)
    {
        $this->confirmUrl = route('newsletter.confirm', $token);
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $consent = app(MarketingConsent::class);

        return new Envelope(
            from: new Address((string) config('mail.from.address'), $consent->senderName()),
            subject: __('newsletter.confirm_email.subject', ['store' => settings('store.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter-confirm',
            text: 'emails.newsletter-confirm-text',
            with: [
                'greeting' => __('emails.common.greeting_generic'),
                'storeName' => settings('store.name'),
                'confirmUrl' => $this->confirmUrl,
                'validDays' => MarketingConsent::CONFIRM_TOKEN_TTL_DAYS,
                'logoUrl' => $this->getLogoUrl(),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return $this->getLogoAttachment();
    }
}
