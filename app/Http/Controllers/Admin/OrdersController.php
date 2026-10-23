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
use App\Models\OrderEmail;
use App\Models\Refund;
use App\Models\Shipment;
use App\Services\EmailService;
use App\Services\RefundService;
use App\Shipping\Labels\Exceptions\LabelProviderUnavailable;
use App\Shipping\Labels\Exceptions\LabelRejected;
use App\Shipping\Labels\ShipmentBuilder;
use App\Shipping\ShipInput;
use App\Shipping\ShippingService;
use App\Shipping\Tracking\TrackingManager;
use App\Support\Revenue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrdersController extends Controller
{
    /** Stripe may have refunded or not (timeout, 5xx): the refund stays pending and is settled automatically. */
    /** "shipped" needs the carrier, the tracking number and the single shipped email: only the ship form does that. */
    private const SHIP_FORM_ONLY_MESSAGE = 'Use the ship form to ship an order: it records the carrier and tracking number and notifies the customer.';

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

        // withQueryString(): the page links keep the active filters
        $orders = $query->paginate(15)->withQueryString();

        // Transform the pagination response to ensure proper structure
        $ordersData = [
            'data' => collect($orders->items())
                ->map(fn (Order $order) => $order->toArray() + [
                    'allowed_transitions' => $order->allowedTransitions(),
                    'can_ship' => $order->canShip(),
                ])
                ->all(),
            'meta' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ],
            'links' => $orders->linkCollection()->toArray(),
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
            // processing and paid (Order::canShip): the "Ship" action is offered only then
            'can_ship' => $order->canShip(),
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
            // Label shipments (newest first): "Download label" and "Cancel shipment" on the order page
            'shipments' => $order->shipments()->whereNotNull('label_path')->reorder()->latest('id')->get()
                ->map(fn (Shipment $shipment) => [
                    'id' => $shipment->id,
                    'status' => $shipment->status,
                    'carrier_code' => $shipment->carrier_code,
                    'driver' => $shipment->driver,
                    'tracking_number' => $shipment->tracking_number,
                    'can_cancel' => $shipment->status === Shipment::STATUS_CREATED && $this->driverCancels($shipment),
                ])->values()->all(),
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
            'shippingAddress',
        ]);

        return Inertia::render('Admin/Orders/Edit', [
            'order' => $order->toFrontendArray(),
            'allowed_transitions' => $order->allowedTransitions(),
            // paid or payment in flight: lines, prices and totals are read-only (money goes back through a refund)
            'financials_locked' => $order->financialsLocked(),
            'carriers' => app(TrackingManager::class)->carriers(),
            'tracking_drivers' => app(TrackingManager::class)->selectableDriverNames(),
        ]);
    }

    /**
     * Update the specified order. Always editable: notes, tracking, addresses, payment status, and the status
     * (through the order lifecycle). Money (lines, shipping, totals) only while the order is unpaid:
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
                'payment_status' => ['sometimes', 'string', Rule::in(Order::PAYMENT_STATUSES)],
                'notes' => 'sometimes|nullable|string|max:1000',
                'tracking_number' => 'sometimes|nullable|string|max:100',
                'tracking_carrier' => ['sometimes', 'nullable', 'string', 'max:50', Rule::in(app(TrackingManager::class)->carrierCodes())],
                'tracking_driver' => ['sometimes', 'nullable', 'string', 'max:50', Rule::in(app(TrackingManager::class)->driverNames())],
                'shipping_amount' => 'sometimes|numeric|min:0',
                'tax_amount' => 'sometimes|numeric|min:0',
                'subtotal' => 'sometimes|numeric|min:0',
                'total_amount' => 'sometimes|numeric|min:0',
                'discount_amount' => 'sometimes|numeric|min:0',
                'order_items' => 'sometimes|string', // JSON string
                'billing_address' => 'sometimes|nullable|string', // JSON string
                'shipping_address' => 'sometimes|nullable|string', // JSON string
            ]);

            // Shipping goes through the ship form only (ShippingService); keeping "shipped" on a shipped order is fine
            if ($request->input('status') === 'shipped' && $order->status !== 'shipped') {
                return $this->shipFormOnly();
            }

            // Refused (paid order, invalid lines) before anything is written
            $financial = OrderFinancialEdit::plan($order, $request->only([
                'payment_status', 'order_items', 'subtotal', 'shipping_amount', 'tax_amount', 'discount_amount', 'total_amount',
            ]));

            // Original status and tracking to detect changes
            $originalStatus = $order->status;
            $originalTracking = $this->trackingKey($order);

            $newStatus = $request->input('status', $order->status);
            $newTrackingNumber = $request->input('tracking_number', $order->tracking_number);

            DB::transaction(function () use ($request, $order, $financial, $newStatus, $newTrackingNumber, $originalStatus) {
                $attributes = [
                    'notes' => $request->input('notes', $order->notes),
                    'tracking_number' => $newTrackingNumber,
                    'tracking_carrier' => $request->input('tracking_carrier', $order->tracking_carrier),
                ];

                if ($request->has('payment_status')) {
                    $attributes['payment_status'] = $request->input('payment_status');
                }

                if ($request->has('tracking_driver')) {
                    $driverInput = trim((string) $request->input('tracking_driver', ''));
                    $attributes['tracking_driver'] = $driverInput !== '' ? $driverInput : null;
                }

                $order->fill($attributes);

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

            // A changed tracking number or carrier on a shipped order: the shipment and the shipped email follow
            // (ShippingService; deduped, so saving the same values again sends nothing)
            $emailWarning = null;

            if ($this->trackingKey($order) !== $originalTracking
                && ! app(ShippingService::class)->trackingChanged($order)) {
                $emailWarning = 'The order was updated, but the shipping email could not be queued. Please check the application logs.';
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

        if ($request->status === 'shipped' && $order->status !== 'shipped') {
            return $this->shipFormOnly();
        }

        try {
            $order->transitionTo($request->status, $request->user(), $request->input('reason'));
        } catch (InvalidOrderTransition $e) {
            return $this->refusedTransition($e);
        }

        return back()->with('success', 'Order status updated successfully.');
    }

    /**
     * Bulk update order statuses
     */
    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'exists:orders,id',
            // no "shipped": shipping needs per-order data (carrier, tracking number), so it goes through the ship form
            'status' => 'required|string|in:pending,processing,delivered,cancelled',
        ], ['status.in' => self::SHIP_FORM_ONLY_MESSAGE]);

        $updated = 0;
        $skipped = [];

        // Per model (not a bulk query) so model events keep status/payment_status consistent. Each order moves only if
        // the lifecycle allows it; the others are reported back and left untouched.
        Order::with('user')->whereIn('id', $request->order_ids)->orderBy('id')->get()
            ->each(function (Order $order) use ($request, &$updated, &$skipped) {
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
            });

        $summary = "{$updated} updated, ".count($skipped).' skipped'.($skipped ? ': '.implode(', ', $skipped) : '').'.';

        return back()->with($updated > 0 ? 'success' : 'error', $summary);
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
     * The ship form: carrier, provider, and tracking number or label parcels (ShippingService does the rest).
     */
    public function showShip(Order $order): Response|RedirectResponse
    {
        // Only a processing order with a captured payment may ship (processShip is refused the same way)
        if (! $order->canShip()) {
            return redirect()->route('admin.orders.show', $order)
                ->with('error', "Order {$order->order_number} cannot ship now: it must be processing and paid.");
        }

        $order->load(['user', 'orderItems.product', 'orderItems.product.defaultVariant', 'orderItems.size', 'shippingAddress']);

        $labelsEnabled = app(ShippingService::class)->labelsEnabled();
        $trackingManager = app(TrackingManager::class);

        $sender = \App\Shipping\Support\SenderAddress::fromSettings();

        return Inertia::render('Admin/Orders/Ship', [
            'order' => $order->toFrontendArray(),
            'carriers' => $trackingManager->carriers(),
            // false: the customer gets no shipped email at all (admin settings)
            'shipped_email_enabled' => app(EmailService::class)->isEnabled(OrderEmail::TYPE_SHIPPED),
            // optional label creation (OFF by default): when on, the form shows parcel fields and a "create label" button
            'labels_enabled' => $labelsEnabled,
            'parcels' => $labelsEnabled ? app(ShipmentBuilder::class)->prefillParcels($order) : [],
            // which rate providers can also buy labels (quotes from the others are shown but not buyable)
            'label_drivers' => $labelsEnabled ? app(\App\Shipping\Labels\LabelManager::class)->driverNames() : [],
            // carriers the rate list recommends first (shipping.preferred_carriers)
            'preferred_carriers' => (array) settings('shipping.preferred_carriers', []),
            'provider_names' => app(ShippingService::class)->providerNames(),
            'tracking_drivers' => $trackingManager->selectableDriverNames(),
            'default_tracking_driver' => $trackingManager->getDefaultDriver(),
            'shipping_cost' => (float) ($order->shipping_cost ?? $order->shipping_amount ?? 0.0),
            // rates and labels need at least the sender's postal code and city
            'sender_configured' => $sender['postal_code'] !== '' && $sender['city'] !== '',
        ]);
    }

    /**
     * Ship the order: carrier (required) and tracking number (required unless the admin confirmed shipping without
     * one). One shipped email: with the tracking, or, without a number, only when "notify the customer now" is ticked.
     */
    public function processShip(Request $request, Order $order, ShippingService $shipping)
    {
        // Label creation is honoured only while it is switched on; otherwise the manual path needs a tracking number.
        $wantsLabel = $shipping->labelsEnabled() && $request->boolean('create_label');
        $trackingManager = app(TrackingManager::class);

        $rules = [
            'tracking_carrier' => ['required', 'string', 'max:50', Rule::in($trackingManager->carrierCodes())],
            'tracking_number' => [Rule::requiredIf(! $wantsLabel && ! $request->boolean('ship_without_tracking')), 'nullable', 'string', 'max:100'],
            'tracking_driver' => ['sometimes', 'nullable', 'string', 'max:50', Rule::in(array_merge($trackingManager->driverNames(), ['']))],
            'label_driver' => $wantsLabel
                ? ['sometimes', 'nullable', 'string', 'max:50', Rule::in(app(\App\Shipping\Labels\LabelManager::class)->driverNames())]
                : ['sometimes', 'nullable', 'string', 'max:50'],
            'ship_without_tracking' => 'sometimes|boolean',
            'notify_customer' => 'sometimes|boolean',
            'create_label' => 'sometimes|boolean',
            'parcels' => 'sometimes|array|max:20',
            'parcels.*.weight' => 'nullable|numeric|min:0|max:1000',
            'parcels.*.length' => 'nullable|numeric|min:0|max:1000',
            'parcels.*.width' => 'nullable|numeric|min:0|max:1000',
            'parcels.*.height' => 'nullable|numeric|min:0|max:1000',
            'insurance_amount' => 'sometimes|nullable|numeric|min:0|max:1000000',
            'cod_amount' => 'sometimes|nullable|numeric|min:0|max:1000000',
            'quote_token' => 'sometimes|nullable|string|max:191',
        ];

        $data = $request->validate($rules);

        $trackingDriver = null;
        if (! $wantsLabel && $request->has('tracking_driver')) {
            $driverInput = trim((string) $request->input('tracking_driver', ''));
            $trackingDriver = $driverInput !== '' ? $driverInput : null;
        }

        try {
            $outcome = $shipping->ship($order, new ShipInput(
                carrierCode: $data['tracking_carrier'],
                trackingNumber: $data['tracking_number'] ?? null, // a number typed anyway is used even with "ship without tracking"
                notifyCustomer: $request->boolean('notify_customer'),
                createLabel: $wantsLabel,
                parcels: $wantsLabel ? array_values($data['parcels'] ?? []) : [],
                insuranceAmount: $wantsLabel && isset($data['insurance_amount']) ? (float) $data['insurance_amount'] : null,
                codAmount: $wantsLabel && isset($data['cod_amount']) ? (float) $data['cod_amount'] : null,
                labelDriver: $wantsLabel ? ($data['label_driver'] ?? null) : null,
                trackingDriver: $trackingDriver,
                quoteToken: $wantsLabel && isset($data['quote_token']) ? (string) $data['quote_token'] : null,
            ), $request->user());
        } catch (InvalidOrderTransition $e) {
            return $this->refusedTransition($e);
        } catch (LabelRejected $e) {
            // The carrier refused (validation): show its message on the form, no state change.
            return back()->withErrors(['label' => $e->getMessage()])->with('error', $e->getMessage());
        } catch (LabelProviderUnavailable $e) {
            // Transient/infrastructure problem: error flash, nothing changed (already logged with a reference id).
            return back()->with('error', $e->getMessage());
        }

        // label: the order page then offers to print the label just bought
        $response = redirect()->route('admin.orders.show', $wantsLabel ? [$order, 'label' => $outcome->shipment->id] : $order)
            ->with('success', 'Order has been shipped successfully!');

        return $outcome->emailFailed
            ? $response->with('warning', 'The order was marked as shipped, but the shipping email could not be queued. Please check the application logs.')
            : $response;
    }

    /**
     * Stream a shipment's label PDF from the private disk (admin only, authorised route).
     */
    public function downloadLabel(Order $order, Shipment $shipment, ShippingService $shipping)
    {
        abort_unless($shipment->order_id === $order->id, 404);

        $contents = $shipping->labelContents($shipment);

        abort_if($contents === null, 404, 'The label is not available.');

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="label-'.$order->order_number.'.pdf"',
        ]);
    }

    /**
     * Cancel a shipment at the carrier (driver permitting). When no other active shipments remain,
     * it automatically transitions the order back to processing.
     */
    public function cancelShipment(Request $request, Order $order, Shipment $shipment, ShippingService $shipping)
    {
        abort_unless($shipment->order_id === $order->id, 404);

        try {
            $shipping->cancelShipment($shipment);
        } catch (LabelRejected $e) {
            return back()->withErrors(['shipment' => $e->getMessage()])->with('error', $e->getMessage());
        } catch (LabelProviderUnavailable $e) {
            return back()->with('error', $e->getMessage());
        }

        $movedToProcessing = false;

        DB::transaction(function () use ($order, $shipment, $request, &$movedToProcessing) {
            $hasOtherActive = $order->shipments()
                ->where('id', '!=', $shipment->id)
                ->where('status', Shipment::STATUS_CREATED)
                ->exists();

            if (! $hasOtherActive && $order->status === 'shipped') {
                $order->transitionTo('processing', $request->user(), 'Shipment label cancelled at carrier');
                $movedToProcessing = true;
            }
        });

        $message = $movedToProcessing
            ? 'Shipment cancelled at the carrier and order moved back to processing.'
            : 'Shipment cancelled at the carrier.';

        return back()->with('success', $message);
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
     * Does the label driver that created this shipment support cancelling it? Never resolves a driver while labels are
     * off, and never throws (a misconfigured driver simply means "no cancel button").
     */
    private function driverCancels(Shipment $shipment): bool
    {
        if (! app(ShippingService::class)->labelsEnabled() || $shipment->isManual() || ! $shipment->external_id) {
            return false;
        }

        try {
            return app(\App\Shipping\Labels\LabelManager::class)->driver($shipment->driver)
                instanceof \App\Shipping\Labels\Contracts\CancelsShipments;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Status "shipped" asked for outside the ship form: refused, nothing written.
     */
    private function shipFormOnly()
    {
        return back()->withErrors(['status' => self::SHIP_FORM_ONLY_MESSAGE])->with('error', self::SHIP_FORM_ONLY_MESSAGE);
    }

    /**
     * Tracking number + carrier + tracking driver as compared for "changed" (case and spaces ignored).
     */
    private function trackingKey(Order $order): string
    {
        return mb_strtolower(trim((string) $order->tracking_number))
            .'|'.mb_strtolower(trim((string) $order->getAttribute('tracking_carrier')))
            .'|'.mb_strtolower(trim((string) $order->getAttribute('tracking_driver')));
    }
}
