# Product Variants Design Document

## Overview

This document describes the implementation of product variants to support "modello faro" (headlight models like rugged/carbon) as true product variants with independent pricing, stock, and images.

## Background

From [docs/fixes.md](../fixes.md) point #3:
> The modello faro should be managed as **variante prodotto/annuncio** (can influence price and stock), not as a simple visual filter.

## Requirements

### Functional Requirements

1. **Every product has at least one variant** - Even single products are treated as having one variant (UI hides selector when only 1 variant exists)
2. **Variant-specific attributes:**
   - Absolute price (not relative/adjustment)
   - Independent stock quantity
   - Independent image gallery
   - Independent description
   - SKU suffix combined with base SKU
3. **Shared across variants:**
   - Product name, slug, category
   - Reviews
   - Shipping dimensions (size_id)
   - SEO metadata
4. **Frontend behavior:**
   - Variant selector visible only when >1 variant
   - Changing variant updates: images, price, stock display, description
   - Default variant shown on page load
5. **Admin behavior:**
   - Unified product creation/edit flow
   - Variant manager with drag-to-reorder
   - Image upload per variant
   - First variant automatically marked as default

### Non-Functional Requirements

- Backward compatibility during migration
- No data loss
- Clean URLs (no variant in path, optional query param)

## Architecture

### Database Schema

#### New Table: `product_variants`

| Field | Type | Description |
|-------|------|-------------|
| `id` | bigint unsigned PK | Auto-increment |
| `product_id` | bigint unsigned FK | References `products.id`, cascade delete |
| `name` | varchar(255) | Variant name (e.g., "Rugoso", "Carbon") |
| `sku_suffix` | varchar(50) | Suffix appended to base_sku (e.g., "-RUG") |
| `description` | text | Variant-specific description (supports HTML) |
| `price` | decimal(10,2) | Absolute price in EUR |
| `stock_quantity` | int | Available stock for this variant |
| `images` | json | Array of image filenames |
| `is_default` | boolean | True for the variant shown by default |
| `sort_order` | int | Display order in selector |
| `status` | enum | 'active' or 'inactive' |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Indexes:**
- `product_variants_product_id_index` on `product_id`
- `product_variants_is_default_index` on `is_default`
- `product_variants_sort_order_index` on `sort_order`

**Unique Constraints:**
- Composite unique on (`product_id`, `name`) - no duplicate variant names per product

#### Modified Table: `products`

**Add:**
| Field | Type | Description |
|-------|------|-------------|
| `base_sku` | varchar(255) | SKU prefix for all variants |

**Remove (migrated to variants):**
- `price` → moved to `product_variants.price`
- `stock_quantity` → moved to `product_variants.stock_quantity`
- `images` → moved to `product_variants.images`
- `description` → moved to `product_variants.description`
- `compare_price` → moved to `product_variants.compare_price`
- `sku` → replaced by `base_sku`

**Keep:**
- `id`, `name`, `slug`, `status`, `featured`
- `category_id`, `size_id` (shipping dimensions)
- `seo_title`, `seo_description`, `social_image_url`
- `created_at`, `updated_at`

#### Modified Table: `cart_items`

| Change | Field | Notes |
|--------|-------|-------|
| Add | `product_variant_id` | bigint unsigned, FK → product_variants.id, nullable initially |
| Keep | `size_id` | Shipping size reference |
| Keep | `product_id` | Keep for backward compatibility |

#### Modified Table: `order_items`

| Change | Field | Notes |
|--------|-------|-------|
| Add | `product_variant_id` | bigint unsigned, FK → product_variants.id, nullable initially |
| Keep | `product_id` | Keep for backward compatibility |

### Model Relationships

```php
// Product model
class Product extends Model
{
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

    // Accessor for backward compatibility during transition
    public function getPriceAttribute(): ?float
    {
        return $this->defaultVariant?->price;
    }
}

// ProductVariant model
class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'name', 'sku_suffix', 'description',
        'price', 'stock_quantity', 'images', 'is_default',
        'sort_order', 'status',
    ];

    protected $casts = [
        'images' => 'array',
        'price' => 'decimal:2',
        'is_default' => 'boolean',
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
        return collect($this->images)->map(fn($img) =>
            Storage::url("products/{$this->product_id}/variants/{$this->id}/{$img}")
        )->toArray();
    }
}
```

### File Storage Structure

```
storage/app/public/products/
├── {product_id}/
│   └── variants/
│       ├── {variant_id}/
│       │   ├── image1.jpg
│       │   ├── image2.jpg
│       │   └── ...
│       └── {variant_id}/
│           └── ...
```

## API/Controller Changes

### Product Controller (Admin)

**Create/Store:**
1. Validate base product data (name, slug, category, size, etc.)
2. Create product with `base_sku`
3. Create at least one variant with images, price, stock, description
4. Mark first variant as `is_default`

**Update:**
1. Update base product fields
2. Sync variants (create/update/delete based on form data)
3. Ensure at least one variant remains, exactly one is_default

**Validation Rules:**
```php
// Base product
'name' => 'required|string|max:255',
'base_sku' => 'nullable|string|max:255',
'category_id' => 'required|exists:categories,id',
'size_id' => 'required|exists:sizes,id',

// Variants (array, min:1)
'variants' => 'required|array|min:1',
'variants.*.name' => 'required|string|max:255',
'variants.*.sku_suffix' => 'nullable|string|max:50',
'variants.*.price' => 'required|numeric|min:0',
'variants.*.stock_quantity' => 'required|integer|min:0',
'variants.*.description' => 'nullable|string',
'variants.*.images' => 'nullable|array',
'variants.*.images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
'variants.*.is_default' => 'boolean',
'variants.*.sort_order' => 'integer',
```

### Public Product Controller

**Show:**
- Load product with `activeVariants` relationship
- Include computed fields for each variant:
  ```json
  {
    "id": 1,
    "name": "Faro LED Angel Eye",
    "slug": "faro-led-angel-eye",
    "category": {...},
    "variants": [
      {
        "id": 1,
        "name": "Rugoso",
        "price": 149.00,
        "stock_quantity": 15,
        "images": ["/storage/products/1/variants/1/img1.jpg", ...],
        "description": "Variant description...",
        "is_default": true
      },
      {
        "id": 2,
        "name": "Carbon",
        "price": 179.00,
        "stock_quantity": 8,
        "images": [...],
        "description": "Variant description...",
        "is_default": false
      }
    ],
    "reviews": [...],
    "shipping": {...}
  }
  ```

### Cart Controller

**Add to Cart:**
- Accept `variant_id` instead of just `product_id`
- Validate variant belongs to product
- Use variant's price and stock

**Cart Display:**
- Show variant name alongside product name
- Use variant images in cart

## Frontend Changes

### Product Page (React/Inertia)

**State Management:**
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
  category: Category;
  variants: ProductVariant[];
  reviews: Review[];
}

// Component state
const [selectedVariantId, setSelectedVariantId] = useState<number>(
  product.variants.find(v => v.is_default)?.id ?? product.variants[0].id
);
const selectedVariant = product.variants.find(v => v.id === selectedVariantId)!;
```

**UI Behavior:**
1. **Variant Selector:**
   - Only render if `variants.length > 1`
   - Dropdown with variant names
   - Show stock status in option ("Rugoso - 15 disponibili")

2. **On Variant Change:**
   - Reset image carousel to first image of new variant
   - Update price display with animation
   - Update stock display
   - Replace description content

3. **Add to Cart:**
   - POST with `product_id` and `variant_id`
   - Disable if `selectedVariant.stock_quantity === 0`

### Admin Product Form

**Two-Step Layout:**

```
┌─ Step 1: Product Information ──────────┐
│  Name, Category, Size, SEO, etc.       │
└────────────────────────────────────────┘

┌─ Step 2: Variants ─────────────────────┐
│                                        │
│  [+ Add Variant]                       │
│                                        │
│  ┌─ Variant Card ──────────────────┐   │
│  │  ☰ Drag handle                  │   │
│  │  Name: [_________]              │   │
│  │  SKU: BASE[_____]               │   │
│  │  Price: [_____]  Stock: [_____] │   │
│  │                                  │   │
│  │  Images: [upload area]           │   │
│  │  [thumb] [thumb] [thumb]         │   │
│  │                                  │   │
│  │  Description: [textarea]         │   │
│  │                                  │   │
│  │  [✓] Default  [🗑 Delete]        │   │
│  └──────────────────────────────────┘   │
│                                        │
└────────────────────────────────────────┘
```

**Validation:**
- Minimum 1 variant required
- Exactly one variant must be marked as default
- All variants must have name, price, and stock

## Data Migration Plan

### Migration Files

1. **2026_03_06_000001_create_product_variants_table.php**
   - Create `product_variants` table

2. **2026_03_06_000002_add_variant_id_to_cart_items.php**
   - Add `product_variant_id` to `cart_items` (nullable)

3. **2026_03_06_000003_add_variant_id_to_order_items.php**
   - Add `product_variant_id` to `order_items` (nullable)

4. **2026_03_06_000004_migrate_product_data_to_variants.php**
   - Add `base_sku` to `products`
   - For each product, create a default variant with existing data
   - Move images to new structure

5. **2026_03_06_000005_remove_legacy_product_columns.php**
   - Remove `price`, `stock_quantity`, `images`, `description`, `compare_price`, `sku`
   - Rename `base_sku` to `sku` if desired (optional)

### Data Migration Logic

```php
// In migration file #4
Product::chunk(100, function ($products) {
    foreach ($products as $product) {
        // Create default variant
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Standard', // or extract from product name if pattern exists
            'sku_suffix' => '',
            'description' => $product->description,
            'price' => $product->price,
            'stock_quantity' => $product->stock_quantity,
            'images' => $product->images ?? [],
            'is_default' => true,
            'sort_order' => 0,
            'status' => 'active',
        ]);

        // Move images to new directory structure
        if (!empty($product->images)) {
            $oldPath = "products/{$product->id}";
            $newPath = "products/{$product->id}/variants/{$variant->id}";

            foreach ($product->images as $image) {
                if (Storage::disk('public')->exists("{$oldPath}/{$image}")) {
                    Storage::disk('public')->move(
                        "{$oldPath}/{$image}",
                        "{$newPath}/{$image}"
                    );
                }
            }
        }

        // Set base_sku
        $product->update(['base_sku' => $product->sku]);
    }
});
```

## Backward Compatibility

During the transition period (between migrations):

1. **Product model accessors** provide fallback to default variant values
2. **Cart/Order controllers** accept both old (product-only) and new (product+variant) requests
3. **Frontend** can handle products with or without variants array

## Testing Checklist

- [ ] Migration runs successfully on existing data
- [ ] All existing products have at least one variant after migration
- [ ] Product images accessible after migration
- [ ] Admin can create product with single variant
- [ ] Admin can create product with multiple variants
- [ ] Admin can reorder variants
- [ ] Admin can set default variant
- [ ] Frontend shows variant selector when >1 variant
- [ ] Frontend hides variant selector when 1 variant
- [ ] Variant change updates images, price, stock, description
- [ ] Add to cart uses correct variant
- [ ] Cart displays variant name
- [ ] Order records variant_id
- [ ] Stock decrements on order for correct variant

## Rollback Plan

If critical issues occur:

1. Restore database from backup (pre-migration)
2. Revert code changes via git
3. Clear caches (config, route, view)

## Future Enhancements

- Variant-specific SEO (title, description)
- Bulk price/stock update via CSV
- Variant-level discounts
- Variant inventory alerts
- Variant image zoom/gallery improvements

---

**Document Version:** 1.0
**Created:** 2026-03-06
**Author:** Claude
**Status:** Approved for Implementation
