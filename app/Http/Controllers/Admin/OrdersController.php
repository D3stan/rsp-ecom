<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Shipping\Tracking\TrackingManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrdersController extends Controller
{
    /**
     * Display a listing of orders
     */
    public function index(Request $request): Response
    {
        $query = Order::with(['user', 'orderItems.product', 'orderItems.product.defaultVariant', 'billingAddress', 'shippingAddress'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                               ->orWhere('email', 'like', "%{$search}%");
                  })
                  ->orWhere('guest_email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->paginate(15);

        // Transform the pagination response to ensure proper structure
        $ordersData = [
            'data' => $orders->items(),
            'meta' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ],
            'links' => [],
        ];

        // Calculate KPI data
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();
        $thisYear = now()->startOfYear();

        $kpis = [
            'today' => [
                'orders' => Order::whereDate('created_at', $today)->count(),
                'revenue' => Order::whereDate('created_at', $today)
                    ->where('payment_status', 'succeeded')->sum('total_amount'),
            ],
            'month' => [
                'orders' => Order::whereDate('created_at', '>=', $thisMonth)->count(),
                'revenue' => Order::whereDate('created_at', '>=', $thisMonth)
                    ->where('payment_status', 'succeeded')->sum('total_amount'),
            ],
            'year' => [
                'orders' => Order::whereDate('created_at', '>=', $thisYear)->count(),
                'revenue' => Order::whereDate('created_at', '>=', $thisYear)
                    ->where('payment_status', 'succeeded')->sum('total_amount'),
            ],
        ];

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $ordersData,
            'kpis' => $kpis,
            'filters' => $request->only(['status', 'payment_status', 'search', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Display the specified order
     */
    public function show(Order $order): Response
    {
        $order->load([
            'user',
            'orderItems.product', 'orderItems.product.defaultVariant',
            'billingAddress',
            'shippingAddress',
            'trackingEvents' => fn ($events) => $events->orderByDesc('occurred_at')->limit(20),
        ]);

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order->toFrontendArray(),
            'carriers' => app(TrackingManager::class)->carriers(),
            // Internal stock bookkeeping (hidden from every customer-facing array): units sold beyond the stock
            'stock' => [
                'has_oversold_items' => (bool) $order->has_oversold_items,
                'oversold' => $order->orderItems
                    ->filter(fn ($item) => (int) $item->oversold_qty > 0)
                    ->mapWithKeys(fn ($item) => [$item->id => (int) $item->oversold_qty])
                    ->all(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified order
     */
    public function edit(Order $order): Response
    {
        $order->load([
            'user',
            'orderItems.product', 'orderItems.product.defaultVariant',
            'billingAddress',
            'shippingAddress'
        ]);

        return Inertia::render('Admin/Orders/Edit', [
            'order' => $order->toFrontendArray(),
            'carriers' => app(TrackingManager::class)->carriers(),
        ]);
    }

    /**
     * Update the specified order
     */
    public function update(Request $request, Order $order)
    {
        try {
            Log::info('Order update attempt', [
                'order_id' => $order->id,
                'fields' => $request->keys(),
            ]);

            $request->validate([
                'status' => 'sometimes|string|in:pending,processing,shipped,delivered,cancelled',
                'payment_status' => 'sometimes|string|in:pending,processing,succeeded,failed,cancelled',
                'notes' => 'sometimes|nullable|string|max:1000',
                'tracking_number' => 'sometimes|nullable|string|max:100',
                'tracking_carrier' => ['sometimes', 'nullable', 'string', 'max:50', Rule::in(app(TrackingManager::class)->carrierCodes())],
                'shipping_amount' => 'sometimes|numeric|min:0',
                'tax_amount' => 'sometimes|numeric|min:0',
                'subtotal' => 'sometimes|numeric|min:0',
                'total_amount' => 'sometimes|numeric|min:0',
                'order_items' => 'sometimes|string', // JSON string
                'billing_address' => 'sometimes|nullable|string', // JSON string
                'shipping_address' => 'sometimes|nullable|string', // JSON string
            ]);

            \Log::info('Validation passed, updating order', [
                'order_id' => $order->id
            ]);

            // Get original status and tracking number to detect changes
            $originalStatus = $order->status;
            $originalTrackingNumber = $order->tracking_number;
            $originalCarrier = mb_strtolower(trim((string) $order->getAttribute('tracking_carrier')));

            // Check what was provided in the request
            $newStatus = $request->input('status', $order->status);
            $newTrackingNumber = $request->input('tracking_number', $order->tracking_number);

            // Scenario 2: If tracking number is being added for the first time, auto-change status to shipped
            $trackingNumberAdded = $newTrackingNumber && !$originalTrackingNumber;
            if ($trackingNumberAdded && $newStatus !== 'shipped') {
                $newStatus = 'shipped';
            }

            // Update order with all data (including potentially modified status)
            $order->update([
                'status' => $newStatus,
                'payment_status' => $request->input('payment_status', $order->payment_status),
                'notes' => $request->input('notes', $order->notes),
                'tracking_number' => $newTrackingNumber,
                'tracking_carrier' => $request->input('tracking_carrier', $order->tracking_carrier),
                'shipping_amount' => $request->input('shipping_amount', $order->shipping_amount),
                'tax_amount' => $request->input('tax_amount', $order->tax_amount),
                'subtotal' => $request->input('subtotal', $order->subtotal),
                'total_amount' => $request->input('total_amount', $order->total_amount),
            ]);

            // The stored VAT portion follows the edited total (orders created with a VAT rate snapshot only):
            // an admin who changes shipping or the total must not leave a stale "of which VAT" behind.
            if ($order->tax_rate !== null && $order->wasChanged(['total_amount', 'shipping_amount', 'subtotal'])) {
                $order->update(['tax_amount' => \App\Support\Vat::portion((float) $order->total_amount, (float) $order->tax_rate)]);
            }

            // Send shipping notification email
            $statusChangedToShipped = $originalStatus !== 'shipped' && $newStatus === 'shipped';
            $trackingNumberUpdated = $newTrackingNumber && trim((string) $newTrackingNumber) !== trim((string) $originalTrackingNumber);
            $carrierChanged = mb_strtolower(trim((string) $order->getAttribute('tracking_carrier'))) !== $originalCarrier;

            Log::info('Checking shipping email conditions', [
                'order_id' => $order->id,
                'original_status' => $originalStatus,
                'new_status' => $newStatus,
                'original_tracking' => $originalTrackingNumber,
                'new_tracking' => $newTrackingNumber,
                'status_changed_to_shipped' => $statusChangedToShipped,
                'tracking_number_updated' => $trackingNumberUpdated,
                'tracking_carrier_changed' => $carrierChanged,
                'tracking_number_added' => $trackingNumberAdded,
            ]);

            // The shipped email is only for orders that are (still) shipped. EmailService dedupes on
            // status + tracking number + carrier, so repeated saves never re-send; only a changed number or carrier does.
            $emailWarning = null;

            if (($statusChangedToShipped || $trackingNumberUpdated || $carrierChanged) && $newStatus === 'shipped') {
                $emailService = app(\App\Services\EmailService::class);

                if ($emailService->isEnabled(\App\Models\OrderEmail::TYPE_SHIPPED)) {
                    $emailQueued = $emailService->sendOrderShipped($order, $newTrackingNumber ?: null);

                    Log::info('Shipping email result', [
                        'order_id' => $order->id,
                        'email_queued' => $emailQueued,
                    ]);

                    if (! $emailQueued) {
                        $emailWarning = 'The order was updated, but the shipping email could not be queued. Please check the application logs.';
                    }
                } else {
                    Log::info('Shipping email disabled in settings', ['order_id' => $order->id]);
                }
            } else {
                Log::info('Shipping email conditions not met', [
                    'order_id' => $order->id,
                    'condition_check' => 'skipped',
                ]);
            }

            // Update order items if provided
        if ($request->has('order_items')) {
            $orderItems = json_decode($request->order_items, true);
            if (is_array($orderItems)) {
                foreach ($orderItems as $itemData) {
                    $orderItem = OrderItem::find($itemData['id']);
                    if ($orderItem && $orderItem->order_id === $order->id) {
                        $orderItem->update([
                            'quantity' => $itemData['quantity'],
                            'price' => $itemData['price'],
                            'total' => $itemData['total'],
                        ]);
                    }
                }
            }
        }

        // Update addresses if provided
        if ($request->has('billing_address') && $request->billing_address) {
            $billingAddress = json_decode($request->billing_address, true);
            if (is_array($billingAddress) && $order->billingAddress) {
                $order->billingAddress->update($billingAddress);
            }
        }

        if ($request->has('shipping_address') && $request->shipping_address) {
            $shippingAddress = json_decode($request->shipping_address, true);
            if (is_array($shippingAddress) && $order->shippingAddress) {
                $order->shippingAddress->update($shippingAddress);
            }
        }

        Log::info('Order updated successfully', ['order_id' => $order->id]);
        $response = back()->with('success', 'Order updated successfully.');

        return $emailWarning ? $response->with('warning', $emailWarning) : $response;
        
        } catch (\Exception $e) {
            Log::error('Failed to update order', [
                'order_id' => $order->id ?? 'unknown',
                'exception' => get_class($e),
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Failed to update order: ' . $e->getMessage());
        }
    }

    /**
     * Update order status
     */
    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|string|in:pending,processing,shipped,delivered,cancelled',
        ]);

        $wasShipped = $order->status === 'shipped';

        $order->update(['status' => $request->status]);

        $response = back()->with('success', 'Order status updated successfully.');

        // Same deduped shipped-email path as update()/processShip()
        if ($request->status === 'shipped' && ! $wasShipped) {
            $emailService = app(\App\Services\EmailService::class);

            if ($emailService->isEnabled(\App\Models\OrderEmail::TYPE_SHIPPED)
                && ! $emailService->sendOrderShipped($order, $order->tracking_number)) {
                $response->with('warning', 'The status was updated, but the shipping email could not be queued. Please check the application logs.');
            }
        }

        return $response;
    }

    /**
     * Bulk update order statuses
     */
    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'exists:orders,id',
            'status' => 'required|string|in:pending,processing,shipped,delivered,cancelled',
        ]);

        $emailService = app(\App\Services\EmailService::class);
        $emailFailures = 0;

        // Per model (not a bulk query) so model events keep status/payment_status consistent
        Order::with('user')->whereIn('id', $request->order_ids)->get()
            ->each(function (Order $order) use ($request, $emailService, &$emailFailures) {
                $wasShipped = $order->status === 'shipped';

                $order->update(['status' => $request->status]);

                // Same deduped path as update()/processShip(): no tracking number -> fingerprint without tracking
                if ($request->status === 'shipped' && ! $wasShipped
                    && $emailService->isEnabled(\App\Models\OrderEmail::TYPE_SHIPPED)
                    && ! $emailService->sendOrderShipped($order, $order->tracking_number)) {
                    $emailFailures++;
                }
            });

        $count = count($request->order_ids);
        $response = back()->with('success', "{$count} orders updated successfully.");

        return $emailFailures > 0
            ? $response->with('warning', "{$emailFailures} shipping email(s) could not be queued. Please check the application logs.")
            : $response;
    }

    /**
     * Cancel an order
     */
    public function cancel(Order $order)
    {
        if (!$order->can_be_cancelled) {
            return back()->withErrors(['error' => 'This order cannot be cancelled.']);
        }

        $order->cancel();

        return back()->with('success', 'Order cancelled successfully.');
    }

    /**
     * Refund an order (placeholder for Stripe integration)
     */
    public function refund(Request $request, Order $order)
    {
        $request->validate([
            'amount' => 'sometimes|numeric|min:0.01|max:' . $order->total_amount,
            'reason' => 'sometimes|string|max:500',
        ]);

        // TODO: Implement Stripe refund logic here, then call EmailService::sendOrderRefunded($order, $refundAmount).
        //       Until money really moves the customer must NOT be emailed (no refund email from this placeholder).
        // For now, just mark as refunded in notes
        $refundAmount = $request->amount ?? $order->total_amount;
        $reason = $request->reason ?? 'Admin refund';
        
        $order->update([
            'notes' => ($order->notes ? $order->notes . "\n\n" : '') . 
                      "REFUND: $" . number_format($refundAmount, 2) . " - {$reason} (Processed on " . now()->format('Y-m-d H:i:s') . ")"
        ]);

        return back()->with('success', 'Refund processed successfully.');
    }

    /**
     * Show the shipping form for an order
     */
    public function showShip(Order $order): Response
    {
        $order->load([
            'user',
            'orderItems.product', 'orderItems.product.defaultVariant',
            'billingAddress',
            'shippingAddress'
        ]);

        // Get admin settings for shipping
        $adminSettings = [
            'company_name' => settings('store.name', ''),
            'company_address' => settings('store.address', ''),
            'company_phone' => settings('contact.phone', ''),
            'company_email' => settings('contact.support_email', ''),
            'iban' => settings('store.company_iban', ''),
            'account_holder' => settings('store.account_holder', ''),
        ];

        // Get available sizes for shipping box information
        $sizes = \App\Models\Size::select('id', 'name', 'length', 'width', 'height', 'box_type', 'shipping_cost')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Orders/Ship', [
            'order' => $order->toFrontendArray(),
            'adminSettings' => $adminSettings,
            'sizes' => $sizes,
            'carriers' => app(TrackingManager::class)->carriers(),
        ]);
    }

    /**
     * Process the shipping for an order
     */
    public function processShip(Request $request, Order $order)
    {
        $request->validate([
            'sender_info.company_name' => 'required|string|max:255',
            'sender_info.address' => 'required|string|max:1000',
            'sender_info.phone' => 'required|string|max:50',
            'sender_info.email' => 'required|email|max:255',
            'customer_info.name' => 'required|string|max:255',
            'customer_info.email' => 'required|email|max:255',
            'customer_info.phone' => 'nullable|string|max:50',
            'shipping_address.first_name' => 'required|string|max:255',
            'shipping_address.last_name' => 'required|string|max:255',
            'shipping_address.company' => 'nullable|string|max:255',
            'shipping_address.address_line_1' => 'required|string|max:255',
            'shipping_address.address_line_2' => 'nullable|string|max:255',
            'shipping_address.city' => 'required|string|max:255',
            'shipping_address.state' => 'required|string|max:255',
            'shipping_address.postal_code' => 'required|string|max:20',
            'shipping_address.country' => 'required|string|max:255',
            'shipping_address.phone' => 'nullable|string|max:50',
            'box_info.size_id' => 'nullable|exists:sizes,id',
            'box_info.weight' => 'nullable|numeric|min:0',
            'box_info.carrier' => 'nullable|string|max:100',
            'box_info.service_type' => 'nullable|string|max:100',
            'tracking_carrier' => ['nullable', 'string', 'max:50', Rule::in(app(TrackingManager::class)->carrierCodes())],
            'additional_settings.insurance_enabled' => 'boolean',
            'additional_settings.insurance_value' => 'nullable|numeric|min:0',
            'additional_settings.contrassegno_enabled' => 'boolean',
            'additional_settings.contrassegno_amount' => 'nullable|numeric|min:0',
            'additional_settings.iban' => 'nullable|string|max:50',
            'additional_settings.account_holder' => 'nullable|string|max:255',
            'additional_settings.notes' => 'nullable|string|max:1000',
        ]);

        try {
            // Update order with shipping information
            $updateData = [
                'status' => 'shipped',
                'notes' => $this->buildShippingNotes($order, $request->all()),
            ];

            // Carrier chosen from config('shipping.carriers'); drives the tracking link and automatic tracking
            if ($request->has('tracking_carrier')) {
                $updateData['tracking_carrier'] = $request->input('tracking_carrier');
            }

            $order->update($updateData);

            // Queue the shipping notification (deduped per status + tracking number)
            $emailService = app(\App\Services\EmailService::class);
            $emailQueued = ! $emailService->isEnabled(\App\Models\OrderEmail::TYPE_SHIPPED)
                || $emailService->sendOrderShipped($order, $order->tracking_number);

            Log::info('Order shipped successfully', [
                'order_id' => $order->id,
                'carrier' => $request->input('box_info.carrier'),
            ]);

            $response = redirect()->route('admin.orders.show', $order)
                ->with('success', 'Order has been shipped successfully!');

            return $emailQueued
                ? $response
                : $response->with('warning', 'The order was marked as shipped, but the shipping email could not be queued. Please check the application logs.');

        } catch (\Exception $e) {
            Log::error('Failed to process order shipping', [
                'order_id' => $order->id,
                'exception' => get_class($e),
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to process shipping: ' . $e->getMessage());
        }
    }

    /**
     * Build shipping notes from the form data
     */
    private function buildShippingNotes(Order $order, array $shippingData): string
    {
        $notes = $order->notes ? $order->notes . "\n\n" : '';
        
        $notes .= "=== SHIPPING INFORMATION ===\n";
        $notes .= "Shipped on: " . now()->format('Y-m-d H:i:s') . "\n";
        
        // Sender information
        $senderInfo = $shippingData['sender_info'] ?? [];
        if (!empty($senderInfo)) {
            $notes .= "\nSender:\n";
            $notes .= "- Company: " . ($senderInfo['company_name'] ?? 'N/A') . "\n";
            $notes .= "- Address: " . ($senderInfo['address'] ?? 'N/A') . "\n";
            $notes .= "- Phone: " . ($senderInfo['phone'] ?? 'N/A') . "\n";
            $notes .= "- Email: " . ($senderInfo['email'] ?? 'N/A') . "\n";
        }
        
        // Customer information
        $customerInfo = $shippingData['customer_info'] ?? [];
        if (!empty($customerInfo)) {
            $notes .= "\nCustomer:\n";
            $notes .= "- Name: " . ($customerInfo['name'] ?? 'N/A') . "\n";
            $notes .= "- Email: " . ($customerInfo['email'] ?? 'N/A') . "\n";
            $notes .= "- Phone: " . ($customerInfo['phone'] ?? 'N/A') . "\n";
        }
        
        // Shipping address
        $shippingAddress = $shippingData['shipping_address'] ?? [];
        if (!empty($shippingAddress)) {
            $notes .= "\nShipping Address:\n";
            $notes .= "- Name: " . ($shippingAddress['first_name'] ?? '') . " " . ($shippingAddress['last_name'] ?? '') . "\n";
            if (!empty($shippingAddress['company'])) $notes .= "- Company: " . $shippingAddress['company'] . "\n";
            $notes .= "- Address: " . ($shippingAddress['address_line_1'] ?? 'N/A') . "\n";
            if (!empty($shippingAddress['address_line_2'])) $notes .= "- Address 2: " . $shippingAddress['address_line_2'] . "\n";
            $notes .= "- City: " . ($shippingAddress['city'] ?? 'N/A') . "\n";
            $notes .= "- State: " . ($shippingAddress['state'] ?? 'N/A') . "\n";
            $notes .= "- Postal Code: " . ($shippingAddress['postal_code'] ?? 'N/A') . "\n";
            $notes .= "- Country: " . ($shippingAddress['country'] ?? 'N/A') . "\n";
            if (!empty($shippingAddress['phone'])) $notes .= "- Phone: " . $shippingAddress['phone'] . "\n";
        }
        
        // Box information
        $boxInfo = $shippingData['box_info'] ?? [];
        if (!empty($boxInfo)) {
            $notes .= "\nPackage Details:\n";
            if (!empty($boxInfo['size_id'])) {
                $size = \App\Models\Size::find($boxInfo['size_id']);
                if ($size) {
                    $notes .= "- Size: " . $size->name . " (" . $size->length . "x" . $size->width . "x" . $size->height . " cm)\n";
                    $notes .= "- Box Type: " . ucfirst(str_replace('_', ' ', $size->box_type)) . "\n";
                }
            }
            if (!empty($boxInfo['weight'])) $notes .= "- Weight: " . $boxInfo['weight'] . " kg\n";
            if (!empty($boxInfo['carrier'])) $notes .= "- Carrier: " . $boxInfo['carrier'] . "\n";
            if (!empty($boxInfo['service_type'])) $notes .= "- Service Type: " . $boxInfo['service_type'] . "\n";
        }
        
        // Additional settings
        $additionalSettings = $shippingData['additional_settings'] ?? [];
        if (!empty($additionalSettings)) {
            if (!empty($additionalSettings['insurance_enabled']) && !empty($additionalSettings['insurance_value'])) {
                $notes .= "\nInsurance: €" . number_format($additionalSettings['insurance_value'], 2) . "\n";
            }
            
            if (!empty($additionalSettings['contrassegno_enabled'])) {
                $notes .= "\nContrassegno (COD): Enabled\n";
                if (!empty($additionalSettings['contrassegno_amount'])) {
                    $notes .= "- Amount: €" . number_format($additionalSettings['contrassegno_amount'], 2) . "\n";
                }
                if (!empty($additionalSettings['iban'])) {
                    $notes .= "- IBAN: " . $additionalSettings['iban'] . "\n";
                }
                if (!empty($additionalSettings['account_holder'])) {
                    $notes .= "- Account Holder: " . $additionalSettings['account_holder'] . "\n";
                }
            }
            
            if (!empty($additionalSettings['notes'])) {
                $notes .= "\nAdditional Notes: " . $additionalSettings['notes'] . "\n";
            }
        }
        
        return $notes;
    }
}
