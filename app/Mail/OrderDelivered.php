<?php

namespace App\Mail;

use App\Mail\Traits\DescribesOrder;
use App\Mail\Traits\HasLogo;
use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Confirms to the customer that an order was delivered (no review request: that would be marketing).
 * Rendered by SendOrderEmail (never queued itself).
 */
class OrderDelivered extends Mailable
{
    use DescribesOrder, HasLogo;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.order_delivered.subject', ['number' => '#'.$this->order->order_number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-delivered',
            text: 'emails.order-delivered-text',
            with: $this->orderContext($this->order) + [
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
