<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
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
            'orders' => $orders,
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
     * Update the specified order
     */
    public function update(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'sometimes|string|in:pending,processing,shipped,delivered,cancelled',
            'payment_status' => 'sometimes|string|in:pending,processing,succeeded,failed',
            'notes' => 'sometimes|nullable|string|max:1000',
        ]);

        $order->update($request->only(['status', 'payment_status', 'notes']));

        return back()->with('success', 'Order updated successfully.');
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
}
