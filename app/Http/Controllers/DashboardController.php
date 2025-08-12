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
        $orders = $user->orders()->with(['orderItems.product.category', 'size'])->latest()->get();

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
