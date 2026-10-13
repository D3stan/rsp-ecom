<?php

namespace App\Mail;

use App\Mail\Traits\HasLogo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Critical internal alert: Stripe reported a successful payment but no order could be created.
 * Not tied to an order, so it carries plain scalars and is queued as a mailable of its own.
 */
class PaidOrderMissingAlert extends Mailable implements ShouldQueue
{
    use HasLogo, Queueable;

    public $tries = 5;

    public $backoff = [60, 300, 900, 3600];

    /**
     * @param  array<string, mixed>  $details  optional: payment_intent_id, amount (formatted), customer_email, cart_id, guest_session_id, error
     */
    public function __construct(public string $sessionId, public array $details = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.admin.paid_order_missing.subject'),
            tags: ['admin-paid-order-missing'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-paid-order-missing',
            text: 'emails.admin-paid-order-missing-text',
            with: [
                'rows' => $this->rows(),
                'logoUrl' => $this->getLogoUrl(),
            ],
        );
    }

    /**
     * Label/value rows of the known facts (the Stripe session first, then whatever was captured).
     *
     * @return array<int, array{label: string, value: string}>
     */
    private function rows(): array
    {
        $rows = [['label' => __('emails.admin.paid_order_missing.session'), 'value' => $this->sessionId]];

        $labels = [
            'payment_intent_id' => __('emails.admin.paid_order_missing.payment_intent'),
            'amount' => __('emails.admin.paid_order_missing.amount'),
            'customer_email' => __('emails.admin.paid_order_missing.customer_email'),
            'cart_id' => __('emails.admin.paid_order_missing.cart'),
            'guest_session_id' => __('emails.admin.paid_order_missing.guest_session'),
            'error' => __('emails.admin.paid_order_missing.error'),
        ];

        foreach ($labels as $key => $label) {
            if (! empty($this->details[$key])) {
                $rows[] = ['label' => $label, 'value' => (string) $this->details[$key]];
            }
        }

        return $rows;
    }
}
