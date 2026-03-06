<?php

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            Product::chunk(100, function ($products) {
                foreach ($products as $product) {
                    try {
                        // Get raw attributes to avoid accessor interference
                        $rawPrice = $product->getRawOriginal('price') ?? 0;
                        $rawStock = $product->getRawOriginal('stock_quantity') ?? 0;
                        $rawDescription = $product->getRawOriginal('description');
                        $rawImages = $product->getRawOriginal('images') ?? [];
                        $rawSku = $product->getRawOriginal('sku') ?? '';

                        // Create default variant
                        $variant = ProductVariant::create([
                            'product_id' => $product->id,
                            'name' => 'Standard',
                            'sku_suffix' => '',
                            'description' => $rawDescription,
                            'price' => $rawPrice,
                            'stock_quantity' => $rawStock,
                            'images' => $rawImages,
                            'is_default' => true,
                            'sort_order' => 0,
                            'status' => 'active',
                        ]);

                        // Migrate images to new directory structure
                        $this->migrateImages($product, $variant);

                        // Set base_sku from existing sku
                        $product->update(['base_sku' => $rawSku]);

                        Log::info('Migrated product to variant', [
                            'product_id' => $product->id,
                            'variant_id' => $variant->id,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to migrate product', [
                            'product_id' => $product->id,
                            'error' => $e->getMessage(),
                        ]);
                        throw $e;
                    }
                }
            });
        });

        Log::info('Product to variant migration completed');
    }

    private function migrateImages(Product $product, ProductVariant $variant): void
    {
        $images = $product->getRawOriginal('images') ?? [];

        if (empty($images)) {
            return;
        }

        $oldPath = "products/{$product->id}";
        $newPath = "products/{$product->id}/variants/{$variant->id}";

        // Ensure new directory exists
        Storage::disk('public')->makeDirectory($newPath);

        foreach ($images as $image) {
            // Skip external URLs
            if (filter_var($image, FILTER_VALIDATE_URL)) {
                continue;
            }

            $oldFilePath = "{$oldPath}/{$image}";

            if (Storage::disk('public')->exists($oldFilePath)) {
                $filename = basename($image);
                $newFilePath = "{$newPath}/{$filename}";

                // Copy file to new location
                Storage::disk('public')->copy($oldFilePath, $newFilePath);

                Log::info('Migrated image', [
                    'from' => $oldFilePath,
                    'to' => $newFilePath,
                ]);
            } else {
                Log::warning('Image not found during migration', [
                    'product_id' => $product->id,
                    'image' => $image,
                ]);
            }
        }
    }

    public function down(): void
    {
        // This migration cannot be safely reversed
        Log::warning('Rollback of product variant migration not supported - restore from backup');
    }
};
