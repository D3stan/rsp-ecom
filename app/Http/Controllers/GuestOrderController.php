<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Artesaos\SEOTools\Facades\SEOMeta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Order-status page reached through the signed link in the customer emails (Order::publicUrl()).
 * No account or cookie is needed: the signature is the proof. Anything else gets the same friendly page.
 */
class GuestOrderController extends Controller
{
    public function show(Request $request, string $order): Response
    {
        // The URL carries a signature: keep it out of search engines, referrers and caches
        SEOMeta::addMeta('robots', 'noindex,nofollow', 'name');

        if (! $request->hasValidSignature()) {
            return $this->invalid($request);
        }

        $model = Order::where('order_number', $order)
            ->with(['orderItems.product.defaultVariant', 'orderItems.size', 'shippingAddress'])
            ->first();

        // Same answer as a bad signature, so the page never confirms whether an order number exists
        if (! $model) {
            return $this->invalid($request);
        }

        $address = $model->shippingAddress;

        return $this->respond($request, 'Orders/GuestShow', [
            'order' => [
                'order_number' => $model->order_number,
                'status' => $model->status,
                'payment_status' => $model->payment_status,
                'created_at' => $model->created_at?->toISOString(),
                'tracking_number' => $model->tracking_number,
                'currency' => $model->currency,
                'subtotal' => (float) $model->subtotal,
                'shipping_amount' => (float) $model->shipping_amount,
                'discount_amount' => (float) $model->discount_amount,
                'total_amount' => (float) $model->total_amount,
                'items' => $model->orderItems->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->product_name ?: $item->product?->name,
                    'size' => $item->size?->name,
                    'quantity' => $item->quantity,
                    'price' => (float) $item->price,
                    'total' => (float) $item->total,
                    'image_url' => $item->product_id ? $item->product?->image_url : null,
                ])->values()->all(),
                'shipping_address' => $address ? [
                    'name' => trim($address->first_name.' '.$address->last_name),
                    'line1' => $address->address_line_1,
                    'line2' => $address->address_line_2,
                    'city' => $address->city,
                    'state' => $address->state,
                    'postal_code' => $address->postal_code,
                    'country' => $address->country,
                ] : null,
            ],
            'supportEmail' => config('mail.support_address'),
        ]);
    }

    private function invalid(Request $request): Response
    {
        return $this->respond($request, 'Orders/GuestInvalid', [
            'supportEmail' => config('mail.support_address'),
        ])->setStatusCode(403);
    }

    private function respond(Request $request, string $component, array $props): Response
    {
        return Inertia::render($component, $props)
            ->toResponse($request)
            ->withHeaders([
                'Cache-Control' => 'no-store, private',
                'Referrer-Policy' => 'no-referrer',
            ]);
    }
}
