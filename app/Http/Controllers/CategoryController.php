<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\OpenGraph;
use Artesaos\SEOTools\Facades\TwitterCard;

class CategoryController extends Controller
{
    /**
     * Display the specified category page.
     */
    public function show(string $slug, Request $request): Response
    {
        $category = Category::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        // Set SEO for category page
        $pageTitle = $category->seo_title ?: $category->name;
        $metaDescription = $category->seo_description ?: $category->description ?: "Browse our {$category->name} collection. Find quality products in this category with great prices and fast shipping.";
        
        SEOMeta::setTitle($pageTitle . ' – ' . config('app.name'));
        SEOMeta::setDescription(Str::limit(strip_tags($metaDescription), 160));
        SEOMeta::setCanonical(route('categories.show', $category));

        OpenGraph::setType('website');
        OpenGraph::setUrl(route('categories.show', $category));
        OpenGraph::setTitle($pageTitle . ' – ' . config('app.name'));
        OpenGraph::setDescription(Str::limit(strip_tags($metaDescription), 300));

        TwitterCard::setType('summary');
        TwitterCard::setTitle($pageTitle . ' – ' . config('app.name'));
        TwitterCard::setDescription(Str::limit(strip_tags($metaDescription), 200));

        // Add category image if available
        $socialImageUrl = $category->social_image_url ?: $category->image;
        if (!empty($socialImageUrl)) {
            if (!filter_var($socialImageUrl, FILTER_VALIDATE_URL)) {
                $socialImageUrl = url($socialImageUrl);
            }
            OpenGraph::addImage($socialImageUrl);
            TwitterCard::setImage($socialImageUrl);
        }

        // Get products in this category with pagination
        $query = Product::with(['category', 'approvedReviews'])
            ->where('category_id', $category->id)
            ->active()
            ->inStock();

        // Apply filters from request
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
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
                $query->withAvg('approvedReviews', 'rating')
                      ->orderBy('approved_reviews_avg_rating', 'desc');
                break;
            default:
                $query->orderBy('name', 'asc');
        }

        // Pagination
        $perPage = $request->get('per_page', 12);
        $products = $query->paginate($perPage)->withQueryString();

        // Transform products for frontend
        $productsData = $products->through(function (Product $product) {
            $imageUrl = $product->main_image_url ?: '/images/product.png';
            
            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->price,
                'originalPrice' => $product->compare_price,
                'rating' => round($product->average_rating, 1),
                'reviews' => $product->review_count,
                'image' => $imageUrl,
                'inStock' => $product->stock_quantity > 0,
                'stockQuantity' => $product->stock_quantity,
            ];
        });

        // Get price range for filters
        $priceRange = [
            'min' => Product::where('category_id', $category->id)->active()->inStock()->min('price') ?? 0,
            'max' => Product::where('category_id', $category->id)->active()->inStock()->max('price') ?? 1000,
        ];

        return Inertia::render('Categories/Show', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'image' => $category->image,
            ],
            'products' => $productsData,
            'priceRange' => $priceRange,
            'filters' => [
                'search' => $request->get('search', ''),
                'min_price' => $request->get('min_price', ''),
                'max_price' => $request->get('max_price', ''),
                'sort' => $request->get('sort', 'name'),
                'direction' => $request->get('direction', 'asc'),
                'per_page' => $request->get('per_page', 12),
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
}
