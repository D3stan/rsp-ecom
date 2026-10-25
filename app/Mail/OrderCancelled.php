<?php

namespace App\Mail;

use App\Mail\Traits\DescribesOrder;
use App\Mail\Traits\HasLogo;
use App\Models\Order;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

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
                'totals' => $this->orderTotals($order, withBreakdown: false),
                'refunded' => $order->payment_status === 'refunded',
                'refundedAmount' => $this->money($order->refunds()->where('status', Refund::STATUS_SUCCEEDED)->sum('amount_cents') / 100, $order->currency),
                'refundDue' => in_array($order->payment_status, Order::REFUND_DUE_PAYMENT_STATUSES, true),
                'amount' => $this->money(app(RefundService::class)->refundableCents($order) / 100, $order->currency),
                'logoUrl' => $this->getLogoUrl(),
            ]),
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
