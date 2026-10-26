<?php

namespace App\Mail;

use App\Mail\Traits\HasLogo;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Product;
use App\Services\Newsletter\MarketingConsent;
use App\Services\Reviews\ReviewRequests;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * "How did we do?": a consented invitation to review the delivered products the customer has not reviewed yet, each
 * with a direct link to the review form, plus a one-click unsubscribe (same marketing consent as the newsletter).
 */
class ReviewRequest extends Mailable
{
    use HasLogo, SerializesModels;

    public ?string $unsubscribeUrl;

    public function __construct(public Order $order)
    {
        $email = $order->getCustomerEmail();
        $subscriber = $email !== null
            ? NewsletterSubscriber::query()->where('email', NewsletterSubscriber::normalizeEmail($email))->first()
            : null;

        $this->unsubscribeUrl = $subscriber?->unsubscribeUrl();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), app(MarketingConsent::class)->senderName()),
            subject: __('emails.review_request.subject', ['store' => settings('store.name')]),
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
        return new Content(
            view: 'emails.review-request',
            text: 'emails.review-request-text',
            with: [
                'greeting' => __('emails.common.greeting_generic'),
                'products' => $this->products(),
                'unsubscribeUrl' => $this->unsubscribeUrl,
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

    /**
     * The products to invite a review for: those the customer has not reviewed, with an absolute image and a direct
     * link to the review form.
     *
     * @return array<int, array{name: string, image: string, url: string}>
     */
    private function products(): array
    {
        return app(ReviewRequests::class)
            ->unreviewedProducts($this->order)
            ->map(fn (Product $product) => [
                'name' => $product->name,
                'image' => $this->absoluteImage($product->main_image_url),
                'url' => route('reviews.create', $product),
            ])
            ->values()
            ->all();
    }

    private function absoluteImage(string $image): string
    {
        return filter_var($image, FILTER_VALIDATE_URL) ? $image : url($image);
    }
}
