<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
        $perPage = $request->get('per_page', 9); // Set to 9 products per page for optimal grid layout
        $products = $query->paginate($perPage)->withQueryString();

        // Transform products for frontend
        $productsData = $products->through(function (Product $product) {
            // Handle empty image arrays - use default image
            $imageUrl = '/images/product.png';
            if (!empty($product->images)) {
                $mainImage = $product->main_image_url;
                if ($mainImage && $this->imageExists($mainImage)) {
                    $imageUrl = $mainImage;
                }
            }
            
            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->price,
                'originalPrice' => $product->compare_price,
                'rating' => round($product->average_rating, 1),
                'reviews' => $product->review_count,
                'image' => $imageUrl,
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
     * Display the specified product.
     */
    public function show(string $slug): Response
    {
        $product = Product::with(['category', 'approvedReviews.user', 'size'])
            ->where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        // Handle empty image arrays - use default image
        $imageUrl = '/images/product.png';
        $images = [$imageUrl]; // Start with default image
        
        if (!empty($product->images)) {
            $mainImage = $product->main_image_url;
            if ($mainImage && $this->imageExists($mainImage)) {
                $imageUrl = $mainImage;
                $images = [$imageUrl]; // Replace default with actual image
                
                // Get additional images if available
                foreach ($product->images as $imagePath) {
                    $fullImagePath = $this->getFullImagePath($imagePath, $product->id);
                    if ($fullImagePath !== $imageUrl && $this->imageExists($fullImagePath)) {
                        $images[] = $fullImagePath;
                    }
                }
            }
        }

        // Transform product for frontend
        $productData = [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'shortDescription' => null, // Field doesn't exist in current schema
            'price' => $product->price,
            'originalPrice' => $product->compare_price,
            'rating' => round($product->average_rating, 1),
            'reviews' => $product->review_count,
            'images' => $images,
            'badge' => $this->getProductBadge($product),
            'inStock' => $product->stock_quantity > 0,
            'stockQuantity' => $product->stock_quantity,
            'specifications' => null, // Field doesn't exist in current schema
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'sizes' => $product->size ? [[
                'id' => $product->size->id,
                'name' => $product->size->name,
                'price_adjustment' => 0, // No price adjustment for single size
            ]] : [],
        ];

        // Get reviews with user information
        $reviews = $product->approvedReviews->map(function ($review) {
            return [
                'id' => $review->id,
                'user' => $review->user ? $review->user->name : 'Anonymous',
                'rating' => $review->rating,
                'comment' => $review->comment,
                'date' => $review->created_at->toISOString(),
            ];
        });

        // Get related products (same category, different product)
        $relatedProducts = Product::with(['category', 'approvedReviews'])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->active()
            ->inStock()
            ->limit(4)
            ->get()
            ->map(function (Product $relatedProduct) {
                // Handle empty image arrays for related products
                $relatedImageUrl = '/images/product.png';
                if (!empty($relatedProduct->images)) {
                    $mainImage = $relatedProduct->main_image_url;
                    if ($mainImage && $this->imageExists($mainImage)) {
                        $relatedImageUrl = $mainImage;
                    }
                }

                return [
                    'id' => $relatedProduct->id,
                    'name' => $relatedProduct->name,
                    'slug' => $relatedProduct->slug,
                    'price' => $relatedProduct->price,
                    'image' => $relatedImageUrl,
                ];
            });

        // Generate breadcrumb
        $breadcrumb = ['Home', 'Products'];
        if ($product->category) {
            $breadcrumb[] = $product->category->name;
        }
        $breadcrumb[] = $product->name;

        return Inertia::render('product', [
            'product' => $productData,
            'reviews' => $reviews,
            'relatedProducts' => $relatedProducts,
            'breadcrumb' => $breadcrumb,
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

    /**
     * Get full image path for a product image
     */
    private function getFullImagePath(string $imagePath, int $productId): string
    {
        // If it's already a full URL, return as is
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            return $imagePath;
        }
        
        return Storage::url("products/{$productId}/{$imagePath}");
    }

    /**
     * Check if an image file exists
     */
    private function imageExists(?string $imageUrl): bool
    {
        if (!$imageUrl) {
            return false;
        }

        // If it's a full URL, check if it's accessible
        if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return true; // Assume external URLs are valid
        }

        // For storage URLs, check if the file exists
        if (str_starts_with($imageUrl, '/storage/')) {
            $filePath = str_replace('/storage/', '', $imageUrl);
            return \Storage::disk('public')->exists($filePath);
        }

        // For other paths, check if the public file exists
        $publicPath = public_path($imageUrl);
        return file_exists($publicPath);
    }
}
