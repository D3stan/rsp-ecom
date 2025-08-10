<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\OpenGraph;
use Artesaos\SEOTools\Facades\TwitterCard;
use Spatie\SchemaOrg\Schema;

class HomeController extends Controller
{
    /**
     * Display the home page with featured products and categories.
     */
    public function index(): Response
    {
        // Set SEO for homepage
        $appName = config('app.name');
        SEOMeta::setTitle($appName . ' - Quality Products Online');
        SEOMeta::setDescription('Discover our wide range of quality products. Shop electronics, clothing, home & garden, sports, and more with fast shipping and excellent customer service.');
        SEOMeta::setCanonical(url('/'));
        
        OpenGraph::setType('website');
        OpenGraph::setUrl(url('/'));
        OpenGraph::setTitle($appName . ' - Quality Products Online');
        OpenGraph::setDescription('Discover our wide range of quality products. Shop electronics, clothing, home & garden, sports, and more with fast shipping and excellent customer service.');
        OpenGraph::setSiteName($appName);
        
        TwitterCard::setType('summary_large_image');
        TwitterCard::setTitle($appName . ' - Quality Products Online');
        TwitterCard::setDescription('Discover our wide range of quality products. Shop electronics, clothing, home & garden, sports, and more.');
        
        // Add default social image if available
        $socialImageUrl = asset('images/social/default.jpg');
        if (file_exists(public_path('images/social/default.jpg'))) {
            OpenGraph::addImage($socialImageUrl);
            TwitterCard::setImage($socialImageUrl);
        }

        // Generate WebSite JSON-LD with SearchAction for sitelinks search box
        $websiteJsonLd = Schema::webSite()
            ->url(config('app.url'))
            ->name($appName)
            ->potentialAction(
                Schema::searchAction()
                    ->target(url('/products') . '?search={search_term_string}')
                    ->queryInput('required name=search_term_string')
            )
            ->toScript();

        // Get featured products (active, in stock, featured flag)
        $featuredProducts = Product::with(['category', 'approvedReviews'])
            ->active()
            ->featured()
            ->inStock()
            ->limit(6)
            ->get()
            ->map(function (Product $product) {
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
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                        'slug' => $product->category->slug,
                    ] : null,
                ];
            });

        // Get active categories with product counts
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
                    'name' => str_replace([' ', '&'], ['_', '_'], strtolower($category->name)),
                    'slug' => $category->slug,
                    'count' => $category->products_count,
                    'icon' => $this->getCategoryIcon($category->slug),
                ];
            });

        return Inertia::render('home', [
            'featuredProducts' => $featuredProducts,
            'categories' => $categories,
            'websiteJsonLd' => $websiteJsonLd,
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
     * Map category slugs to their corresponding icon names.
     * These should match the icons used in the frontend.
     */
    private function getCategoryIcon(string $slug): string
    {
        $iconMap = [
            'electronics' => 'Smartphone',
            'clothing' => 'Shirt', 
            'home-garden' => 'Home',
            'sports-outdoors' => 'Activity',
            'books-media' => 'Book',
            'health-beauty' => 'Heart',
            // Additional mappings for consistency
            'smartphones' => 'Smartphone',
            'laptops' => 'Laptop',
            'audio' => 'Headphones',
            'wearables' => 'Watch',
            'cameras' => 'Camera',
            'gaming' => 'Gamepad2',
        ];

        return $iconMap[$slug] ?? 'Package';
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
