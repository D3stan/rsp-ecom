<?php

namespace App\Mail;

use App\Mail\Traits\DescribesOrder;
use App\Mail\Traits\HasLogo;
use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderShipped extends Mailable
{
    use DescribesOrder, HasLogo;

    public $order;

    public $trackingNumber;

    /**
     * Create a new message instance.
     */
    public function __construct(Order $order, $trackingNumber = null)
    {
        $this->order = $order;
        $this->trackingNumber = $trackingNumber;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.order_shipped.subject', ['number' => '#'.$this->order->order_number]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $trackingNumber = $this->trackingNumber !== null && trim((string) $this->trackingNumber) !== ''
            ? trim((string) $this->trackingNumber)
            : null;

        return new Content(
            view: 'emails.order-shipped',
            text: 'emails.order-shipped-text',
            with: $this->orderContext($this->order) + [
                'trackingNumber' => $trackingNumber,
                'carrierName' => $trackingNumber ? $this->order->trackingCarrierName() : null,
                // A link only when the carrier's tracking page is known; otherwise the number is shown alone
                'trackingUrl' => $trackingNumber ? $this->order->trackingUrl($trackingNumber) : null,
                'delivery' => $this->deliveryEstimate(),
                'logoUrl' => $this->getLogoUrl(),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return $this->getLogoAttachment();
    }
}
