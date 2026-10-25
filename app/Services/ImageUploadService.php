<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadService
{
    /** Shared folder for photos embedded in variant descriptions (the product may not exist yet when they are uploaded). */
    private const DESCRIPTION_DIRECTORY = 'products/descriptions';

    /** Detected mime type to stored extension: the only types a description photo may have. */
    private const DESCRIPTION_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

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
     * Upload a photo used inside a variant description. The extension comes from the detected file content through a
     * fixed allowlist, never from the client name; anything else is refused.
     */
    public function uploadDescriptionImage(UploadedFile $image): string
    {
        $extension = self::DESCRIPTION_EXTENSIONS[$image->getMimeType()] ?? throw new \InvalidArgumentException('Unsupported description image type');

        $disk = $this->descriptionDisk();
        $filename = now()->timestamp.'_'.Str::random(8).'.'.$extension;

        $image->storeAs(self::DESCRIPTION_DIRECTORY, $filename, $disk);

        return Storage::disk($disk)->url(self::DESCRIPTION_DIRECTORY.'/'.$filename);
    }

    /**
     * URL prefix every description photo starts with; the markdown renderer keeps only images under it.
     */
    public function descriptionImageBaseUrl(): string
    {
        return Storage::disk($this->descriptionDisk())->url(self::DESCRIPTION_DIRECTORY.'/');
    }

    private function descriptionDisk(): string
    {
        return config('filesystems.default') === 's3' ? 's3' : 'public';
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
