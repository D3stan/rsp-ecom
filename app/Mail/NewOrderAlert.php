<?php

namespace App\Mail;

use App\Mail\Traits\DescribesOrder;
use App\Mail\Traits\HasLogo;
use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewOrderAlert extends Mailable
{
    use DescribesOrder, HasLogo;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.admin.new_order.subject', [
                'number' => '#'.$this->order->order_number,
                'total' => $this->money($this->order->total_amount, $this->order->currency),
            ]),
        );
    }

    public function content(): Content
    {
        $order = $this->loadOrderRelations($this->order);
        $billing = $order->billingAddress;
        $fullName = trim((string) $order->user?->name)
            ?: trim($billing?->first_name.' '.$billing?->last_name);

        return new Content(
            view: 'emails.admin-new-order',
            text: 'emails.admin-new-order-text',
            with: [
                'order' => $order,
                'customerName' => $fullName !== '' ? $fullName : null,
                'customerEmail' => $order->user?->email ?: $order->guest_email,
                'customerPhone' => $order->guest_phone ?: $order->shippingAddress?->phone,
                'isGuest' => $order->user_id === null,
                'items' => $this->orderLines($order),
                'totals' => $this->orderTotals($order),
                'totalFormatted' => $this->money($order->total_amount, $order->currency),
                'addressLines' => $this->addressLines($order->shippingAddress),
                'adminUrl' => route('admin.orders.show', $order),
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
