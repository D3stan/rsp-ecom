<?php

namespace App\Mail;

use App\Mail\Traits\HasLogo;
use App\Models\RestockNotification;
use App\Support\Money;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

/**
 * "An item you saved is back in stock" (cart or wishlist). Not an order email: it carries a one-click unsubscribe
 * (signed link in the footer + List-Unsubscribe / List-Unsubscribe-Post headers, RFC 8058).
 */
class BackInStock extends Mailable
{
    use HasLogo;

    public string $source;

    public function __construct(public RestockNotification $notification)
    {
        $this->source = $notification->source;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.back_in_stock.subject', ['product' => $this->notification->productVariant->product->name]),
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl().'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        $variant = $this->notification->productVariant;
        $product = $variant->product;
        $user = $this->notification->user;
        $name = trim((string) $user?->name);
        $fromCart = $this->source === RestockNotification::SOURCE_CART;

        return new Content(
            view: 'emails.back-in-stock',
            text: 'emails.back-in-stock-text',
            with: [
                'greeting' => $name !== ''
                    ? __('emails.common.greeting', ['name' => $name])
                    : __('emails.common.greeting_generic'),
                'intro' => $fromCart ? __('emails.back_in_stock.intro_cart') : __('emails.back_in_stock.intro_wishlist'),
                'productName' => $product->name,
                // A product's only "Standard" variant is not worth naming
                'variantName' => ($variant->name !== 'Standard' || ! $variant->is_default) ? $variant->name : null,
                'price' => Money::format($variant->price, settings('store.currency', 'EUR')),
                'image' => $this->imageUrl($variant->main_image_url),
                'actionUrl' => $fromCart
                    ? route('cart')
                    : route('products.show', ['slug' => $product->slug, 'variant' => $variant->id]),
                'actionLabel' => $fromCart ? __('emails.back_in_stock.button_cart') : __('emails.back_in_stock.button_product'),
                'unsubscribeUrl' => $this->unsubscribeUrl(),
                'logoUrl' => $this->getLogoUrl(),
            ],
        );
    }

    /**
     * Signed, without expiry: the link has to keep working for as long as the email sits in the inbox.
     */
    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('restock.unsubscribe', ['user' => $this->notification->user_id]);
    }

    private function imageUrl(string $url): string
    {
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : url($url);
    }
}
