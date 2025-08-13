<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Artesaos\SEOTools\Facades\SEOMeta;

class ReviewController extends Controller
{
    public function index()
    {
        SEOMeta::setTitle('My Reviews – ' . config('app.name'));
        SEOMeta::setDescription('View and manage your product reviews.');
        
        $user = Auth::user();
        
        $reviews = $user->reviews()
            ->with(['product:id,name,slug,price'])
            ->orderBy('created_at', 'desc')
            ->paginate(12);
            
        return Inertia::render('Reviews/Index', [
            'reviews' => $reviews,
        ]);
    }
    
    public function create(Product $product)
    {
        // Set noindex for review form
        SEOMeta::addMeta('robots', 'noindex,nofollow', 'name');
        SEOMeta::setTitle('Write Review for ' . $product->name . ' – ' . config('app.name'));
        
        $user = Auth::user();
        
        // Check if user has already reviewed this product
        $existingReview = $user->reviews()->where('product_id', $product->id)->first();
        
        if ($existingReview) {
            return redirect()->route('orders.index')->with('error', 'You have already reviewed this product.');
        }
        
        // Check if user has ordered this product
        $hasOrdered = $user->orders()
            ->whereHas('orderItems', function ($query) use ($product) {
                $query->where('product_id', $product->id);
            })
            ->where('status', 'delivered')
            ->exists();
            
        if (!$hasOrdered) {
            return redirect()->route('orders.index')->with('error', 'You can only review products you have purchased.');
        }
        
        return Inertia::render('Reviews/Create', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'image_url' => $product->image_url,
                'price' => (float) $product->price,
            ]
        ]);
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);
        
        $user = Auth::user();
        
        // Check if user has already reviewed this product
        $existingReview = $user->reviews()->where('product_id', $request->product_id)->first();
        
        if ($existingReview) {
            return redirect()->route('orders.index')->with('error', 'You have already reviewed this product.');
        }
        
        // Check if user has ordered this product
        $hasOrdered = $user->orders()
            ->whereHas('orderItems', function ($query) use ($request) {
                $query->where('product_id', $request->product_id);
            })
            ->where('status', 'delivered')
            ->exists();
            
        if (!$hasOrdered) {
            return redirect()->route('orders.index')->with('error', 'You can only review products you have purchased.');
        }
        
        Review::create([
            'user_id' => $user->id,
            'product_id' => $request->product_id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'is_approved' => true, // Auto-approve for now
        ]);
        
        return redirect()->route('orders.index')->with('success', 'Thank you for your review!');
    }
}
