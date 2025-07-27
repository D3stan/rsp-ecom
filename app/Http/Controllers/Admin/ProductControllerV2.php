<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Size;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProductControllerV2 extends Controller
{
    public function __construct(
        private ImageUploadService $imageService
    ) {}

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

        // Handle image uploads using the service
        if ($request->hasFile('images')) {
            $imageUrls = $this->imageService->uploadProductImages(
                $product->id, 
                $request->file('images')
            );
            
            // Store just the filenames in the database
            $filenames = collect($imageUrls)->map(fn($url) => basename($url))->toArray();
            $product->update(['images' => $filenames]);
        }

        return redirect()->route('admin.products.index')
            ->with('success', 'Product created successfully.');
    }

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
        $product->update($validated);

        // Remove specified images
        if ($request->has('remove_images')) {
            $imagesToRemove = collect($request->remove_images)->map(function($filename) use ($product) {
                return $product->getImageUrl($filename);
            })->toArray();
            
            $this->imageService->deleteProductImages($product->id, $imagesToRemove);
            
            // Update database
            $currentImages = collect($product->images ?? []);
            $remainingImages = $currentImages->diff($request->remove_images)->values()->toArray();
            $product->update(['images' => $remainingImages]);
        }

        // Handle new image uploads
        if ($request->hasFile('new_images')) {
            $newImageUrls = $this->imageService->uploadProductImages(
                $product->id, 
                $request->file('new_images')
            );
            
            $newFilenames = collect($newImageUrls)->map(fn($url) => basename($url))->toArray();
            $currentImages = $product->images ?? [];
            $allImages = array_merge($currentImages, $newFilenames);
            
            $product->update(['images' => $allImages]);
        }

        return redirect()->route('admin.products.index')
            ->with('success', 'Product updated successfully.');
    }
}
