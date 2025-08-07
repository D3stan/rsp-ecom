<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class OrdersController extends Controller
{
    /**
     * Display a listing of orders
     */
    public function index(Request $request): Response
    {
        $query = Order::with(['user', 'orderItems.product', 'billingAddress', 'shippingAddress'])
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
            'orderItems.product',
            'billingAddress',
            'shippingAddress'
        ]);

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order->toFrontendArray(),
        ]);
    }

    /**
     * Show the form for editing the specified order
     */
    public function edit(Order $order): Response
    {
        $order->load([
            'user',
            'orderItems.product',
            'billingAddress',
            'shippingAddress'
        ]);

        return Inertia::render('Admin/Orders/Edit', [
            'order' => $order->toFrontendArray(),
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
                'request_data' => $request->all()
            ]);

            $request->validate([
                'status' => 'sometimes|string|in:pending,processing,shipped,delivered,cancelled',
                'payment_status' => 'sometimes|string|in:pending,processing,succeeded,failed',
                'notes' => 'sometimes|nullable|string|max:1000',
                'tracking_number' => 'sometimes|nullable|string|max:100',
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

            // Update order basic information
            $order->update($request->only([
                'status', 
                'payment_status', 
                'notes', 
            'tracking_number',
            'shipping_amount', 
            'tax_amount', 
            'subtotal', 
            'total_amount'
        ]));

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
        return back()->with('success', 'Order updated successfully.');
        
        } catch (\Exception $e) {
            Log::error('Failed to update order', [
                'order_id' => $order->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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

        $order->update(['status' => $request->status]);

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
            'status' => 'required|string|in:pending,processing,shipped,delivered,cancelled',
        ]);

        Order::whereIn('id', $request->order_ids)
            ->update(['status' => $request->status]);

        $count = count($request->order_ids);

        return back()->with('success', "{$count} orders updated successfully.");
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

        // TODO: Implement Stripe refund logic here
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
            'orderItems.product',
            'billingAddress',
            'shippingAddress'
        ]);

        // Get admin settings for shipping
        $adminSettings = [
            'company_name' => \App\Models\Setting::get('site_name', 'Your Company'),
            'company_address' => \App\Models\Setting::get('company_address', ''),
            'company_phone' => \App\Models\Setting::get('contact_phone', ''),
            'company_email' => \App\Models\Setting::get('contact_email', ''),
            'iban' => \App\Models\Setting::get('company_iban', ''),
            'account_holder' => \App\Models\Setting::get('company_account_holder', ''),
        ];

        return Inertia::render('Admin/Orders/Ship', [
            'order' => $order->toFrontendArray(),
            'adminSettings' => $adminSettings,
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
            'box_info.weight' => 'nullable|numeric|min:0',
            'box_info.length' => 'nullable|numeric|min:0',
            'box_info.width' => 'nullable|numeric|min:0',
            'box_info.height' => 'nullable|numeric|min:0',
            'box_info.tracking_number' => 'nullable|string|max:100',
            'box_info.carrier' => 'nullable|string|max:100',
            'box_info.service_type' => 'nullable|string|max:100',
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
                'tracking_number' => $request->input('box_info.tracking_number'),
                'notes' => $this->buildShippingNotes($order, $request->all()),
            ];

            $order->update($updateData);

            // Send shipping notification email if enabled
            if (\App\Models\Setting::get('order_shipped_enabled', true)) {
                $emailService = app(\App\Services\EmailService::class);
                $emailService->sendOrderShipped($order, $request->input('box_info.tracking_number'));
            }

            Log::info('Order shipped successfully', [
                'order_id' => $order->id,
                'tracking_number' => $request->input('box_info.tracking_number'),
                'carrier' => $request->input('box_info.carrier'),
            ]);

            return redirect()->route('admin.orders.show', $order)
                ->with('success', 'Order has been shipped successfully!');

        } catch (\Exception $e) {
            Log::error('Failed to process order shipping', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
        
        // Box information
        $boxInfo = $shippingData['box_info'] ?? [];
        if (!empty($boxInfo)) {
            $notes .= "\nPackage Details:\n";
            if (!empty($boxInfo['weight'])) $notes .= "- Weight: " . $boxInfo['weight'] . " kg\n";
            if (!empty($boxInfo['length'])) $notes .= "- Dimensions: " . $boxInfo['length'] . "x" . ($boxInfo['width'] ?? '0') . "x" . ($boxInfo['height'] ?? '0') . " cm\n";
            if (!empty($boxInfo['carrier'])) $notes .= "- Carrier: " . $boxInfo['carrier'] . "\n";
            if (!empty($boxInfo['service_type'])) $notes .= "- Service Type: " . $boxInfo['service_type'] . "\n";
            if (!empty($boxInfo['tracking_number'])) $notes .= "- Tracking Number: " . $boxInfo['tracking_number'] . "\n";
        }
        
        // Additional settings
        $additionalSettings = $shippingData['additional_settings'] ?? [];
        if (!empty($additionalSettings)) {
            if (!empty($additionalSettings['insurance_value'])) {
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
