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
                $orderData['tracking_number'] = $order->tracking_number; // Ensure tracking number is included
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
        
        // Get user's reviews to check which products have been reviewed
        $userReviews = $user->reviews()->select('product_id')->get();

        return Inertia::render('Dashboard/Orders', [
            'orders' => $orders,
            'userReviews' => $userReviews,
        ]);
    }

    public function wishlist()
    {
        // Set noindex for user account pages
        SEOMeta::addMeta('robots', 'noindex,nofollow', 'name');
        SEOMeta::setTitle('My Wishlist – ' . config('app.name'));
        
        $user = Auth::user();
        $wishlist = $user->wishlist()
            ->with(['product.category'])
            ->latest()
            ->get()
            ->map(function ($wishlistItem) {
                return [
                    'id' => $wishlistItem->id,
                    'user_id' => $wishlistItem->user_id,
                    'product_id' => $wishlistItem->product_id,
                    'created_at' => $wishlistItem->created_at->toISOString(),
                    'updated_at' => $wishlistItem->updated_at->toISOString(),
                    'product' => [
                        'id' => $wishlistItem->product->id,
                        'name' => $wishlistItem->product->name,
                        'slug' => $wishlistItem->product->slug,
                        'price' => (float) $wishlistItem->product->price,
                        'image_url' => $wishlistItem->product->image_url,
                        'rating' => $wishlistItem->product->rating ?? null,
                        'description' => $wishlistItem->product->description,
                        'stock_quantity' => $wishlistItem->product->stock_quantity,
                        'is_active' => $wishlistItem->product->is_active,
                        'category' => $wishlistItem->product->category ? [
                            'id' => $wishlistItem->product->category->id,
                            'name' => $wishlistItem->product->category->name,
                            'slug' => $wishlistItem->product->category->slug,
                        ] : null,
                    ],
                ];
            });

        return Inertia::render('Dashboard/Wishlist', [
            'wishlist' => $wishlist,
        ]);
    }
}
