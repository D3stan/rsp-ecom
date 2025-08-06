<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Size;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'size']);

        // Search functionality
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($category = $request->get('category')) {
            if ($category !== 'all') {
                $query->whereHas('category', function ($q) use ($category) {
                    $q->where('slug', $category);
                });
            }
        }

        // Status filter
        if ($status = $request->get('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        // Stock filter
        if ($stockFilter = $request->get('stock_filter')) {
            switch ($stockFilter) {
                case 'in-stock':
                    $query->where('stock_quantity', '>', 10);
                    break;
                case 'low-stock':
                    $query->where('stock_quantity', '>', 0)
                          ->where('stock_quantity', '<=', 10);
                    break;
                case 'out-of-stock':
                    $query->where('stock_quantity', 0);
                    break;
            }
        }

        // Sorting
        $sort = $request->get('sort', 'created_at');
        $direction = $request->get('direction', 'desc');
        
        $allowedSorts = ['name', 'price', 'stock_quantity', 'created_at', 'status'];
        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, $direction);
        }

        $products = $query->paginate(20)->withQueryString();

        // Add computed attributes to each product
        $products->getCollection()->transform(function ($product) {
            $product->main_image_url = $product->main_image_url;
            $product->discount_percentage = $product->discount_percentage;
            $product->average_rating = $product->average_rating;
            $product->review_count = $product->review_count;
            return $product;
        });

        // Calculate stats
        $stats = [
            'total_products' => Product::count(),
            'active_products' => Product::where('status', 'active')->count(),
            'low_stock_count' => Product::where('stock_quantity', '>', 0)
                                       ->where('stock_quantity', '<=', 10)->count(),
            'total_value' => Product::where('status', 'active')
                                   ->sum(DB::raw('price * stock_quantity')),
        ];

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'categories' => Category::where('is_active', true)->get(['id', 'name', 'slug']),
            'sizes' => Size::all(['id', 'name']),
            'filters' => $request->only(['search', 'category', 'status', 'stock_filter', 'sort', 'direction']),
            'stats' => $stats,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Admin/Products/Create', [
            'categories' => Category::where('is_active', true)->get(),
            'sizes' => Size::all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'sku' => 'required|string|unique:products',
            'status' => 'required|in:active,inactive,draft',
            'featured' => 'boolean',
            'category_id' => 'required|exists:categories,id',
            'size_id' => 'required|exists:sizes,id',
            'images' => 'nullable|array|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        // Create the product first
        $product = Product::create($validated);

        // Handle image uploads
        if ($request->hasFile('images')) {
            $this->handleImageUploads($product, $request->file('images'));
        }

        return redirect()->route('admin.products.index')
            ->with('success', 'Product created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        return Inertia::render('Admin/Products/Edit', [
            'product' => $product->load(['category', 'size']),
            'categories' => Category::where('is_active', true)->get(),
            'sizes' => Size::all(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'sku' => 'required|string|unique:products,sku,' . $product->id,
            'status' => 'required|in:active,inactive,draft',
            'featured' => 'boolean',
            'category_id' => 'required|exists:categories,id',
            'size_id' => 'required|exists:sizes,id',
            'new_images' => 'nullable|array|max:10',
            'new_images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'remove_images' => 'nullable|array',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        // Update product
        $product->update($validated);

        // Remove specified images
        if ($request->has('remove_images')) {
            foreach ($request->remove_images as $imageToRemove) {
                $product->removeImage($imageToRemove);
            }
        }

        // Handle new image uploads
        if ($request->hasFile('new_images')) {
            $this->handleImageUploads($product, $request->file('new_images'));
        }

        return redirect()->route('admin.products.index')
            ->with('success', 'Product updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        // Clear all images before deleting
        $product->clearImages();
        
        // Remove the product directory if empty
        Storage::deleteDirectory("products/{$product->id}");
        
        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('success', 'Product deleted successfully.');
    }

    /**
     * Handle multiple image uploads for a product
     */
    private function handleImageUploads(Product $product, array $images): void
    {
        foreach ($images as $image) {
            $filename = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
            
            // Store in products/{product_id}/ directory
            $image->storeAs("products/{$product->id}", $filename, 'public');
            
            // Add to product's images array
            $product->addImage($filename);
        }
    }

    /**
     * Remove a specific image from a product
     */
    public function removeImage(Product $product, string $filename)
    {
        $product->removeImage($filename);
        
        return response()->json(['success' => true]);
    }

    /**
     * Quick stock adjustment (+1 or -1)
     */
    public function quickStock(Request $request, Product $product)
    {
        $request->validate([
            'increment' => 'required|boolean',
        ]);

        if ($request->increment) {
            $product->increment('stock_quantity');
        } else {
            if ($product->stock_quantity > 0) {
                $product->decrement('stock_quantity');
            }
        }

        return back()->with('success', 'Stock updated successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        $product->load(['category', 'size', 'reviews.user']);
        
        // Add computed attributes
        $product->main_image_url = $product->main_image_url;
        $product->image_urls = $product->image_urls;
        $product->discount_percentage = $product->discount_percentage;
        $product->average_rating = $product->average_rating;
        $product->review_count = $product->review_count;

        return Inertia::render('Admin/Products/Show', [
            'product' => $product,
        ]);
    }
}
