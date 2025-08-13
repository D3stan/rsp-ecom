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
        // Set SEO for homepage based on current locale
        $appName = config('app.name');
        $locale = app()->getLocale();
        
        // Language-specific SEO content
        $seoContent = $this->getSeoContentByLocale($locale);
        
        SEOMeta::setTitle($appName . ' - ' . $seoContent['title']);
        SEOMeta::setDescription($seoContent['description']);
        SEOMeta::setKeywords($seoContent['keywords']);
        SEOMeta::setCanonical(url('/'));
        
        OpenGraph::setType('website');
        OpenGraph::setUrl(url('/'));
        OpenGraph::setTitle($appName . ' - ' . $seoContent['title']);
        OpenGraph::setDescription($seoContent['description']);
        OpenGraph::setSiteName($appName);
        
        TwitterCard::setType('summary_large_image');
        TwitterCard::setTitle($appName . ' - ' . $seoContent['twitter_title']);
        TwitterCard::setDescription($seoContent['twitter_description']);
        
        // Add default social image if available
        $socialImagePath = 'images/rsplogo.png';
        $socialImageUrl = asset($socialImagePath);
        if (file_exists(public_path($socialImagePath))) {
            OpenGraph::addImage($socialImageUrl);
            TwitterCard::setImage($socialImageUrl);
        }

        // Generate WebSite JSON-LD with SearchAction for sitelinks search box
        $websiteJsonLd = Schema::webSite()
            ->url(config('app.url'))
            ->name($appName)
            ->description($seoContent['schema_description'])
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

    /**
     * Get SEO content based on locale
     */
    private function getSeoContentByLocale(string $locale): array
    {
        $seoContent = [
            'it' => [
                'title' => 'Fari LED Angel Eye per Motard | Accessori Moto',
                'description' => 'Scopri la nostra collezione di fari LED angel eye per motard, luci LED moto, fari anteriori e accessori illuminazione per moto. Qualità professionale e spedizione veloce.',
                'twitter_title' => 'Fari LED Angel Eye per Motard',
                'twitter_description' => 'Fari LED angel eye per motard, luci LED moto e accessori illuminazione. Qualità professionale e spedizione veloce.',
                'schema_description' => 'Fari LED angel eye per motard, luci LED moto e accessori illuminazione. Specializzati in prodotti di qualità per motociclette.',
                'keywords' => ['fari led', 'angel eye', 'motard', 'luci led moto', 'fari moto', 'illuminazione moto', 'fari anteriori', 'angel eyes led', 'motard accessories', 'motorcycle lights', 'led headlights'],
            ],
            'en' => [
                'title' => 'LED Angel Eye Headlights for Motard | Motorcycle Accessories',
                'description' => 'Discover our collection of LED angel eye headlights for motard, motorcycle LED lights, front headlights and motorcycle lighting accessories. Professional quality and fast shipping.',
                'twitter_title' => 'LED Angel Eye Headlights for Motard',
                'twitter_description' => 'LED angel eye headlights for motard, motorcycle LED lights and lighting accessories. Professional quality and fast shipping.',
                'schema_description' => 'LED angel eye headlights for motard, motorcycle LED lights and lighting accessories. Specialized in quality motorcycle products.',
                'keywords' => ['led headlights', 'angel eye', 'motard', 'motorcycle led lights', 'motorcycle lights', 'motorcycle lighting', 'front headlights', 'angel eyes led', 'motard accessories', 'bike lights', 'led motorcycle'],
            ],
            'es' => [
                'title' => 'Faros LED Angel Eye para Motard | Accesorios Moto',
                'description' => 'Descubre nuestra colección de faros LED angel eye para motard, luces LED para moto, faros delanteros y accesorios de iluminación para moto. Calidad profesional y envío rápido.',
                'twitter_title' => 'Faros LED Angel Eye para Motard',
                'twitter_description' => 'Faros LED angel eye para motard, luces LED para moto y accesorios de iluminación. Calidad profesional y envío rápido.',
                'schema_description' => 'Faros LED angel eye para motard, luces LED para moto y accesorios de iluminación. Especializados en productos de calidad para motocicletas.',
                'keywords' => ['faros led', 'angel eye', 'motard', 'luces led moto', 'faros moto', 'iluminación moto', 'faros delanteros', 'angel eyes led', 'accesorios motard', 'luces motocicleta', 'led moto'],
            ],
            'fr' => [
                'title' => 'Phares LED Angel Eye pour Motard | Accessoires Moto',
                'description' => 'Découvrez notre collection de phares LED angel eye pour motard, éclairages LED moto, phares avant et accessoires d\'éclairage pour moto. Qualité professionnelle et expédition rapide.',
                'twitter_title' => 'Phares LED Angel Eye pour Motard',
                'twitter_description' => 'Phares LED angel eye pour motard, éclairages LED moto et accessoires d\'éclairage. Qualité professionnelle et expédition rapide.',
                'schema_description' => 'Phares LED angel eye pour motard, éclairages LED moto et accessoires d\'éclairage. Spécialisés en produits de qualité pour motos.',
                'keywords' => ['phares led', 'angel eye', 'motard', 'éclairages led moto', 'phares moto', 'éclairage moto', 'phares avant', 'angel eyes led', 'accessoires motard', 'éclairages moto', 'led moto'],
            ],
            'de' => [
                'title' => 'LED Angel Eye Scheinwerfer für Motard | Motorrad Zubehör',
                'description' => 'Entdecken Sie unsere Kollektion von LED Angel Eye Scheinwerfern für Motard, Motorrad LED-Beleuchtung, Frontscheinwerfer und Motorrad-Beleuchtungszubehör. Professionelle Qualität und schneller Versand.',
                'twitter_title' => 'LED Angel Eye Scheinwerfer für Motard',
                'twitter_description' => 'LED Angel Eye Scheinwerfer für Motard, Motorrad LED-Beleuchtung und Beleuchtungszubehör. Professionelle Qualität und schneller Versand.',
                'schema_description' => 'LED Angel Eye Scheinwerfer für Motard, Motorrad LED-Beleuchtung und Beleuchtungszubehör. Spezialisiert auf Qualitätsprodukte für Motorräder.',
                'keywords' => ['led scheinwerfer', 'angel eye', 'motard', 'motorrad led beleuchtung', 'motorrad scheinwerfer', 'motorrad beleuchtung', 'frontscheinwerfer', 'angel eyes led', 'motard zubehör', 'motorrad lichter', 'led motorrad'],
            ],
        ];

        // Return content for the specified locale, fallback to English if not found
        return $seoContent[$locale] ?? $seoContent['en'];
    }
}
