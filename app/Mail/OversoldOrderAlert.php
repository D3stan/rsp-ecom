<?php

namespace App\Mail;

use App\Mail\Traits\DescribesOrder;
use App\Mail\Traits\HasLogo;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Internal alert to the shop owner: a paid order contains units that were not in stock (the order is kept, the
 * customer has paid; the shop has to obtain the missing units or contact the customer). Not customer-facing.
 * Rendered by SendOrderEmail (never queued itself).
 */
class OversoldOrderAlert extends Mailable
{
    use DescribesOrder, HasLogo;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.admin.oversold.subject', ['number' => '#'.$this->order->order_number]),
        );
    }

    public function content(): Content
    {
        $order = $this->loadOrderRelations($this->order);

        return new Content(
            view: 'emails.admin-oversold-order',
            text: 'emails.admin-oversold-order-text',
            with: [
                'order' => $order,
                'lines' => $this->oversoldLines($order),
                'adminUrl' => route('admin.orders.show', $order),
                'logoUrl' => $this->getLogoUrl(),
            ],
        );
    }

    /**
     * @return array<int, array{name: string, detail: string}>
     */
    private function oversoldLines(Order $order): array
    {
        return $order->orderItems
            ->filter(fn (OrderItem $item) => (int) $item->oversold_qty > 0)
            ->map(function (OrderItem $item) {
                $name = (string) $item->product_name;

                if ($item->productVariant && ($item->productVariant->name !== 'Standard' || ! $item->productVariant->is_default)) {
                    $name .= ' ('.$item->productVariant->name.')';
                }

                return [
                    'name' => $name,
                    'detail' => __('emails.admin.oversold.line', [
                        'ordered' => (int) $item->quantity,
                        'missing' => (int) $item->oversold_qty,
                    ]),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return $this->getLogoAttachment();
    }
}
