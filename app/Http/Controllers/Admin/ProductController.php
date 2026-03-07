<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;
use App\Models\Size;
use App\Services\FileUploadConfigService;
use Illuminate\Support\Facades\Log;
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
            'uploadLimits' => [
                'maxFileSize' => FileUploadConfigService::getMaxFileUploadSizeFormatted(),
                'maxFileSizeBytes' => FileUploadConfigService::getMaxFileUploadSize(),
                'maxFilesPerVariant' => 10,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Log::info('Product creation with variants started');

        try {
            $this->checkStorageSetup();

            $fileUploadsEnabled = ini_get('file_uploads') && FileUploadConfigService::getMaxFileUploadSize() > 0;

            $validationRules = [
                'name' => 'required|string|max:255',
                'category_id' => 'required|exists:categories,id',
                'size_id' => 'required|exists:sizes,id',
                'status' => 'required|in:active,inactive,draft',
                'featured' => 'boolean',
                'base_sku' => 'nullable|string|max:255',
                'seo_title' => 'nullable|string|max:255',
                'seo_description' => 'nullable|string|max:500',
                'variants' => 'required|array|min:1',
                'variants.*.name' => 'required|string|max:255',
                'variants.*.sku_suffix' => 'nullable|string|max:50',
                'variants.*.price' => 'required|numeric|min:0',
                'variants.*.stock_quantity' => 'required|integer|min:0',
                'variants.*.description' => 'nullable|string',
                'variants.*.is_default' => 'boolean',
                'variants.*.sort_order' => 'integer',
            ];

            if ($fileUploadsEnabled) {
                $validationRules['variants.*.images'] = 'nullable|array|max:10';
                $validationRules['variants.*.images.*'] = 'image|mimes:jpeg,png,jpg,gif,webp|' . FileUploadConfigService::getFileValidationRule();
            }

            $validated = $request->validate($validationRules);

            DB::beginTransaction();

            // Get price from the first variant for the main product
            $firstVariant = $validated['variants'][0];

            $productData = [
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'category_id' => $validated['category_id'],
                'size_id' => $validated['size_id'],
                'status' => $validated['status'],
                'featured' => $validated['featured'] ?? false,
                'base_sku' => $validated['base_sku'] ?: $this->generateSku($validated['name']),
                'seo_title' => $validated['seo_title'] ?? null,
                'seo_description' => $validated['seo_description'] ?? null,
                'price' => $firstVariant['price'],
                'stock_quantity' => $firstVariant['stock_quantity'],
                'sku' => $validated['base_sku'] ?: $this->generateSku($validated['name']),
            ];

            $product = Product::create($productData);

            // Create variants
            $hasDefault = false;
            foreach ($validated['variants'] as $index => $variantData) {
                $isDefault = !$hasDefault && ($variantData['is_default'] ?? $index === 0);
                if ($isDefault) $hasDefault = true;

                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'name' => $variantData['name'],
                    'sku_suffix' => $variantData['sku_suffix'] ?? '',
                    'price' => $variantData['price'],
                    'stock_quantity' => $variantData['stock_quantity'],
                    'description' => $variantData['description'] ?? null,
                    'is_default' => $isDefault,
                    'sort_order' => $variantData['sort_order'] ?? $index,
                    'status' => 'active',
                    'images' => [],
                ]);

                // Handle images
                $imageKey = "variant_images_{$index}";
                if ($fileUploadsEnabled && $request->hasFile($imageKey)) {
                    $this->handleVariantImageUploads($variant, $request->file($imageKey));
                }
            }

            DB::commit();

            return redirect()->route('admin.products.index')
                ->with('success', 'Product created with ' . count($validated['variants']) . ' variant(s).');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product creation failed', ['error' => $e->getMessage()]);
            if (isset($product)) $product->delete();
            return back()->withInput()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Check if storage is properly configured
     */
    private function checkStorageSetup(): void
    {
        $storagePublicPath = storage_path('app/public');
        $publicStoragePath = public_path('storage');
        
        // Check if storage/app/public directory exists and is writable
        if (!is_dir($storagePublicPath)) {
            throw new \Exception('Storage directory does not exist. Please create: ' . $storagePublicPath);
        }
        
        if (!is_writable($storagePublicPath)) {
            throw new \Exception('Storage directory is not writable. Please fix permissions for: ' . $storagePublicPath);
        }
        
        // Check if storage link exists (only if file uploads are enabled)
        if (ini_get('file_uploads') && !file_exists($publicStoragePath)) {
            \Log::warning('Storage link does not exist, but file uploads are enabled', [
                'public_storage_path' => $publicStoragePath,
                'storage_public_path' => $storagePublicPath
            ]);
            // Don't throw an exception here, just log the warning
            // The system can still work without the symlink for image display
        }
        
        // Ensure products directory exists
        $productStoragePath = storage_path('app/public/products');
        if (!is_dir($productStoragePath)) {
            mkdir($productStoragePath, 0755, true);
            \Log::info('Created products directory', ['path' => $productStoragePath]);
        }
    }

    /**
     * Generate a unique SKU based on product name
     */
    private function generateSku(string $productName): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $productName), 0, 6));
        if (strlen($base) < 3) $base = 'PROD' . $base;
        return $base . '-' . time();
    }

    /**
     * Handle image uploads for a product variant
     */
    private function handleVariantImageUploads(ProductVariant $variant, array $images): void
    {
        $directory = "products/{$variant->product_id}/variants/{$variant->id}";
        Storage::disk('public')->makeDirectory($directory);

        $uploadedImages = [];
        foreach ($images as $image) {
            $filename = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
            $path = $image->storeAs($directory, $filename, 'public');
            if ($path) $uploadedImages[] = $filename;
        }

        $variant->update(['images' => $uploadedImages]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        $product->load(['variants', 'category', 'size']);

        return Inertia::render('Admin/Products/Edit', [
            'product' => $product,
            'categories' => Category::where('is_active', true)->get(),
            'sizes' => Size::all(),
            'uploadLimits' => [
                'maxFileSize' => FileUploadConfigService::getMaxFileUploadSizeFormatted(),
                'maxFileSizeBytes' => FileUploadConfigService::getMaxFileUploadSize(),
                'maxFilesPerVariant' => 10,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        // Debug what we're actually receiving
        \Log::info('===== DEBUGGING PRODUCT UPDATE =====');
        \Log::info('All request data:', ['data' => $request->all()]);
        \Log::info('Request input name:', ['name' => $request->input('name')]);
        \Log::info('Request has files:', ['has_files' => $request->hasFile('new_images')]);
        \Log::info('Request files count:', ['files' => $request->file('new_images') ? count($request->file('new_images')) : 0]);
        
        // Check if this is the issue - no data is being received
        if (empty($request->all())) {
            \Log::error('No request data received!');
            return response()->json(['error' => 'No data received'], 422);
        }

        // Simplified validation for debugging
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|string', // Temporarily accept as string
            'compare_price' => 'nullable|string',
            'stock_quantity' => 'required|string', // Temporarily accept as string
            'sku' => 'required|string|unique:products,sku,' . $product->id,
            'status' => 'required|in:active,inactive,draft',
            'featured' => 'nullable',
            'category_id' => 'required|string', // Temporarily accept as string
            'size_id' => 'required|string', // Temporarily accept as string
            'new_images' => 'nullable|array|max:' . FileUploadConfigService::getMaxFileUploads(),
            'new_images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|' . FileUploadConfigService::getFileValidationRule(),
            'remove_images' => 'nullable|array',
        ]);

        // Convert string values to proper types
        $validated['price'] = (float) $validated['price'];
        $validated['stock_quantity'] = (int) $validated['stock_quantity'];
        $validated['category_id'] = (int) $validated['category_id'];
        $validated['size_id'] = (int) $validated['size_id'];
        
        if (isset($validated['compare_price']) && $validated['compare_price'] !== '') {
            $validated['compare_price'] = (float) $validated['compare_price'];
        } else {
            $validated['compare_price'] = null;
        }

        // Handle featured field explicitly
        $validated['featured'] = $request->has('featured') ? (bool)$request->input('featured') : false;

        $validated['slug'] = Str::slug($validated['name']);

        \Log::info('Validated data:', ['validated' => $validated]);

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
        try {
            \Log::info('Starting image upload process', [
                'product_id' => $product->id,
                'image_count' => count($images),
                'storage_path' => storage_path('app/public'),
                'is_writable' => is_writable(storage_path('app/public'))
            ]);

            foreach ($images as $index => $image) {
                \Log::info("Processing image {$index}", [
                    'original_name' => $image->getClientOriginalName(),
                    'mime_type' => $image->getMimeType(),
                    'size' => $image->getSize(),
                    'extension' => $image->getClientOriginalExtension()
                ]);
                
                // Generate unique filename
                $filename = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
                
                // Ensure the directory exists
                $directory = "products/{$product->id}";
                $fullPath = storage_path("app/public/{$directory}");
                
                if (!Storage::disk('public')->exists($directory)) {
                    Storage::disk('public')->makeDirectory($directory);
                    \Log::info("Created directory", ['directory' => $directory, 'full_path' => $fullPath]);
                }
                
                // Check if directory is writable
                if (!is_writable($fullPath)) {
                    \Log::error("Directory not writable", ['directory' => $fullPath]);
                    throw new \Exception("Directory not writable: {$fullPath}");
                }
                
                // Store the image
                $path = $image->storeAs($directory, $filename, 'public');
                
                if ($path) {
                    // Verify file was actually created
                    $storedFilePath = storage_path("app/public/{$path}");
                    if (file_exists($storedFilePath)) {
                        \Log::info("File verified on disk", ['path' => $storedFilePath]);
                        
                        // Add to product's images array
                        $product->addImage($filename);
                        \Log::info("Image stored successfully", ['filename' => $filename, 'path' => $path]);
                    } else {
                        \Log::error("File not found on disk after storage", ['expected_path' => $storedFilePath]);
                        throw new \Exception("File was not properly stored: {$filename}");
                    }
                } else {
                    \Log::error("Failed to store image", ['filename' => $filename]);
                    throw new \Exception("Failed to store image: {$filename}");
                }
            }
            
            \Log::info('Image upload process completed successfully');
        } catch (\Exception $e) {
            \Log::error('Image upload failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
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

    /**
     * Store a review for the product
     */
    public function storeReview(Request $request, Product $product)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|max:1000',
            'user_name' => 'required|string|max:255',
        ]);

        // Create a temporary user or use existing logic
        // For now, we'll just store the review with user_name
        $review = $product->reviews()->create([
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
            'user_id' => auth()->id(), // You might want to handle this differently
            'user_name' => $validated['user_name'], // Add this field to reviews table if needed
        ]);

        return redirect()->back()->with('success', 'Review added successfully.');
    }
}
