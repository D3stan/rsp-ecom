<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Revenue;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        // Get date ranges
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $thisWeek = now()->startOfWeek();
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();
        $thisYear = now()->startOfYear();

        // Basic stats. Revenue is net: cancelled orders excluded, succeeded refunds subtracted (App\Support\Revenue)
        $stats = [
            'today' => [
                'orders' => Order::whereDate('created_at', $today)->count(),
                'revenue' => Revenue::net(Order::whereDate('created_at', $today)),
                'customers' => User::whereDate('created_at', $today)->count(),
            ],
            'yesterday' => [
                'orders' => Order::whereDate('created_at', $yesterday)->count(),
                'revenue' => Revenue::net(Order::whereDate('created_at', $yesterday)),
            ],
            'month' => [
                'orders' => Order::whereDate('created_at', '>=', $thisMonth)->count(),
                'revenue' => Revenue::net(Order::whereDate('created_at', '>=', $thisMonth)),
                'customers' => User::whereDate('created_at', '>=', $thisMonth)->count(),
            ],
            'year' => [
                'orders' => Order::whereDate('created_at', '>=', $thisYear)->count(),
                'revenue' => Revenue::net(Order::whereDate('created_at', '>=', $thisYear)),
            ],
        ];

        // Calculate growth percentages
        $todayGrowth = $stats['yesterday']['orders'] > 0
            ? round((($stats['today']['orders'] - $stats['yesterday']['orders']) / $stats['yesterday']['orders']) * 100, 1)
            : 0;

        $revenueGrowth = $stats['yesterday']['revenue'] > 0
            ? round((($stats['today']['revenue'] - $stats['yesterday']['revenue']) / $stats['yesterday']['revenue']) * 100, 1)
            : 0;

        // Recent orders for activity feed
        $recentOrders = Order::with(['user'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->user ? $order->user->name : __('admin.dashboard.guest_customer'),
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                    'time_ago' => $order->created_at->diffForHumans(),
                ];
            });

        // Order status distribution
        $orderStatusDistribution = [
            'pending' => Order::where('status', 'pending')->count(),
            'processing' => Order::where('status', 'processing')->count(),
            'shipped' => Order::where('status', 'shipped')->count(),
            'delivered' => Order::where('status', 'delivered')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
        ];

        // Revenue chart data (last 7 days)
        $revenueChartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $revenue = Revenue::net(Order::whereDate('created_at', $date));

            $revenueChartData[] = [
                'date' => $date->locale(app()->getLocale())->translatedFormat('M d'),
                'revenue' => (float) $revenue,
                'orders' => Order::whereDate('created_at', $date)->count(),
            ];
        }

        // Top products (placeholder - you'll need to implement this based on OrderItems)
        $paidOrderItems = function ($query) {
            $query->whereHas('order', function ($orderQuery) {
                $orderQuery->where('payment_status', 'succeeded');
            });
        };

        // HAVING on a withCount alias is a non-aggregate query on SQLite: filter with whereHas instead.
        $topProducts = Product::withCount(['orderItems' => $paidOrderItems])
            ->whereHas('orderItems', $paidOrderItems)
            ->orderBy('order_items_count', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sales_count' => $product->order_items_count,
                    'price' => $product->price,
                ];
            });

        // Low stock alerts
        $lowStockProducts = Product::lowStock()
            ->orderBy('stock_quantity', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'stock_quantity' => $product->stock_quantity,
                    'price' => $product->price,
                ];
            });

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'growth' => [
                'orders' => $todayGrowth,
                'revenue' => $revenueGrowth,
            ],
            'recentOrders' => $recentOrders,
            'orderStatusDistribution' => $orderStatusDistribution,
            'revenueChartData' => $revenueChartData,
            'topProducts' => $topProducts,
            'lowStockProducts' => $lowStockProducts,
        ]);
    }
}
