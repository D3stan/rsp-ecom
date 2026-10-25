<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $user = Auth::user();
        $product = Product::findOrFail($request->product_id);

        if ($user->hasInWishlist($product)) {
            return response()->json([
                'message' => 'Product is already in your wishlist',
                'in_wishlist' => true,
            ], 200);
        }

        $added = $user->addToWishlist($product);

        if ($added) {
            return response()->json([
                'message' => 'Product added to wishlist successfully',
                'in_wishlist' => true,
            ], 201);
        }

        return response()->json([
            'message' => 'Failed to add product to wishlist',
        ], 500);
    }

    public function destroy(Request $request, $wishlistId): RedirectResponse|JsonResponse
    {
        $user = Auth::user();

        $wishlistItem = Wishlist::where('id', $wishlistId)
            ->where('user_id', $user->id)
            ->first();

        if (! $wishlistItem) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Wishlist item not found',
                ], 404);
            }

            return redirect()->back()->withErrors(['error' => 'Wishlist item not found']);
        }

        $wishlistItem->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Product removed from wishlist successfully',
                'in_wishlist' => false,
            ], 200);
        }

        return redirect()->back()->with('success', 'Product removed from wishlist successfully');
    }

    public function destroyByProduct(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $user = Auth::user();
        $product = Product::findOrFail($request->product_id);

        $removed = $user->removeFromWishlist($product);

        if ($removed) {
            return response()->json([
                'message' => 'Product removed from wishlist successfully',
                'in_wishlist' => false,
            ], 200);
        }

        return response()->json([
            'message' => 'Product was not in your wishlist',
            'in_wishlist' => false,
        ], 200);
    }

    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $user = Auth::user();
        $product = Product::findOrFail($request->product_id);

        $inWishlist = $user->hasInWishlist($product);

        return response()->json([
            'in_wishlist' => $inWishlist,
        ], 200);
    }

    public function count(): JsonResponse
    {
        $user = Auth::user();
        $count = $user->wishlist()->count();

        return response()->json([
            'count' => $count,
        ], 200);
    }

    public function toggle(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $user = Auth::user();
        $product = Product::findOrFail($request->product_id);

        if ($user->hasInWishlist($product)) {
            $user->removeFromWishlist($product);

            return response()->json([
                'message' => 'Product removed from wishlist',
                'in_wishlist' => false,
            ], 200);
        } else {
            $user->addToWishlist($product);

            return response()->json([
                'message' => 'Product added to wishlist',
                'in_wishlist' => true,
            ], 200);
        }
    }
}
