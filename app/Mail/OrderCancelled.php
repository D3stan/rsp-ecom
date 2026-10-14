<?php

namespace App\Mail;

use App\Mail\Traits\DescribesOrder;
use App\Mail\Traits\HasLogo;
use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells the customer an order was cancelled. Rendered by SendOrderEmail (never queued itself).
 */
class OrderCancelled extends Mailable
{
    use DescribesOrder, HasLogo;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.order_cancelled.subject', ['number' => '#'.$this->order->order_number]),
        );
    }

    public function content(): Content
    {
        $order = $this->order;

        return new Content(
            view: 'emails.order-cancelled',
            text: 'emails.order-cancelled-text',
            with: array_merge($this->orderContext($order), [
                // Only the total: the order never completed, so the breakdown would only add noise
                'totals' => $this->orderTotals($order, withBreakdown: false),
                // Money was captured: the customer is owed a refund (issued manually by the shop).
                'refundDue' => $order->isPaymentCompleted(),
                'amount' => $this->money($order->total_amount, $order->currency),
                'logoUrl' => $this->getLogoUrl(),
            ]),
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return $this->getLogoAttachment();
    }
}
