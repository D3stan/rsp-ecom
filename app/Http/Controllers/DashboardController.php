<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Artesaos\SEOTools\Facades\SEOMeta;

class DashboardController extends Controller
{
    public function orders()
    {
        // Set noindex for user account pages
        SEOMeta::addMeta('robots', 'noindex,nofollow', 'name');
        SEOMeta::setTitle('My Orders – ' . config('app.name'));
        
        $user = Auth::user();
        $orders = $user->orders()
            ->with(['orderItems.product.category', 'orderItems.size'])
            ->latest()
            ->get()
            ->map(function ($order) {
                // Format order data for frontend
                $orderData = $order->toArray();
                $orderData['total'] = $order->total_amount; // Add alias for backward compatibility
                $orderData['order_items'] = $order->orderItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'order_id' => $item->order_id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'price' => (float) $item->price,
                        'total' => (float) $item->total,
                        'product' => $item->product ? [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'slug' => $item->product->slug,
                            'image_url' => $item->product->image_url,
                            'category' => $item->product->category,
                        ] : null,
                        'size' => $item->size,
                    ];
                })->toArray();
                
                return $orderData;
            });

        return Inertia::render('Dashboard/Orders', [
            'orders' => $orders,
        ]);
    }

    public function wishlist()
    {
        // Set noindex for user account pages
        SEOMeta::addMeta('robots', 'noindex,nofollow', 'name');
        SEOMeta::setTitle('My Wishlist – ' . config('app.name'));
        
        $user = Auth::user();
        $wishlist = $user->wishlist()->with('product.category')->get();

        return Inertia::render('Dashboard/Wishlist', [
            'wishlist' => $wishlist,
        ]);
    }
}
