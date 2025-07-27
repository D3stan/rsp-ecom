<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductsController extends Controller
{
    /**
     * Display a listing of products with search, filters, and pagination.
     */
    public function index(Request $request): Response
    {
        $query = Product::with(['category', 'approvedReviews'])
            ->active()
            ->inStock();

        // Search functionality
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($categoryId = $request->get('category')) {
            $query->where('category_id', $categoryId);
        }

        // Price range filter
        if ($minPrice = $request->get('min_price')) {
            $query->where('price', '>=', $minPrice);
        }
        if ($maxPrice = $request->get('max_price')) {
            $query->where('price', '<=', $maxPrice);
        }

        // Sorting
        $sortBy = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');

        switch ($sortBy) {
            case 'price':
                $query->orderBy('price', $sortDirection);
                break;
            case 'name':
                $query->orderBy('name', $sortDirection);
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'rating':
                // Order by average rating (calculated from reviews)
                $query->withAvg('approvedReviews', 'rating')
                      ->orderBy('approved_reviews_avg_rating', 'desc');
                break;
            case 'popular':
                // Order by number of reviews as popularity indicator
                $query->withCount('approvedReviews')
                      ->orderBy('approved_reviews_count', 'desc');
                break;
            case 'most_bought':
                // Order by total quantity sold from order items
                $query->withSum('orderItems', 'quantity')
                      ->orderBy('order_items_sum_quantity', 'desc');
                break;
            default:
                $query->orderBy('name', 'asc');
        }

        // Pagination
        $perPage = $request->get('per_page', 8); // Reduced from 24 to 8 for better visibility
        $products = $query->paginate($perPage)->withQueryString();

        // Transform products for frontend
        $productsData = $products->through(function (Product $product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->price,
                'originalPrice' => $product->compare_price,
                'rating' => round($product->average_rating, 1),
                'reviews' => $product->review_count,
                'image' => $product->main_image_url ?? '/images/placeholder-product.jpg',
                'badge' => $this->getProductBadge($product),
                'inStock' => $product->stock_quantity > 0,
                'stockQuantity' => $product->stock_quantity,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'slug' => $product->category->slug,
                ] : null,
            ];
        });

        // Get categories for filter dropdown
        $categories = Category::active()
            ->withCount(['products' => function ($query) {
                $query->active()->inStock();
            }])
            ->having('products_count', '>', 0)
            ->orderBy('name')
            ->get()
            ->map(function (Category $category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'count' => $category->products_count,
                ];
            });

        // Get price range for filter
        $priceRange = [
            'min' => Product::active()->inStock()->min('price') ?? 0,
            'max' => Product::active()->inStock()->max('price') ?? 1000,
        ];

        return Inertia::render('products', [
            'products' => $productsData,
            'categories' => $categories,
            'priceRange' => $priceRange,
            'filters' => [
                'search' => $request->get('search', ''),
                'category' => $request->get('category', ''),
                'min_price' => $request->get('min_price', ''),
                'max_price' => $request->get('max_price', ''),
                'sort' => $request->get('sort', 'name'),
                'direction' => $request->get('direction', 'asc'),
                'per_page' => $request->get('per_page', 24),
            ],
            'pagination' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
        ]);
    }

    /**
     * Get badge text for a product based on its properties.
     */
    private function getProductBadge(Product $product): ?string
    {
        // Check if product has a discount
        if ($product->compare_price && $product->compare_price > $product->price) {
            $discountPercent = round((($product->compare_price - $product->price) / $product->compare_price) * 100);
            if ($discountPercent >= 20) {
                return 'Sale';
            }
        }

        // Check if product is newly added (within last 30 days)
        if ($product->created_at && $product->created_at->diffInDays(now()) <= 30) {
            return 'New';
        }

        // Check if product has high rating and many reviews (best seller)
        if ($product->average_rating >= 4.5 && $product->review_count >= 50) {
            return 'Best Seller';
        }

        return null;
    }
}
