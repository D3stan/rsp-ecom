<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Size;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = Product::with(['category', 'size'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Admin/Products/Index', [
            'products' => $products
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
}
