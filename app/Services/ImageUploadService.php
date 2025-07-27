<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadService
{
    /**
     * Upload multiple images for a product
     */
    public function uploadProductImages(int $productId, array $images): array
    {
        $uploadedImages = [];
        
        foreach ($images as $image) {
            $uploadedImages[] = $this->uploadSingleImage($productId, $image);
        }
        
        return $uploadedImages;
    }
    
    /**
     * Upload a single image
     */
    public function uploadSingleImage(int $productId, UploadedFile $image): string
    {
        // Generate unique filename
        $filename = $this->generateFileName($image);
        
        // Define the path
        $path = "products/{$productId}/{$filename}";
        
        // Upload to cloud storage (S3/DO Spaces/etc.)
        if (config('filesystems.default') === 's3') {
            $image->storeAs("products/{$productId}", $filename, 's3');
            return Storage::disk('s3')->url($path);
        }
        
        // Fallback to local storage
        $image->storeAs("products/{$productId}", $filename, 'public');
        return Storage::disk('public')->url($path);
    }
    
    /**
     * Delete product images
     */
    public function deleteProductImages(int $productId, array $imageUrls): void
    {
        foreach ($imageUrls as $imageUrl) {
            $this->deleteImage($productId, $imageUrl);
        }
        
        // Clean up empty directory
        $this->cleanupDirectory($productId);
    }
    
    /**
     * Delete a single image
     */
    public function deleteImage(int $productId, string $imageUrl): void
    {
        // Extract filename from URL
        $filename = basename($imageUrl);
        $path = "products/{$productId}/{$filename}";
        
        if (config('filesystems.default') === 's3') {
            Storage::disk('s3')->delete($path);
        } else {
            Storage::disk('public')->delete($path);
        }
    }
    
    /**
     * Generate optimized images (thumbnails, etc.)
     */
    public function generateImageVariants(int $productId, UploadedFile $image): array
    {
        $variants = [];
        $originalName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $image->getClientOriginalExtension();
        
        // Original image
        $original = $this->uploadSingleImage($productId, $image);
        $variants['original'] = $original;
        
        // You could add image processing here with Intervention Image
        // $variants['thumbnail'] = $this->createThumbnail($productId, $image);
        // $variants['medium'] = $this->createMediumSize($productId, $image);
        
        return $variants;
    }
    
    /**
     * Generate a unique filename
     */
    private function generateFileName(UploadedFile $image): string
    {
        $timestamp = now()->timestamp;
        $random = Str::random(8);
        $extension = $image->getClientOriginalExtension();
        
        return "{$timestamp}_{$random}.{$extension}";
    }
    
    /**
     * Clean up empty directories
     */
    private function cleanupDirectory(int $productId): void
    {
        $directory = "products/{$productId}";
        
        if (config('filesystems.default') === 's3') {
            $files = Storage::disk('s3')->files($directory);
            if (empty($files)) {
                Storage::disk('s3')->deleteDirectory($directory);
            }
        } else {
            $files = Storage::disk('public')->files($directory);
            if (empty($files)) {
                Storage::disk('public')->deleteDirectory($directory);
            }
        }
    }
}
