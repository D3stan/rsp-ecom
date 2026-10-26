<?php

namespace App\Mail;

use App\Mail\Traits\HasLogo;
use App\Models\CheckoutSnapshot;
use App\Models\NewsletterSubscriber;
use App\Models\Product;
use App\Services\Newsletter\AbandonedCheckoutReminders;
use App\Services\Newsletter\MarketingConsent;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * "You left something in your cart": the lines of an expired checkout, a signed link that rebuilds the cart
 * (valid AbandonedCheckoutReminders::RECOVERY_LINK_DAYS) and a one-click unsubscribe.
 */
class AbandonedCheckoutReminder extends Mailable
{
    use HasLogo, Queueable, SerializesModels;

    public string $recoverUrl;

    public ?string $unsubscribeUrl;

    public function __construct(public CheckoutSnapshot $snapshot, public ?NewsletterSubscriber $subscriber = null)
    {
        $this->recoverUrl = URL::temporarySignedRoute(
            'checkout.recover',
            now()->addDays(AbandonedCheckoutReminders::RECOVERY_LINK_DAYS),
            ['snapshot' => $snapshot->id],
        );
        $this->unsubscribeUrl = $subscriber?->unsubscribeUrl();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), app(MarketingConsent::class)->senderName()),
            subject: __('newsletter.reminder.subject', ['store' => settings('store.name')]),
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
            view: 'emails.abandoned-checkout-reminder',
            text: 'emails.abandoned-checkout-reminder-text',
            with: [
                'greeting' => __('emails.common.greeting_generic'),
                'items' => $this->items(),
                'recoverUrl' => $this->recoverUrl,
                'validDays' => AbandonedCheckoutReminders::RECOVERY_LINK_DAYS,
                'unsubscribeUrl' => $this->unsubscribeUrl,
                'logoUrl' => $this->getLogoUrl(),
            ],
        );
    }

    /**
     * Lines in the shape of the emails.partials.items partial.
     *
     * @return array<int, array{name: string, options: array<int, string>, quantity: int, unit: string, total: string, image: string}>
     */
    private function items(): array
    {
        $currency = (string) ($this->snapshot->currency ?: settings('store.currency', 'EUR'));
        $products = Product::query()->whereIn('id', collect($this->snapshot->lines)->pluck('product_id')->filter())->get()->keyBy('id');

        return collect($this->snapshot->lines)->map(function (array $line) use ($currency, $products) {
            $quantity = (int) ($line['quantity'] ?? 1);
            $price = (float) ($line['price'] ?? 0);
            $variant = $line['variant_name'] ?? null;
            $product = $products->get($line['product_id'] ?? null);

            return [
                'name' => (string) ($line['product_name'] ?? ''),
                'options' => $variant && $variant !== 'Standard' ? [__('emails.common.variant', ['variant' => $variant])] : [],
                'quantity' => $quantity,
                'unit' => Money::format($price, $currency),
                'total' => Money::format($price * $quantity, $currency),
                'image' => (string) ($product?->image_url ?: $this->getLogoUrl()),
            ];
        })->values()->all();
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return $this->getLogoAttachment();
    }
}
