<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\CatalogPagination;
use Artesaos\SEOTools\Facades\JsonLd;
use Artesaos\SEOTools\Facades\OpenGraph;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\TwitterCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductsController extends Controller
{
    public function index(Request $request): Response
    {
        $pageTitle = 'Products';
        $metaDescription = 'Browse our wide selection of high-quality products. Find the perfect items for your needs with filters and search.';

        if ($search = $request->get('search')) {
            $pageTitle = "Search results for '{$search}'";
            $metaDescription = "Search results for '{$search}' - Find the products you're looking for.";
        }

        if ($categorySlug = $request->get('category')) {
            $category = Category::where('slug', $categorySlug)->first();
            if ($category) {
                $pageTitle = $category->name.' Products';
                $metaDescription = $category->description ?? "Browse our {$category->name} collection. Find the perfect products in this category.";
            }
        }

        SEOMeta::setTitle($pageTitle.' – '.settings('store.name'));
        SEOMeta::setDescription(Str::limit($metaDescription, 160));
        SEOMeta::setCanonical(route('products', $request->except('page')));

        if ($request->hasAny(['search', 'category', 'min_price', 'max_price', 'sort'])) {
            SEOMeta::addMeta('robots', 'noindex,follow', 'name');
        }

        OpenGraph::setType('website');
        OpenGraph::setUrl(route('products', $request->except('page')));
        OpenGraph::setTitle($pageTitle.' – '.settings('store.name'));
        OpenGraph::setDescription(Str::limit($metaDescription, 300));

        TwitterCard::setType('summary');
        TwitterCard::setTitle($pageTitle.' – '.settings('store.name'));
        TwitterCard::setDescription(Str::limit($metaDescription, 200));

        $query = Product::with(['category', 'approvedReviews', 'defaultVariant'])
            ->active()
            ->inStock();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($categorySlug = $request->get('category')) {
            $category = Category::where('slug', $categorySlug)->first();
            if ($category) {
                $query->where('category_id', $category->id);
            }
        }

        if ($minPrice = $request->get('min_price')) {
            $query->where('price', '>=', $minPrice);
        }
        if ($maxPrice = $request->get('max_price')) {
            $query->where('price', '<=', $maxPrice);
        }

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
            case 'popular':
                $query->withCount('approvedReviews')
                    ->orderBy('approved_reviews_count', 'desc');
                break;
            case 'most_bought':
                $query->withSum('orderItems', 'quantity')
                    ->orderBy('order_items_sum_quantity', 'desc');
                break;
            default:
                $query->orderBy('name', 'asc');
        }

        $perPage = CatalogPagination::perPage($request);
        $products = $query->paginate($perPage)->withQueryString();

        $productsData = $products->through(function (Product $product) {
            $imageUrl = '/images/product.png';
            if (! empty($product->images)) {
                $mainImage = $product->main_image_url;
                if ($mainImage && $this->imageExists($mainImage)) {
                    $imageUrl = $mainImage;
                }
            }

            $inWishlist = false;
            if (auth()->check()) {
                $inWishlist = auth()->user()->hasInWishlist($product);
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->defaultVariant?->price ?? $product->price,
                'originalPrice' => $product->compare_price,
                'rating' => round($product->average_rating, 1),
                'reviews' => $product->review_count,
                'image' => $product->defaultVariant?->main_image_url ?? $imageUrl,
                'badge' => $this->getProductBadge($product),
                'inStock' => ($product->defaultVariant?->stock_quantity ?? $product->stock_quantity) > 0,
                'stockQuantity' => $product->defaultVariant?->stock_quantity ?? $product->stock_quantity,
                'inWishlist' => $inWishlist,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'slug' => $product->category->slug,
                ] : null,
                'defaultVariantId' => $product->defaultVariant?->id,
                'variants' => $product->activeVariants->map(fn ($v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'price' => $v->price,
                    'stock_quantity' => $v->stock_quantity,
                    'image' => $v->main_image_url,
                ]),
            ];
        });

        $categories = Category::active()
            ->withCount(['products' => function ($query) {
                $query->active()->inStock();
            }])
            ->orderBy('name')
            ->get()
            ->filter(fn (Category $category) => $category->products_count > 0)
            ->values()
            ->map(function (Category $category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'count' => $category->products_count,
                ];
            });

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
                'per_page' => $perPage,
            ],
            'pagination' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'from' => $products->firstItem() ?? 0,
                'to' => $products->lastItem() ?? 0,
            ],
        ]);
    }

    public function show(string $slug): Response
    {
        $product = Product::with(['category', 'approvedReviews.user', 'approvedReviews.approvedMedia', 'size', 'activeVariants', 'defaultVariant'])
            ->withAvg('approvedReviews', 'rating')
            ->withCount('approvedReviews')
            ->where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        $pageTitle = $product->seo_title ?: $product->name;
        $metaDescription = $product->seo_description ?: strip_tags($product->description);

        SEOMeta::setTitle($pageTitle.' – '.settings('store.name'));
        SEOMeta::setDescription(Str::limit($metaDescription, 160));
        SEOMeta::setCanonical(route('products.show', $product));

        OpenGraph::setType('product');
        OpenGraph::setUrl(route('products.show', $product));
        OpenGraph::setTitle($pageTitle.' – '.settings('store.name'));
        OpenGraph::setDescription(Str::limit($metaDescription, 300));

        $socialImageUrl = $product->social_image_url ?: $product->main_image_url;
        if ($socialImageUrl && ! filter_var($socialImageUrl, FILTER_VALIDATE_URL)) {
            $socialImageUrl = url($socialImageUrl);
        }

        if ($socialImageUrl) {
            OpenGraph::addImage($socialImageUrl);
            TwitterCard::setImage($socialImageUrl);
        }

        TwitterCard::setType('summary_large_image');
        TwitterCard::setTitle($pageTitle.' – '.settings('store.name'));
        TwitterCard::setDescription(Str::limit($metaDescription, 200));

        JsonLd::setType('Product');
        JsonLd::addValue('name', $product->name);
        JsonLd::addValue('description', Str::limit($metaDescription, 300));
        JsonLd::addValue('sku', $product->sku);

        $productImages = [];
        if (! empty($product->images)) {
            foreach ($product->images as $image) {
                $imageUrl = $product->getImageUrl($image);
                if (! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                    $imageUrl = url($imageUrl);
                }
                $productImages[] = $imageUrl;
            }
        }
        if (! empty($productImages)) {
            JsonLd::addValue('image', $productImages);
        }

        if ($product->category) {
            JsonLd::addValue('brand', [
                '@type' => 'Brand',
                'name' => $product->category->name,
            ]);
        }

        JsonLd::addValue('offers', [
            '@type' => 'Offer',
            'price' => (string) $product->price,
            'priceCurrency' => 'EUR',
            'availability' => $product->stock_quantity > 0
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
            'url' => route('products.show', $product),
            'seller' => [
                '@type' => 'Organization',
                'name' => settings('store.name'),
            ],
        ]);

        if ($product->review_count > 0) {
            JsonLd::addValue('aggregateRating', [
                '@type' => 'AggregateRating',
                'ratingValue' => round($product->average_rating, 1),
                'reviewCount' => $product->review_count,
                'bestRating' => 5,
                'worstRating' => 1,
            ]);
        }

        $productData = [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'base_sku' => $product->base_sku,
            'safety_information' => $product->safety_information,
            'variants' => $product->activeVariants->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'sku' => $product->base_sku ? $product->base_sku.$v->sku_suffix : null,
                'price' => $v->price,
                'stock_quantity' => $v->stock_quantity,
                'images' => $v->image_urls,
                'description' => $v->description,
                'is_default' => $v->is_default,
            ]),
            'shipping' => [
                'size' => $product->size?->name,
            ],
            'average_rating' => $product->approved_reviews_count > 0 ? round((float) $product->approved_reviews_avg_rating, 1) : null,
            'review_count' => (int) $product->approved_reviews_count,
            'rating_distribution' => collect([5, 4, 3, 2, 1])
                ->mapWithKeys(fn (int $star) => [(string) $star => $product->approvedReviews->where('rating', $star)->count()])
                ->all(),
            'inWishlist' => auth()->check() && auth()->user()->hasInWishlist($product),
        ];

        $reviews = $product->approvedReviews->map(function ($review) {
            return [
                'id' => $review->id,
                'user' => $review->user ? $review->user->name : 'Anonymous',
                'rating' => $review->rating,
                'comment' => $review->comment,
                'date' => $review->created_at->toDateString(),
                'verified_purchase' => true,
                'photos' => $review->approvedMedia->map->toStorefrontArray()->values(),
            ];
        });

        $schemaReviews = $reviews->take(5)->map(fn (array $review) => [
            '@type' => 'Review',
            'author' => ['@type' => 'Person', 'name' => $review['user']],
            'reviewRating' => ['@type' => 'Rating', 'ratingValue' => (string) $review['rating'], 'bestRating' => 5, 'worstRating' => 1],
            'reviewBody' => (string) $review['comment'],
            'datePublished' => $review['date'],
        ])->values()->all();

        if ($schemaReviews !== []) {
            JsonLd::addValue('review', $schemaReviews);
        }

        $relatedProducts = Product::with('defaultVariant')
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('status', 'active')
            ->limit(4)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'price' => $p->defaultVariant?->price ?? 0,
                'image' => $p->defaultVariant?->main_image_url ?? '/images/product.png',
            ]);

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
            'compliance' => $this->compliance(),
        ]);
    }

    /**
     * Manufacturer block of the product page (GPSR, Regulation (EU) 2023/988). Each compliance.* setting falls back to
     * the store's own identity (the shop is treated as the manufacturer / EU importer until the admin fills them in).
     *
     * @return array{manufacturer_name: string|null, manufacturer_address: string|null, manufacturer_email: string|null, eu_responsible_person: string|null}
     */
    private function compliance(): array
    {
        $first = function (string ...$keys): ?string {
            foreach ($keys as $key) {
                $value = trim((string) settings($key, ''));

                if ($value !== '') {
                    return $value;
                }
            }

            return null;
        };

        return [
            'manufacturer_name' => $first('compliance.manufacturer_name', 'store.legal_name', 'store.name'),
            'manufacturer_address' => $first('compliance.manufacturer_address', 'store.address'),
            'manufacturer_email' => $first('compliance.manufacturer_email', 'contact.support_email'),
            'eu_responsible_person' => $first('compliance.eu_responsible_person'),
        ];
    }

    private function getProductBadge(Product $product): ?string
    {
        if ($product->compare_price && $product->compare_price > $product->price) {
            $discountPercent = round((($product->compare_price - $product->price) / $product->compare_price) * 100);
            if ($discountPercent >= 20) {
                return 'Sale';
            }
        }

        if ($product->created_at && $product->created_at->diffInDays(now()) <= 30) {
            return 'New';
        }

        if ($product->average_rating >= 4.5 && $product->review_count >= 50) {
            return 'Best Seller';
        }

        return null;
    }

    private function getFullImagePath(string $imagePath, int $productId): string
    {
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            return $imagePath;
        }

        return Storage::url("products/{$productId}/{$imagePath}");
    }

    private function imageExists(?string $imageUrl): bool
    {
        if (! $imageUrl) {
            return false;
        }

        if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return true;
        }

        if (str_starts_with($imageUrl, '/storage/')) {
            $filePath = str_replace('/storage/', '', $imageUrl);

            return \Storage::disk('public')->exists($filePath);
        }

        $publicPath = public_path($imageUrl);

        return file_exists($publicPath);
    }
}
