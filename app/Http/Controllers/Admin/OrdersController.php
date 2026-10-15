<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Orders\OrderFinancialEdit;
use App\Exceptions\InvalidOrderTransition;
use App\Exceptions\OrderEditRefused;
use App\Exceptions\RefundFailed;
use App\Exceptions\RefundRefused;
use App\Exceptions\RefundUnconfirmed;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Refund;
use App\Services\RefundService;
use App\Support\Revenue;
use App\Shipping\Tracking\TrackingManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrdersController extends Controller
{
    /** Stripe may have refunded or not (timeout, 5xx): the refund stays pending and is settled automatically. */
    private const REFUND_UNCONFIRMED_MESSAGE = 'Stripe did not confirm the refund yet; it will be updated automatically. Do not retry with a new refund: '
        .'if it stays pending, use "Retry" on it (it resends the same request, so it cannot be paid twice).';

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

        // Cancelled while money is still captured: customer service owes a refund
        if ($request->boolean('refund_due')) {
            $query->refundDue();
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
            'data' => collect($orders->items())
                ->map(fn (Order $order) => $order->toArray() + ['allowed_transitions' => $order->allowedTransitions()])
                ->all(),
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

        // KPI data; revenue is net (cancelled orders excluded, succeeded refunds subtracted)
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();
        $thisYear = now()->startOfYear();

        $kpis = [
            'today' => [
                'orders' => Order::whereDate('created_at', $today)->count(),
                'revenue' => Revenue::net(Order::whereDate('created_at', $today)),
            ],
            'month' => [
                'orders' => Order::whereDate('created_at', '>=', $thisMonth)->count(),
                'revenue' => Revenue::net(Order::whereDate('created_at', '>=', $thisMonth)),
            ],
            'year' => [
                'orders' => Order::whereDate('created_at', '>=', $thisYear)->count(),
                'revenue' => Revenue::net(Order::whereDate('created_at', '>=', $thisYear)),
            ],
        ];

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $ordersData,
            'kpis' => $kpis,
            'filters' => $request->only(['status', 'payment_status', 'refund_due', 'search', 'date_from', 'date_to']),
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
            'allowed_transitions' => $order->allowedTransitions(),
            // cancelled while money is still captured (computed)
            'refund_due' => $order->isRefundDue(),
            // Audit trail for the timeline, oldest first (empty for orders from before it existed)
            'events' => $order->events()->with('actor:id,name')->get()->map->toTimelineArray()->values()->all(),
            // Money returned so far (newest first) and what can still be refunded through Stripe
            'refunds' => $order->refunds()->with('creator:id,name')->orderByDesc('id')->get()->map->toAdminArray()->values()->all(),
            'refund' => [
                'refundable_cents' => app(RefundService::class)->refundableCents($order),
                'has_payment_intent' => ! empty($order->stripe_payment_intent_id),
                // units each line can still put back in stock (empty when the stock was never taken or already returned)
                'restockable' => $order->stock_deducted_at !== null && $order->stock_restored_at === null
                    ? $order->orderItems->mapWithKeys(fn ($item) => [$item->id => max(0, (int) $item->quantity - (int) $item->oversold_qty - (int) $item->restocked_qty)])->all()
                    : (object) [],
            ],
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
            'allowed_transitions' => $order->allowedTransitions(),
            // paid or payment in flight: lines, prices and totals are read-only (money goes back through a refund)
            'financials_locked' => $order->financialsLocked(),
            'carriers' => app(TrackingManager::class)->carriers(),
        ]);
    }

    /**
     * Update the specified order. Always editable: notes, tracking, addresses, and the status (through the order
     * lifecycle). Money (lines, shipping, totals) only while the order is unpaid, and the payment status never:
     * OrderFinancialEdit decides. Everything is written in one transaction, or nothing is.
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
                'reason' => 'sometimes|nullable|string|max:500',
                'payment_status' => 'sometimes|string',
                'notes' => 'sometimes|nullable|string|max:1000',
                'tracking_number' => 'sometimes|nullable|string|max:100',
                'tracking_carrier' => ['sometimes', 'nullable', 'string', 'max:50', Rule::in(app(TrackingManager::class)->carrierCodes())],
                'shipping_amount' => 'sometimes|numeric|min:0',
                'tax_amount' => 'sometimes|numeric|min:0',
                'subtotal' => 'sometimes|numeric|min:0',
                'total_amount' => 'sometimes|numeric|min:0',
                'discount_amount' => 'sometimes|numeric|min:0',
                'order_items' => 'sometimes|string', // JSON string
                'billing_address' => 'sometimes|nullable|string', // JSON string
                'shipping_address' => 'sometimes|nullable|string', // JSON string
            ]);

            // Refused (paid order, payment status, invalid lines) before anything is written
            $financial = OrderFinancialEdit::plan($order, $request->only([
                'payment_status', 'order_items', 'subtotal', 'shipping_amount', 'tax_amount', 'discount_amount', 'total_amount',
            ]));

            // Get original status and tracking number to detect changes
            $originalStatus = $order->status;
            $originalTrackingNumber = $order->tracking_number;
            $originalCarrier = mb_strtolower(trim((string) $order->getAttribute('tracking_carrier')));

            // Check what was provided in the request
            $newStatus = $request->input('status', $order->status);
            $newTrackingNumber = $request->input('tracking_number', $order->tracking_number);

            // Scenario 2: a tracking number added for the first time ships the order, when the admin did not ask for
            // another status and the order may ship now (OrderTransitions)
            $trackingNumberAdded = $newTrackingNumber && ! $originalTrackingNumber;
            if ($trackingNumberAdded && $newStatus === $originalStatus && in_array('shipped', $order->allowedTransitions(), true)) {
                $newStatus = 'shipped';
            }

            DB::transaction(function () use ($request, $order, $financial, $newStatus, $newTrackingNumber, $originalStatus) {
                $order->fill([
                    'notes' => $request->input('notes', $order->notes),
                    'tracking_number' => $newTrackingNumber,
                    'tracking_carrier' => $request->input('tracking_carrier', $order->tracking_carrier),
                ]);

                if ($financial !== null) {
                    OrderFinancialEdit::apply($order, $financial);
                }

                // One save: through transitionTo() when the status changes (a refused move rolls everything back)
                if ($newStatus !== $originalStatus) {
                    $order->transitionTo($newStatus, $request->user(), $request->input('reason'));
                } else {
                    $order->save();
                }

                foreach (['billing_address' => $order->billingAddress, 'shipping_address' => $order->shippingAddress] as $field => $address) {
                    $data = $request->filled($field) ? json_decode((string) $request->input($field), true) : null;

                    if (is_array($data) && $address) {
                        $address->update($data);
                    }
                }
            });

            $newStatus = $order->status;

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

            Log::info('Order updated successfully', ['order_id' => $order->id]);
            $response = back()->with('success', 'Order updated successfully.');

            return $emailWarning ? $response->with('warning', $emailWarning) : $response;
        } catch (OrderEditRefused $e) {
            return back()->withErrors(['order' => $e->getMessage()])->with('error', $e->getMessage());
        } catch (InvalidOrderTransition $e) {
            return $this->refusedTransition($e);
        } catch (\Exception $e) {
            Log::error('Failed to update order', [
                'order_id' => $order->id ?? 'unknown',
                'exception' => get_class($e),
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to update order: '.$e->getMessage());
        }
    }

    /**
     * Update order status
     */
    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|string|in:pending,processing,shipped,delivered,cancelled',
            'reason' => 'nullable|string|max:500',
        ]);

        $wasShipped = $order->status === 'shipped';

        try {
            $order->transitionTo($request->status, $request->user(), $request->input('reason'));
        } catch (InvalidOrderTransition $e) {
            return $this->refusedTransition($e);
        }

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
        $updated = 0;
        $skipped = [];

        // Per model (not a bulk query) so model events keep status/payment_status consistent. Each order moves only if
        // the lifecycle allows it; the others are reported back and left untouched.
        Order::with('user')->whereIn('id', $request->order_ids)->orderBy('id')->get()
            ->each(function (Order $order) use ($request, $emailService, &$emailFailures, &$updated, &$skipped) {
                $from = $order->status;

                if ($from === $request->status) {
                    $skipped[] = "{$order->order_number} (already {$from})";

                    return;
                }

                try {
                    $order->transitionTo($request->status, $request->user());
                } catch (InvalidOrderTransition) {
                    $skipped[] = "{$order->order_number} ({$from} → {$request->status} not allowed)";

                    return;
                }

                $updated++;

                // Same deduped path as update()/processShip(): no tracking number -> fingerprint without tracking
                if ($request->status === 'shipped'
                    && $emailService->isEnabled(\App\Models\OrderEmail::TYPE_SHIPPED)
                    && ! $emailService->sendOrderShipped($order, $order->tracking_number)) {
                    $emailFailures++;
                }
            });

        $summary = "{$updated} updated, ".count($skipped).' skipped'.($skipped ? ': '.implode(', ', $skipped) : '').'.';
        $response = back()->with($updated > 0 ? 'success' : 'error', $summary);

        return $emailFailures > 0
            ? $response->with('warning', "{$emailFailures} shipping email(s) could not be queued. Please check the application logs.")
            : $response;
    }

    /**
     * Cancel an order
     */
    public function cancel(Request $request, Order $order, RefundService $refunds)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
            // paid orders: "now" refunds everything still refundable first, "later" leaves the order as "Refund due"
            'refund' => 'nullable|in:now,later',
        ]);

        if (! $order->can_be_cancelled) {
            return back()->with('error', "Order {$order->order_number} cannot be cancelled from \"{$order->status}\".");
        }

        $warning = null;
        $refundable = $refunds->refundableCents($order);

        if ($request->input('refund') === 'now' && $refundable > 0) {
            try {
                $refunds->refund($order, $refundable, $request->input('reason') ?: 'Order cancelled', $request->user());
            } catch (RefundRefused|RefundFailed $e) {
                // Not cancelled either: the admin can retry, or cancel and refund later
                return back()->with('error', 'The order was not cancelled: the refund failed. '.$e->getMessage());
            } catch (RefundUnconfirmed) {
                $warning = self::REFUND_UNCONFIRMED_MESSAGE;
            }
        }

        if (! $order->fresh()->cancel($request->user(), $request->input('reason'))) {
            return back()->with('error', "Order {$order->order_number} cannot be cancelled from \"{$order->status}\".");
        }

        $response = back()->with('success', 'Order cancelled successfully.');

        return $warning ? $response->with('warning', $warning) : $response;
    }

    /**
     * Refund money through Stripe (RefundService): amount in EUR from the form, converted to cents; optional reason and
     * units per line to put back in stock. Refused refunds change nothing; a Stripe failure is recorded on the order.
     */
    public function refund(Request $request, Order $order, RefundService $refunds)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:1000000',
            'reason' => 'nullable|string|max:500',
            'restock' => 'sometimes|array',
            'restock.*' => 'integer|min:0',
        ]);

        try {
            $refund = $refunds->refund(
                $order,
                (int) round((float) $data['amount'] * 100),
                $data['reason'] ?? null,
                $request->user(),
                Refund::SOURCE_ADMIN,
                array_filter($data['restock'] ?? [], fn ($quantity) => (int) $quantity > 0),
            );
        } catch (RefundRefused $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->with('error', $e->getMessage());
        } catch (RefundFailed $e) {
            return back()->with('error', 'Stripe did not execute the refund: '.$e->getMessage());
        } catch (RefundUnconfirmed) {
            return back()->with('warning', self::REFUND_UNCONFIRMED_MESSAGE);
        }

        return back()->with('success', sprintf('Refund of %s %s issued.', number_format($refund->amount(), 2, ',', '.'), $refund->currency));
    }

    /**
     * Settle a refund Stripe did not confirm (still pending): looks it up at Stripe, else re-sends it under the same
     * idempotency key, so it can never be paid twice.
     */
    public function retryRefund(Request $request, Order $order, Refund $refund, RefundService $refunds)
    {
        try {
            $refund = $refunds->retry($refund, $request->user());
        } catch (RefundRefused $e) {
            return back()->with('error', $e->getMessage());
        } catch (RefundFailed $e) {
            return back()->with('error', 'Stripe did not execute the refund: '.$e->getMessage());
        } catch (RefundUnconfirmed) {
            return back()->with('warning', self::REFUND_UNCONFIRMED_MESSAGE);
        }

        return back()->with('success', sprintf('Refund of %s %s confirmed by Stripe.', number_format($refund->amount(), 2, ',', '.'), $refund->currency));
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
            // Shipping information, saved together with the move to "shipped" (refused unless the order may ship)
            $updateData = [
                'notes' => $this->buildShippingNotes($order, $request->all()),
            ];

            // Carrier chosen from config('shipping.carriers'); drives the tracking link and automatic tracking
            if ($request->has('tracking_carrier')) {
                $updateData['tracking_carrier'] = $request->input('tracking_carrier');
            }

            $order->fill($updateData)->transitionTo('shipped', $request->user());

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

        } catch (InvalidOrderTransition $e) {
            return $this->refusedTransition($e);
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
     * A status change the order lifecycle refused: nothing was written. The message goes to the toast (flash) and to
     * the status field of the form that sent it.
     */
    private function refusedTransition(InvalidOrderTransition $e)
    {
        return back()->withErrors(['status' => $e->getMessage()])->with('error', $e->getMessage());
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
