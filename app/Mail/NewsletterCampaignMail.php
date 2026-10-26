<?php

namespace App\Mail;

use App\Mail\Traits\HasLogo;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Services\Newsletter\CampaignRenderer;
use App\Services\Newsletter\MarketingConsent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * One newsletter campaign email. To a subscriber: personal signed unsubscribe link in the footer and the
 * RFC 8058 one-click headers. Without a subscriber (admin test / preview): "[TEST]" subject, placeholder link.
 */
class NewsletterCampaignMail extends Mailable
{
    use HasLogo, Queueable, SerializesModels;

    public ?string $unsubscribeUrl;

    public function __construct(
        public NewsletterCampaign $campaign,
        public ?NewsletterSubscriber $subscriber = null,
        public bool $isTest = false,
    ) {
        $this->unsubscribeUrl = $subscriber?->unsubscribeUrl();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), app(MarketingConsent::class)->senderName()),
            subject: ($this->isTest ? '[TEST] ' : '').$this->campaign->subject,
        );
    }

    public function headers(): Headers
    {
        if ($this->unsubscribeUrl === null) {
            return new Headers;
        }

        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        $renderer = app(CampaignRenderer::class);

        return new Content(
            view: 'emails.newsletter-campaign',
            text: 'emails.newsletter-campaign-text',
            with: [
                'subjectLine' => $this->campaign->subject,
                'preheader' => (string) $this->campaign->preheader,
                'bodyHtml' => $renderer->html($this->campaign->body_markdown),
                'bodyText' => $renderer->text($this->campaign->body_markdown),
                // Test sends and previews show where the personal link goes, without a working one
                'unsubscribeUrl' => $this->unsubscribeUrl ?? '#unsubscribe',
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
