# Product Variants Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement product variants with independent price, stock, images, and description per variant, where every product has at least one variant.

**Architecture:** Create `product_variants` table linked to products. Migrate existing product data (price, stock, images, description) to default variants. Update admin UI to manage variants. Update frontend to show variant selector and dynamic content.

**Tech Stack:** Laravel 11, PHP 8.3, MySQL, React/Inertia, TypeScript, Tailwind CSS

**Design Document:** [2026-03-06-product-variants-design.md](./2026-03-06-product-variants-design.md)

---

## Pre-Implementation Setup

### Task 0: Verify Environment

**Step 1: Check current database state**

Run: `php artisan migrate:status`
Expected: All existing migrations should be "Ran"

**Step 2: Run existing tests**

Run: `php artisan test`
Expected: All tests passing (or note current state)

**Step 3: Create feature branch**

Run:
```bash
git checkout -b feature/product-variants
git branch
```
Expected: Currently on `feature/product-variants` branch

---

## Phase 1: Database Schema

### Task 1: Create ProductVariant Model and Migration

**Files:**
- Create: `app/Models/ProductVariant.php`
- Create: `database/migrations/2026_03_06_100000_create_product_variants_table.php`

**Step 1: Create migration file**

Run: `php artisan make:migration create_product_variants_table`

**Step 2: Write migration code**

Edit: `database/migrations/2026_03_06_100000_create_product_variants_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('sku_suffix', 50)->default('');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('stock_quantity')->default(0);
            $table->json('images')->nullable();
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index('product_id');
            $table->index('is_default');
            $table->index('sort_order');
            $table->unique(['product_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
```

**Step 3: Write ProductVariant model**

Create: `app/Models/ProductVariant.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'sku_suffix',
        'description',
        'price',
        'stock_quantity',
        'images',
        'is_default',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'images' => 'array',
        'price' => 'decimal:2',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
        'stock_quantity' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getFullSkuAttribute(): string
    {
        return $this->product->base_sku . $this->sku_suffix;
    }

    public function getImageUrlsAttribute(): array
    {
        if (empty($this->images)) {
            return ['/images/product.png'];
        }

        return collect($this->images)->map(function ($image) {
            if (filter_var($image, FILTER_VALIDATE_URL)) {
                return $image;
            }
            return Storage::url("products/{$this->product_id}/variants/{$this->id}/{$image}");
        })->toArray();
    }

    public function getMainImageUrlAttribute(): string
    {
        $urls = $this->image_urls;
        return $urls[0] ?? '/images/product.png';
    }

    public function addImage(string $filename): void
    {
        $images = $this->images ?? [];
        $images[] = $filename;
        $this->update(['images' => $images]);
    }

    public function removeImage(string $filename): void
    {
        $images = collect($this->images ?? [])
            ->filter(fn($img) => $img !== $filename)
            ->values()
            ->toArray();

        $this->update(['images' => $images]);

        // Delete the file
        Storage::delete("products/{$this->product_id}/variants/{$this->id}/{$filename}");
    }

    public function clearImages(): void
    {
        if (!empty($this->images)) {
            foreach ($this->images as $image) {
                if (!filter_var($image, FILTER_VALIDATE_URL)) {
                    Storage::delete("products/{$this->product_id}/variants/{$this->id}/{$image}");
                }
            }
        }
        $this->update(['images' => []]);
    }

    public function isInStock(): bool
    {
        return $this->stock_quantity > 0 && $this->status === 'active';
    }

    public function canAddToCart(int $quantity = 1): bool
    {
        return $this->status === 'active' && $this->stock_quantity >= $quantity;
    }

    public function decrementStock(int $quantity): bool
    {
        if ($this->stock_quantity < $quantity) {
            return false;
        }
        return $this->decrement('stock_quantity', $quantity);
    }

    public function incrementStock(int $quantity): bool
    {
        return $this->increment('stock_quantity', $quantity);
    }
}
```

**Step 4: Run migration**

Run: `php artisan migrate`
Expected: Migration successful, `product_variants` table created

**Step 5: Commit**

```bash
git add database/migrations/2026_03_06_100000_create_product_variants_table.php app/Models/ProductVariant.php
git commit -m "feat: add ProductVariant model and migration"
```

---

### Task 2: Update Product Model Relationships

**Files:**
- Modify: `app/Models/Product.php`

**Step 1: Add base_sku to fillable and add variant relationships**

Edit: `app/Models/Product.php`

Add to `$fillable`:
```php
protected $fillable = [
    // ... existing fields ...
    'base_sku', // Add this
];
```

Add new relationships after existing ones:
```php
public function variants(): HasMany
{
    return $this->hasMany(ProductVariant::class);
}

public function activeVariants(): HasMany
{
    return $this->hasMany(ProductVariant::class)
        ->where('status', 'active')
        ->orderBy('sort_order');
}

public function defaultVariant(): HasOne
{
    return $this->hasOne(ProductVariant::class)
        ->where('is_default', true);
}
```

Add import at top:
```php
use Illuminate\Database\Eloquent\Relations\HasOne;
```

**Step 2: Add backward compatibility accessors**

Add to Product model after existing methods:

```php
/**
 * Backward compatibility accessors - delegate to default variant
 */

public function getPriceAttribute(): ?float
{
    return $this->defaultVariant?->price;
}

public function getStockQuantityAttribute(): ?int
{
    return $this->defaultVariant?->stock_quantity;
}

public function getImagesAttribute(): ?array
{
    return $this->defaultVariant?->images;
}

public function getDescriptionAttribute(): ?string
{
    return $this->defaultVariant?->description;
}

public function getSkuAttribute(): ?string
{
    return $this->defaultVariant?->full_sku;
}

public function getMainImageUrlAttribute(): string
{
    return $this->defaultVariant?->main_image_url ?? '/images/product.png';
}

public function getImageUrlsAttribute(): array
{
    return $this->defaultVariant?->image_urls ?? ['/images/product.png'];
}

public function getComparePriceAttribute(): ?float
{
    return $this->defaultVariant?->compare_price;
}

public function getDiscountPercentageAttribute(): ?float
{
    $defaultVariant = $this->defaultVariant;
    if (!$defaultVariant) {
        return null;
    }

    $comparePrice = $defaultVariant->compare_price ?? 0;
    $price = $defaultVariant->price ?? 0;

    if (!$comparePrice || $comparePrice <= $price) {
        return null;
    }

    return round((($comparePrice - $price) / $comparePrice) * 100, 2);
}
```

**Step 3: Commit**

```bash
git add app/Models/Product.php
git commit -m "feat: add variant relationships and backward compatibility accessors to Product model"
```

---

### Task 3: Add Variant ID to Cart Items

**Files:**
- Create: `database/migrations/2026_03_06_100001_add_variant_id_to_cart_items.php`

**Step 1: Create migration**

Run: `php artisan make:migration add_variant_id_to_cart_items`

**Step 2: Write migration**

Edit the created migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->onDelete('cascade');

            $table->index('product_variant_id');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropIndex(['product_variant_id']);
            $table->dropColumn('product_variant_id');
        });
    }
};
```

**Step 3: Run migration**

Run: `php artisan migrate`

**Step 4: Update CartItem model**

Edit: `app/Models/CartItem.php`

Add to `$fillable`:
```php
protected $fillable = [
    'cart_id',
    'product_id',
    'product_variant_id', // Add this
    'quantity',
    'price',
    'size_id',
];
```

Add relationship:
```php
public function productVariant(): BelongsTo
{
    return $this->belongsTo(ProductVariant::class);
}
```

**Step 5: Commit**

```bash
git add database/migrations/2026_03_06_100001_add_variant_id_to_cart_items.php app/Models/CartItem.php
git commit -m "feat: add product_variant_id to cart_items"
```

---

### Task 4: Add Variant ID to Order Items

**Files:**
- Create: `database/migrations/2026_03_06_100002_add_variant_id_to_order_items.php`

**Step 1: Create migration**

Run: `php artisan make:migration add_variant_id_to_order_items`

**Step 2: Write migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->onDelete('set null');

            $table->index('product_variant_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropIndex(['product_variant_id']);
            $table->dropColumn('product_variant_id');
        });
    }
};
```

**Step 3: Run migration**

Run: `php artisan migrate`

**Step 4: Update OrderItem model**

Edit: `app/Models/OrderItem.php`

Add to `$fillable`:
```php
protected $fillable = [
    'order_id',
    'product_id',
    'product_variant_id', // Add this
    'product_name',
    'quantity',
    'price',
    'total',
];
```

Add relationship:
```php
public function productVariant(): BelongsTo
{
    return $this->belongsTo(ProductVariant::class);
}
```

**Step 5: Commit**

```bash
git add database/migrations/2026_03_06_100002_add_variant_id_to_order_items.php app/Models/OrderItem.php
git commit -m "feat: add product_variant_id to order_items"
```

---

### Task 5: Create Data Migration for Existing Products

**Files:**
- Create: `database/migrations/2026_03_06_100003_migrate_products_to_variants.php`

**Step 1: Add base_sku column first**

Run: `php artisan make:migration add_base_sku_to_products`

Edit:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('base_sku')->nullable()->after('sku');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('base_sku');
        });
    }
};
```

Run: `php artisan migrate`

**Step 2: Create data migration**

Run: `php artisan make:migration migrate_product_data_to_variants`

Edit:
```php
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
                        // Create default variant with existing product data
                        $variant = ProductVariant::create([
                            'product_id' => $product->id,
                            'name' => 'Standard',
                            'sku_suffix' => '',
                            'description' => $product->getRawOriginal('description'),
                            'price' => $product->getRawOriginal('price') ?? 0,
                            'stock_quantity' => $product->getRawOriginal('stock_quantity') ?? 0,
                            'images' => $product->getRawOriginal('images') ?? [],
                            'is_default' => true,
                            'sort_order' => 0,
                            'status' => 'active',
                        ]);

                        // Move images to new directory structure
                        $this->migrateImages($product, $variant);

                        // Set base_sku from existing sku
                        $product->update(['base_sku' => $product->getRawOriginal('sku')]);

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

        $migratedImages = [];

        foreach ($images as $image) {
            // Skip if it's a URL (external image)
            if (filter_var($image, FILTER_VALIDATE_URL)) {
                $migratedImages[] = $image;
                continue;
            }

            $oldFilePath = "{$oldPath}/{$image}";
            $newFilePath = "{$newPath}/{$image}";

            if (Storage::disk('public')->exists($oldFilePath)) {
                // Copy file to new location
                Storage::disk('public')->copy($oldFilePath, $newFilePath);
                $migratedImages[] = $image;

                Log::info('Migrated image', [
                    'from' => $oldFilePath,
                    'to' => $newFilePath,
                ]);
            } else {
                Log::warning('Image not found during migration', [
                    'product_id' => $product->id,
                    'image' => $image,
                    'path' => $oldFilePath,
                ]);
            }
        }

        // Update variant with migrated images
        $variant->update(['images' => $migratedImages]);
    }

    public function down(): void
    {
        // This migration cannot be safely reversed as it involves data transformation
        // To rollback, restore from backup
        Log::warning('Rollback of product variant migration not supported - restore from backup');
    }
};
```

**Step 3: Run migration (TEST ON BACKUP FIRST)**

**WARNING:** Run this on a database backup first to verify!

Run: `php artisan migrate`

Verify in database:
- All products have a variant in `product_variants` table
- All variants have `is_default = true`
- Images exist in new path structure

**Step 4: Commit**

```bash
git add database/migrations/2026_03_06_100003_* database/migrations/2026_03_06_100004_*
git commit -m "feat: migrate existing products to variant structure"
```

---

## Phase 2: Backend Controllers

### Task 6: Update Admin Product Controller

**Files:**
- Modify: `app/Http/Controllers/Admin/ProductController.php`

**Step 1: Update create() method to remove upload limits temporarily**

The upload limits will need to be per-variant now. We'll handle this differently.

Edit `create()` method:
```php
public function create()
{
    return Inertia::render('Admin/Products/Create', [
        'categories' => Category::where('is_active', true)->get(),
        'sizes' => Size::all(),
        'uploadLimits' => [
            'maxFileSize' => FileUploadConfigService::getMaxFileUploadSizeFormatted(),
            'maxFileSizeBytes' => FileUploadConfigService::getMaxFileUploadSize(),
            'maxFilesPerVariant' => 10, // New - limit per variant
        ],
    ]);
}
```

**Step 2: Update store() method to handle variants**

Replace the entire store method:

```php
public function store(Request $request)
{
    Log::info('Product creation with variants started');

    try {
        $this->checkStorageSetup();

        $fileUploadsEnabled = ini_get('file_uploads') && FileUploadConfigService::getMaxFileUploadSize() > 0;

        $validationRules = [
            // Product base fields
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'size_id' => 'required|exists:sizes,id',
            'status' => 'required|in:active,inactive,draft',
            'featured' => 'boolean',
            'base_sku' => 'nullable|string|max:255',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',

            // Variants (minimum 1 required)
            'variants' => 'required|array|min:1',
            'variants.*.name' => 'required|string|max:255',
            'variants.*.sku_suffix' => 'nullable|string|max:50',
            'variants.*.price' => 'required|numeric|min:0',
            'variants.*.stock_quantity' => 'required|integer|min:0',
            'variants.*.description' => 'nullable|string',
            'variants.*.is_default' => 'boolean',
            'variants.*.sort_order' => 'integer',
        ];

        // Image validation per variant
        if ($fileUploadsEnabled) {
            $validationRules['variants.*.images'] = 'nullable|array|max:10';
            $validationRules['variants.*.images.*'] = 'image|mimes:jpeg,png,jpg,gif,webp|' . FileUploadConfigService::getFileValidationRule();
        }

        $validated = $request->validate($validationRules);

        DB::beginTransaction();

        // Create base product
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
        ];

        $product = Product::create($productData);

        // Create variants
        $hasDefault = false;
        foreach ($validated['variants'] as $index => $variantData) {
            $isDefault = !$hasDefault && ($variantData['is_default'] ?? $index === 0);
            if ($isDefault) {
                $hasDefault = true;
            }

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

            // Handle images for this variant
            if ($fileUploadsEnabled && !empty($variantData['images'])) {
                $this->handleVariantImageUploads($variant, $variantData['images']);
            }
        }

        DB::commit();

        Log::info('Product with variants created successfully', ['product_id' => $product->id]);

        return redirect()->route('admin.products.index')
            ->with('success', 'Product created successfully with ' . count($validated['variants']) . ' variant(s).');

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Product creation failed', ['error' => $e->getMessage()]);

        // Cleanup if product was created
        if (isset($product)) {
            $product->delete();
        }

        return back()->withInput()->withErrors(['error' => 'Failed to create product: ' . $e->getMessage()]);
    }
}
```

**Step 3: Add variant image upload handler**

Add new method:

```php
private function handleVariantImageUploads(ProductVariant $variant, array $images): void
{
    $directory = "products/{$variant->product_id}/variants/{$variant->id}";
    Storage::disk('public')->makeDirectory($directory);

    $uploadedImages = [];

    foreach ($images as $image) {
        $filename = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
        $path = $image->storeAs($directory, $filename, 'public');

        if ($path) {
            $uploadedImages[] = $filename;
        }
    }

    $variant->update(['images' => $uploadedImages]);
}
```

**Step 4: Update update() method**

The update method needs to sync variants (create/update/delete).

```php
public function update(Request $request, Product $product)
{
    Log::info('Product update with variants started', ['product_id' => $product->id]);

    $fileUploadsEnabled = ini_get('file_uploads') && FileUploadConfigService::getMaxFileUploadSize() > 0;

    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'category_id' => 'required|exists:categories,id',
        'size_id' => 'required|exists:sizes,id',
        'status' => 'required|in:active,inactive,draft',
        'featured' => 'boolean',
        'base_sku' => 'required|string|max:255',
        'seo_title' => 'nullable|string|max:255',
        'seo_description' => 'nullable|string|max:500',

        // Variants
        'variants' => 'required|array|min:1',
        'variants.*.id' => 'nullable|exists:product_variants,id',
        'variants.*.name' => 'required|string|max:255',
        'variants.*.sku_suffix' => 'nullable|string|max:50',
        'variants.*.price' => 'required|numeric|min:0',
        'variants.*.stock_quantity' => 'required|integer|min:0',
        'variants.*.description' => 'nullable|string',
        'variants.*.is_default' => 'boolean',
        'variants.*.sort_order' => 'integer',

        // Images to remove
        'remove_images' => 'nullable|array',
        'remove_images.*.variant_id' => 'required|exists:product_variants,id',
        'remove_images.*.filename' => 'required|string',
    ]);

    try {
        DB::beginTransaction();

        // Update product
        $product->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'category_id' => $validated['category_id'],
            'size_id' => $validated['size_id'],
            'status' => $validated['status'],
            'featured' => $validated['featured'] ?? false,
            'base_sku' => $validated['base_sku'],
            'seo_title' => $validated['seo_title'] ?? null,
            'seo_description' => $validated['seo_description'] ?? null,
        ]);

        // Get existing variant IDs
        $existingVariantIds = $product->variants->pluck('id')->toArray();
        $updatedVariantIds = [];

        // Process variants
        foreach ($validated['variants'] as $index => $variantData) {
            if (!empty($variantData['id'])) {
                // Update existing variant
                $variant = ProductVariant::find($variantData['id']);
                $variant->update([
                    'name' => $variantData['name'],
                    'sku_suffix' => $variantData['sku_suffix'] ?? '',
                    'price' => $variantData['price'],
                    'stock_quantity' => $variantData['stock_quantity'],
                    'description' => $variantData['description'] ?? null,
                    'is_default' => $variantData['is_default'] ?? false,
                    'sort_order' => $variantData['sort_order'] ?? $index,
                ]);
                $updatedVariantIds[] = $variant->id;
            } else {
                // Create new variant
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'name' => $variantData['name'],
                    'sku_suffix' => $variantData['sku_suffix'] ?? '',
                    'price' => $variantData['price'],
                    'stock_quantity' => $variantData['stock_quantity'],
                    'description' => $variantData['description'] ?? null,
                    'is_default' => $variantData['is_default'] ?? false,
                    'sort_order' => $variantData['sort_order'] ?? $index,
                    'status' => 'active',
                    'images' => [],
                ]);
                $updatedVariantIds[] = $variant->id;
            }

            // Handle new images for this variant
            $imageKey = "variant_images_{$index}";
            if ($fileUploadsEnabled && $request->hasFile($imageKey)) {
                $this->handleVariantImageUploads($variant, $request->file($imageKey));
            }
        }

        // Delete variants not in the update list (except if it would leave 0 variants)
        $variantsToDelete = array_diff($existingVariantIds, $updatedVariantIds);
        if (count($updatedVariantIds) === 0) {
            throw new \Exception('At least one variant is required');
        }

        foreach ($variantsToDelete as $variantId) {
            $variant = ProductVariant::find($variantId);
            if ($variant) {
                $variant->clearImages();
                Storage::deleteDirectory("products/{$product->id}/variants/{$variantId}");
                $variant->delete();
            }
        }

        // Ensure at least one default variant
        $hasDefault = $product->variants()->where('is_default', true)->exists();
        if (!$hasDefault && $product->variants()->count() > 0) {
            $firstVariant = $product->variants()->orderBy('sort_order')->first();
            $firstVariant->update(['is_default' => true]);
        }

        // Handle image removals
        if (!empty($validated['remove_images'])) {
            foreach ($validated['remove_images'] as $removeData) {
                $variant = ProductVariant::find($removeData['variant_id']);
                if ($variant && $variant->product_id === $product->id) {
                    $variant->removeImage($removeData['filename']);
                }
            }
        }

        DB::commit();

        return redirect()->route('admin.products.index')
            ->with('success', 'Product updated successfully.');

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Product update failed', ['error' => $e->getMessage()]);
        return back()->withInput()->withErrors(['error' => 'Failed to update product: ' . $e->getMessage()]);
    }
}
```

**Step 5: Update edit() method to include variants**

```php
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
```

**Step 6: Update show() method**

```php
public function show(Product $product)
{
    $product->load(['variants', 'category', 'size', 'reviews.user']);

    return Inertia::render('Admin/Products/Show', [
        'product' => $product,
    ]);
}
```

**Step 7: Add generateSku helper**

```php
private function generateSku(string $productName): string
{
    $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $productName), 0, 6));
    if (strlen($base) < 3) {
        $base = 'PROD' . $base;
    }
    return $base . '-' . time();
}
```

**Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/ProductController.php
git commit -m "feat: update admin ProductController for variants"
```

---

### Task 7: Update Public Product Controller

**Files:**
- Find and modify: Public product controller (likely `app/Http/Controllers/ProductController.php` or in `HomeController.php`)

**Step 1: Find the public product show method**

Run: `grep -r "public function show.*Product" app/Http/Controllers/ --include="*.php"`

**Step 2: Update to include variants**

Edit the product show method to include variant data:

```php
public function show(Product $product)
{
    if ($product->status !== 'active') {
        abort(404);
    }

    $product->load(['activeVariants', 'category', 'approvedReviews.user']);

    // Format product for frontend
    $productData = [
        'id' => $product->id,
        'name' => $product->name,
        'slug' => $product->slug,
        'category' => $product->category ? [
            'id' => $product->category->id,
            'name' => $product->category->name,
            'slug' => $product->category->slug,
        ] : null,
        'variants' => $product->activeVariants->map(fn($v) => [
            'id' => $v->id,
            'name' => $v->name,
            'price' => $v->price,
            'stock_quantity' => $v->stock_quantity,
            'images' => $v->image_urls,
            'description' => $v->description,
            'is_default' => $v->is_default,
        ]),
        'shipping' => [
            'size' => $product->size?->name,
        ],
    ];

    $reviews = $product->approvedReviews->map(fn($r) => [
        'id' => $r->id,
        'user' => $r->user?->name ?? 'Anonymous',
        'rating' => $r->rating,
        'comment' => $r->comment,
        'date' => $r->created_at->toDateString(),
    ]);

    // Get related products
    $relatedProducts = Product::with('defaultVariant')
        ->where('category_id', $product->category_id)
        ->where('id', '!=', $product->id)
        ->where('status', 'active')
        ->limit(4)
        ->get()
        ->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'price' => $p->defaultVariant?->price ?? 0,
            'image' => $p->defaultVariant?->main_image_url ?? '/images/product.png',
        ]);

    return Inertia::render('product', [
        'product' => $productData,
        'reviews' => $reviews,
        'relatedProducts' => $relatedProducts,
        'breadcrumb' => ['Home', 'Products', $product->category?->name, $product->name],
    ]);
}
```

**Step 3: Commit**

```bash
git add app/Http/Controllers/ProductController.php  # or wherever it is
git commit -m "feat: update public product controller to include variants"
```

---

### Task 8: Update Cart Controller

**Files:**
- Modify: `app/Http/Controllers/CartController.php`

**Step 1: Update add to cart to accept variant_id**

Edit the add method:

```php
public function add(Request $request)
{
    $validated = $request->validate([
        'product_id' => 'required|exists:products,id',
        'product_variant_id' => 'required|exists:product_variants,id',
        'quantity' => 'required|integer|min:1',
    ]);

    $variant = ProductVariant::findOrFail($validated['product_variant_id']);

    // Verify variant belongs to product
    if ($variant->product_id != $validated['product_id']) {
        return response()->json(['error' => 'Invalid variant for product'], 422);
    }

    // Check stock
    if (!$variant->canAddToCart($validated['quantity'])) {
        return response()->json([
            'error' => 'Not enough stock',
            'available' => $variant->stock_quantity,
        ], 422);
    }

    // Get or create cart
    $cart = $this->getOrCreateCart();

    // Check if item already in cart
    $cartItem = $cart->items()
        ->where('product_id', $validated['product_id'])
        ->where('product_variant_id', $validated['product_variant_id'])
        ->first();

    if ($cartItem) {
        // Update quantity
        $newQuantity = $cartItem->quantity + $validated['quantity'];
        if (!$variant->canAddToCart($newQuantity)) {
            return response()->json([
                'error' => 'Not enough stock for total quantity',
                'available' => $variant->stock_quantity,
            ], 422);
        }
        $cartItem->update(['quantity' => $newQuantity]);
    } else {
        // Create new cart item
        $cart->items()->create([
            'product_id' => $validated['product_id'],
            'product_variant_id' => $validated['product_variant_id'],
            'quantity' => $validated['quantity'],
            'price' => $variant->price,
            'size_id' => $variant->product->size_id,
        ]);
    }

    return response()->json([
        'success' => true,
        'cart_count' => $cart->items->sum('quantity'),
    ]);
}
```

**Step 2: Update cart display method**

```php
public function index()
{
    $cart = $this->getOrCreateCart();

    $items = $cart->items->map(function ($item) {
        $variant = $item->productVariant;
        $product = $item->product;

        return [
            'id' => $item->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'name' => $product->name,
            'variant_name' => $variant->name,
            'price' => $item->price,
            'quantity' => $item->quantity,
            'image' => $variant->main_image_url,
            'slug' => $product->slug,
            'max_quantity' => $variant->stock_quantity,
        ];
    });

    return response()->json([
        'items' => $items,
        'subtotal' => $items->sum(fn($i) => $i['price'] * $i['quantity']),
        'count' => $items->sum('quantity'),
    ]);
}
```

**Step 3: Commit**

```bash
git add app/Http/Controllers/CartController.php
git commit -m "feat: update CartController to handle product variants"
```

---

## Phase 3: Frontend Components

### Task 9: Create Variant Selector Component

**Files:**
- Create: `resources/js/components/VariantSelector.tsx`

```tsx
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface Variant {
    id: number;
    name: string;
    price: number;
    stock_quantity: number;
    is_default: boolean;
}

interface VariantSelectorProps {
    variants: Variant[];
    selectedVariantId: number;
    onVariantChange: (variantId: number) => void;
    label?: string;
}

export function VariantSelector({
    variants,
    selectedVariantId,
    onVariantChange,
    label = 'Variante',
}: VariantSelectorProps) {
    // Don't render if only one variant
    if (variants.length <= 1) {
        return null;
    }

    const selectedVariant = variants.find((v) => v.id === selectedVariantId);

    return (
        <div>
            <Label className="mb-3 block text-sm font-medium text-gray-900">{label}</Label>
            <Select value={selectedVariantId.toString()} onValueChange={(value) => onVariantChange(Number(value))}>
                <SelectTrigger className="w-full text-black">
                    <SelectValue placeholder="Seleziona variante" />
                </SelectTrigger>
                <SelectContent className="bg-white text-gray-900">
                    {variants.map((variant) => (
                        <SelectItem key={variant.id} value={variant.id.toString()}>
                            <span className="flex items-center justify-between gap-4">
                                <span>{variant.name}</span>
                                <span className="text-sm text-gray-500">
                                    {variant.stock_quantity > 0 ? `${variant.stock_quantity} disponibili` : 'Esaurito'}
                                </span>
                            </span>
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
```

**Step 1: Commit**

```bash
git add resources/js/components/VariantSelector.tsx
git commit -m "feat: add VariantSelector component"
```

---

### Task 10: Update Product Page

**Files:**
- Modify: `resources/js/pages/product.tsx`

**Step 1: Update Product interface**

```typescript
interface ProductVariant {
    id: number;
    name: string;
    price: number;
    stock_quantity: number;
    images: string[];
    description: string;
    is_default: boolean;
}

interface Product {
    id: number;
    name: string;
    slug: string;
    category: {
        id: number;
        name: string;
        slug: string;
    } | null;
    variants: ProductVariant[];
    shipping: {
        size: string;
    };
}
```

**Step 2: Update component state and logic**

Replace the existing state management:

```typescript
export default function Product() {
    const { product, reviews, relatedProducts, auth } = usePage<ProductPageProps>().props;
    const { addToast } = useToast();
    const isMobile = useIsMobile();
    const { t } = useTranslation();

    // Find default variant
    const defaultVariant = product.variants.find((v) => v.is_default) ?? product.variants[0];

    // State
    const [selectedVariantId, setSelectedVariantId] = useState<number>(defaultVariant.id);
    const [currentImageIndex, setCurrentImageIndex] = useState(0);
    const [quantity, setQuantity] = useState(1);
    const [isAddingToCart, setIsAddingToCart] = useState(false);
    const [isDescriptionOpen, setIsDescriptionOpen] = useState(false);
    const [isSpecsOpen, setIsSpecsOpen] = useState(false);
    const [isReviewsOpen, setIsReviewsOpen] = useState(false);

    // Get selected variant
    const selectedVariant = product.variants.find((v) => v.id === selectedVariantId)!;

    // Reset image index when variant changes
    useEffect(() => {
        setCurrentImageIndex(0);
    }, [selectedVariantId]);

    // Display images from selected variant
    const displayImages = selectedVariant.images.length > 0 ? selectedVariant.images : ['/images/product.png'];

    // Price is directly from variant
    const currentPrice = selectedVariant.price;

    // Rest of handlers remain similar...
```

**Step 3: Update JSX to use variant data**

Replace price display:
```tsx
{/* Price */}
<div className="mb-6 flex items-center space-x-3">
    <span className="text-3xl font-bold text-gray-900">{formatCurrency(currentPrice)}</span>
</div>
```

Replace image handling:
```tsx
{/* Image Carousel */}
<div className="space-y-4">
    <div className="relative aspect-square overflow-hidden rounded-2xl bg-gray-100">
        <img src={displayImages[currentImageIndex]} alt={product.name} className="h-full w-full object-cover" />
        {/* ... navigation buttons ... */}
    </div>
    {/* ... thumbnails ... */}
</div>
```

Add VariantSelector and update stock display:
```tsx
{/* Variant Selector */}
{product.variants.length > 1 && (
    <VariantSelector
        variants={product.variants}
        selectedVariantId={selectedVariantId}
        onVariantChange={setSelectedVariantId}
    />
)}

{/* Stock display */}
<span className="text-sm text-gray-600">
    {selectedVariant.stock_quantity} {t('product.in_stock')}
</span>
```

Update description to use variant description:
```tsx
{/* Description */}
{selectedVariant.description && (
    <div className="border-t border-gray-200 pt-6">
        <p className="leading-relaxed text-gray-700">{selectedVariant.description}</p>
    </div>
)}
```

Update Add to Cart to use variant:
```tsx
const handleAddToCart = async () => {
    if (isAddingToCart) return;

    setIsAddingToCart(true);

    try {
        const cartData: AddToCartData = {
            product_id: product.id,
            product_variant_id: selectedVariantId,
            quantity: quantity,
        };

        const response = await cartService.addToCart(cartData);
        // ... rest of handler
    } finally {
        setIsAddingToCart(false);
    }
};
```

Update disabled state:
```tsx
<Button
    onClick={handleAddToCart}
    disabled={selectedVariant.stock_quantity === 0 || isAddingToCart}
    className="h-12 w-full text-lg font-semibold"
>
    {/* ... */}
</Button>
```

**Step 4: Commit**

```bash
git add resources/js/pages/product.tsx
git commit -m "feat: update product page for variant selection"
```

---

### Task 11: Create Admin Variant Manager Component

**Files:**
- Create: `resources/js/components/admin/VariantManager.tsx`

```tsx
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Plus, Trash2, GripVertical, Star } from 'lucide-react';
import { useState } from 'react';

interface Variant {
    id?: number;
    name: string;
    sku_suffix: string;
    price: string;
    stock_quantity: string;
    description: string;
    is_default: boolean;
    sort_order: number;
    images: File[];
    existing_images?: string[];
}

interface VariantManagerProps {
    variants: Variant[];
    baseSku: string;
    onVariantsChange: (variants: Variant[]) => void;
    uploadLimits: {
        maxFileSize: string;
        maxFileSizeBytes: number;
        maxFilesPerVariant: number;
    };
}

export function VariantManager({ variants, baseSku, onVariantsChange, uploadLimits }: VariantManagerProps) {
    const [previews, setPreviews] = useState<Record<number, string[]>>({});

    const addVariant = () => {
        const newVariant: Variant = {
            name: '',
            sku_suffix: '',
            price: '',
            stock_quantity: '',
            description: '',
            is_default: variants.length === 0,
            sort_order: variants.length,
            images: [],
        };
        onVariantsChange([...variants, newVariant]);
    };

    const updateVariant = (index: number, updates: Partial<Variant>) => {
        const updated = [...variants];
        updated[index] = { ...updated[index], ...updates };

        // Ensure only one default
        if (updates.is_default) {
            updated.forEach((v, i) => {
                if (i !== index) v.is_default = false;
            });
        }

        onVariantsChange(updated);
    };

    const removeVariant = (index: number) => {
        if (variants.length <= 1) {
            alert('At least one variant is required');
            return;
        }

        const updated = variants.filter((_, i) => i !== index);

        // Ensure at least one default
        if (!updated.some((v) => v.is_default)) {
            updated[0].is_default = true;
        }

        onVariantsChange(updated);
    };

    const handleImageChange = (variantIndex: number, files: FileList | null) => {
        if (!files) return;

        const newFiles = Array.from(files);
        const currentImages = variants[variantIndex].images;
        const totalImages = currentImages.length + newFiles.length;

        if (totalImages > uploadLimits.maxFilesPerVariant) {
            alert(`Maximum ${uploadLimits.maxFilesPerVariant} images per variant`);
            return;
        }

        updateVariant(variantIndex, { images: [...currentImages, ...newFiles] });

        // Create previews
        const newPreviews = newFiles.map((f) => URL.createObjectURL(f));
        setPreviews((prev) => ({
            ...prev,
            [variantIndex]: [...(prev[variantIndex] || []), ...newPreviews],
        }));
    };

    const removeImage = (variantIndex: number, imageIndex: number, isExisting: boolean) => {
        if (isExisting) {
            // Handle removing existing image (will need separate logic in parent)
            return;
        }

        const variant = variants[variantIndex];
        const updatedImages = variant.images.filter((_, i) => i !== imageIndex);
        updateVariant(variantIndex, { images: updatedImages });

        setPreviews((prev) => ({
            ...prev,
            [variantIndex]: prev[variantIndex]?.filter((_, i) => i !== imageIndex) || [],
        }));
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>Varianti Prodotto</CardTitle>
                <Button type="button" onClick={addVariant} size="sm">
                    <Plus className="mr-2 h-4 w-4" />
                    Aggiungi Variante
                </Button>
            </CardHeader>
            <CardContent className="space-y-4">
                {variants.map((variant, index) => (
                    <Card key={index} className={variant.is_default ? 'border-blue-500' : ''}>
                        <CardContent className="space-y-4 pt-4">
                            <div className="flex items-start gap-2">
                                <GripVertical className="mt-2 h-5 w-5 cursor-move text-gray-400" />

                                <div className="grid flex-1 grid-cols-1 gap-4 md:grid-cols-2">
                                    {/* Name */}
                                    <div>
                                        <Label>Nome Variante *</Label>
                                        <Input
                                            value={variant.name}
                                            onChange={(e) => updateVariant(index, { name: e.target.value })}
                                            placeholder="Es. Rugoso, Carbon"
                                        />
                                    </div>

                                    {/* SKU */}
                                    <div>
                                        <Label>SKU</Label>
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm text-gray-500">{baseSku || 'BASE'}</span>
                                            <Input
                                                value={variant.sku_suffix}
                                                onChange={(e) => updateVariant(index, { sku_suffix: e.target.value })}
                                                placeholder="-RUG"
                                                className="flex-1"
                                            />
                                        </div>
                                    </div>

                                    {/* Price */}
                                    <div>
                                        <Label>Prezzo *</Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            value={variant.price}
                                            onChange={(e) => updateVariant(index, { price: e.target.value })}
                                            placeholder="0.00"
                                        />
                                    </div>

                                    {/* Stock */}
                                    <div>
                                        <Label>Quantità *</Label>
                                        <Input
                                            type="number"
                                            value={variant.stock_quantity}
                                            onChange={(e) => updateVariant(index, { stock_quantity: e.target.value })}
                                            placeholder="0"
                                        />
                                    </div>

                                    {/* Description */}
                                    <div className="md:col-span-2">
                                        <Label>Descrizione</Label>
                                        <Textarea
                                            value={variant.description}
                                            onChange={(e) => updateVariant(index, { description: e.target.value })}
                                            placeholder="Descrizione specifica per questa variante"
                                            rows={3}
                                        />
                                    </div>

                                    {/* Images */}
                                    <div className="md:col-span-2">
                                        <Label>Immagini (max {uploadLimits.maxFilesPerVariant})</Label>
                                        <Input
                                            type="file"
                                            multiple
                                            accept="image/*"
                                            onChange={(e) => handleImageChange(index, e.target.files)}
                                            disabled={variant.images.length >= uploadLimits.maxFilesPerVariant}
                                        />

                                        {/* Preview existing + new images */}
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {variant.existing_images?.map((img, imgIndex) => (
                                                <div key={`existing-${imgIndex}`} className="relative h-20 w-20">
                                                    <img src={img} alt="" className="h-full w-full rounded object-cover" />
                                                    <button
                                                        type="button"
                                                        onClick={() => removeImage(index, imgIndex, true)}
                                                        className="absolute -top-2 -right-2 rounded-full bg-red-500 p-1 text-white"
                                                    >
                                                        <Trash2 className="h-3 w-3" />
                                                    </button>
                                                </div>
                                            ))}
                                            {previews[index]?.map((preview, imgIndex) => (
                                                <div key={`new-${imgIndex}`} className="relative h-20 w-20">
                                                    <img src={preview} alt="" className="h-full w-full rounded object-cover" />
                                                    <button
                                                        type="button"
                                                        onClick={() => removeImage(index, imgIndex, false)}
                                                        className="absolute -top-2 -right-2 rounded-full bg-red-500 p-1 text-white"
                                                    >
                                                        <Trash2 className="h-3 w-3" />
                                                    </button>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </div>

                                {/* Actions */}
                                <div className="flex flex-col gap-2">
                                    <Button
                                        type="button"
                                        variant={variant.is_default ? 'default' : 'outline'}
                                        size="sm"
                                        onClick={() => updateVariant(index, { is_default: true })}
                                    >
                                        <Star className={`mr-2 h-4 w-4 ${variant.is_default ? 'fill-current' : ''}`} />
                                        {variant.is_default ? 'Default' : 'Set Default'}
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => removeVariant(index)}
                                        disabled={variants.length <= 1}
                                    >
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Elimina
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </CardContent>
        </Card>
    );
}
```

**Step 1: Commit**

```bash
git add resources/js/components/admin/VariantManager.tsx
git commit -m "feat: add VariantManager component for admin"
```

---

### Task 12: Update Admin Product Create Page

**Files:**
- Modify: `resources/js/pages/Admin/Products/Create.tsx`

**Step 1: Add VariantManager import and state**

```tsx
import { VariantManager } from '@/components/admin/VariantManager';

// Update form data interface
interface FormData {
    name: string;
    category_id: string;
    size_id: string;
    status: string;
    featured: boolean;
    base_sku: string;
    seo_title: string;
    seo_description: string;
    variants: Array<{
        name: string;
        sku_suffix: string;
        price: string;
        stock_quantity: string;
        description: string;
        is_default: boolean;
        sort_order: number;
        images: File[];
    }>;
}

// Update useForm initial data
const { data, setData, post, processing, errors } = useForm<FormData>({
    name: '',
    category_id: '',
    size_id: '',
    status: 'active',
    featured: false,
    base_sku: '',
    seo_title: '',
    seo_description: '',
    variants: [
        {
            name: 'Standard',
            sku_suffix: '',
            price: '',
            stock_quantity: '',
            description: '',
            is_default: true,
            sort_order: 0,
            images: [],
        },
    ],
});
```

**Step 2: Update form submission to handle variants**

```tsx
const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    // Validation
    if (!data.name.trim()) return;
    if (!data.category_id) return;
    if (!data.size_id) return;
    if (data.variants.some((v) => !v.name || !v.price || !v.stock_quantity)) {
        alert('Tutte le varianti devono avere nome, prezzo e quantità');
        return;
    }

    // Create FormData for file uploads
    const formData = new FormData();
    formData.append('name', data.name);
    formData.append('category_id', data.category_id);
    formData.append('size_id', data.size_id);
    formData.append('status', data.status);
    formData.append('featured', data.featured ? '1' : '0');
    formData.append('base_sku', data.base_sku);
    formData.append('seo_title', data.seo_title);
    formData.append('seo_description', data.seo_description);

    // Add variants as JSON (without images)
    const variantsForJson = data.variants.map((v, index) => ({
        name: v.name,
        sku_suffix: v.sku_suffix,
        price: v.price,
        stock_quantity: v.stock_quantity,
        description: v.description,
        is_default: v.is_default,
        sort_order: v.sort_order,
    }));
    formData.append('variants', JSON.stringify(variantsForJson));

    // Add images separately
    data.variants.forEach((variant, index) => {
        variant.images.forEach((file) => {
            formData.append(`variant_images_${index}[]`, file);
        });
    });

    post(route('admin.products.store'), {
        data: formData,
        forceFormData: true,
        onSuccess: () => {
            console.log('Product created successfully');
        },
        onError: (errors) => {
            console.log('Validation errors:', errors);
        },
    });
};
```

**Step 3: Add VariantManager to form JSX**

Replace the old "Product Images" card with VariantManager:

```tsx
{/* Variants */}
<VariantManager
    variants={data.variants}
    baseSku={data.base_sku}
    onVariantsChange={(variants) => setData('variants', variants)}
    uploadLimits={{
        maxFileSize: uploadLimits.maxFileSize,
        maxFileSizeBytes: uploadLimits.maxFileSizeBytes,
        maxFilesPerVariant: uploadLimits.maxFiles,
    }}
/>
```

Remove old price/stock/description fields from Basic Information section (they're now in variants).

**Step 4: Commit**

```bash
git add resources/js/pages/Admin/Products/Create.tsx
git commit -m "feat: update admin product create page for variants"
```

---

## Phase 4: Testing & Cleanup

### Task 13: Run Tests

**Files:**
- Run all tests

**Step 1: Run PHPUnit tests**

Run: `php artisan test`

Expected: Fix any failing tests related to product creation/updates

**Step 2: Commit fixes**

```bash
git add .
git commit -m "test: fix tests for product variants"
```

---

### Task 14: Manual Testing Checklist

Test these scenarios manually:

1. **Migration Test:**
   - [ ] All existing products have a "Standard" variant
   - [ ] All existing images accessible in new paths

2. **Admin Create:**
   - [ ] Create product with 1 variant
   - [ ] Create product with 3 variants
   - [ ] Upload images per variant
   - [ ] Set default variant

3. **Admin Edit:**
   - [ ] Edit product, add new variant
   - [ ] Edit product, remove variant
   - [ ] Change default variant
   - [ ] Remove and add images

4. **Frontend:**
   - [ ] Product with 1 variant shows no selector
   - [ ] Product with 2+ variants shows selector
   - [ ] Changing variant updates images
   - [ ] Changing variant updates price
   - [ ] Changing variant updates stock
   - [ ] Changing variant updates description
   - [ ] Add to cart uses correct variant

5. **Cart:**
   - [ ] Cart shows variant name
   - [ ] Cart shows correct price
   - [ ] Cart shows correct image

---

### Task 15: Final Cleanup Migration

**Files:**
- Create: `database/migrations/2026_03_06_100004_remove_legacy_product_columns.php`

**Note:** Only run this after everything is verified working!

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'price',
                'stock_quantity',
                'description',
                'images',
                'compare_price',
                'sku',
            ]);
        });

        // Make variant_id non-nullable in cart_items and order_items
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable(false)->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        // This is a destructive migration, rollback not supported
    }
};
```

---

## Summary

This implementation plan covers:

1. **Database:** New `product_variants` table, modified `products`, `cart_items`, `order_items`
2. **Models:** ProductVariant model, updated Product with backward compatibility
3. **Controllers:** Admin ProductController, Public ProductController, CartController
4. **Frontend:** VariantSelector component, updated Product page, VariantManager for admin
5. **Migration:** Data migration from existing products to variants

Total estimated tasks: 15
Estimated time: 4-6 hours

**Next Steps:**
1. Execute plan using `superpowers:executing-plans` skill
2. Test thoroughly before running cleanup migration
3. Deploy with zero-downtime strategy
