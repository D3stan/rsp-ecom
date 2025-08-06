<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function orders()
    {
        $user = Auth::user();
        $orders = $user->orders()->with(['orderItems.product.category', 'size'])->latest()->get();

        return Inertia::render('Dashboard/Orders', [
            'orders' => $orders,
        ]);
    }
}
