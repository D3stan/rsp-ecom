<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCheckoutAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ensure user is authenticated
        if (!Auth::check()) {
            return redirect()->route('login')->with('message', 'Please log in to access checkout.');
        }

        // For checkout pages, ensure user has items in cart
        if ($request->is('checkout') && !$request->is('checkout/success') && !$request->is('checkout/cancel')) {
            $user = Auth::user();
            $cartItemsCount = $user->carts()->count();
            
            if ($cartItemsCount === 0) {
                return redirect()->route('cart')->with('error', 'Your cart is empty.');
            }
        }

        return $next($request);
    }
}
