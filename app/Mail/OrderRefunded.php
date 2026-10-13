<?php

namespace App\Mail;

use App\Mail\Traits\DescribesOrder;
use App\Mail\Traits\HasLogo;
use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells the customer money was returned. Must only be sent for a refund that was really executed.
 * Rendered by SendOrderEmail (never queued itself).
 */
class OrderRefunded extends Mailable
{
    use DescribesOrder, HasLogo;

    public function __construct(public Order $order, public float $refundAmount) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.order_refunded.subject', ['number' => '#'.$this->order->order_number]),
            tags: ['order-refunded'],
            metadata: [
                'order_id' => $this->order->id,
            ],
        );
    }

    public function content(): Content
    {
        $order = $this->order;

        return new Content(
            view: 'emails.order-refunded',
            text: 'emails.order-refunded-text',
            with: $this->orderContext($order) + [
                'amount' => $this->money($this->refundAmount, $order->currency),
                'orderTotal' => $this->money($order->total_amount, $order->currency),
                'isPartial' => round($this->refundAmount, 2) < round((float) $order->total_amount, 2),
                'logoUrl' => $this->getLogoUrl(),
            ],
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
