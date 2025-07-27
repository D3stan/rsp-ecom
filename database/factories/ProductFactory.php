<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Size;
use Database\Seeders\SeederConfig;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Get a random category to determine realistic pricing
        $category = Category::inRandomOrder()->first();
        $priceRange = SeederConfig::getPriceRange($category?->name ?? 'Electronics');
        
        $price = $this->faker->randomFloat(2, $priceRange['min'], $priceRange['max']);
        $hasComparePrice = $this->faker->boolean(30); // 30% chance of having compare price
        $comparePrice = $hasComparePrice ? $price + $this->faker->randomFloat(2, 10, 100) : null;
        
        $name = $this->generateProductName($category?->name ?? 'Electronics');
        $baseSlug = Str::slug($name);
        $uniqueSlug = $baseSlug . '-' . $this->faker->unique()->numberBetween(1000, 9999);
        
        return [
            'name' => $name,
            'slug' => $uniqueSlug,
            'description' => $this->generateProductDescription($category?->name ?? 'Electronics'),
            'price' => $price,
            'compare_price' => $comparePrice,
            'stock_quantity' => $this->faker->numberBetween(0, 150),
            'sku' => $this->generateSKU(),
            'images' => $this->generateProductImages(),
            'status' => $this->faker->randomElement(['active', 'active', 'active', 'inactive', 'draft']), // Mostly active
            'featured' => $this->faker->boolean(20), // 20% chance of being featured
            'category_id' => $category?->id ?? 1,
            'size_id' => Size::inRandomOrder()->first()?->id ?? 1,
        ];
    }

    /**
     * Generate realistic product names based on category
     */
    private function generateProductName(string $categoryName): string
    {
        $productTypes = [
            'Electronics' => [
                'Wireless Bluetooth Headphones',
                'Smart Watch',
                'USB-C Cable',
                'Portable Power Bank',
                'Bluetooth Speaker',
                'Smartphone Case',
                'Wireless Charger',
                'Gaming Mouse',
                'Mechanical Keyboard',
                'Laptop Stand',
                'HD Webcam',
                'Tablet Screen Protector',
            ],
            'Clothing' => [
                'Cotton T-Shirt',
                'Denim Jeans',
                'Wool Sweater',
                'Summer Dress',
                'Leather Jacket',
                'Running Shoes',
                'Baseball Cap',
                'Winter Coat',
                'Casual Sneakers',
                'Silk Scarf',
                'Cotton Hoodie',
                'Formal Shirt',
            ],
            'Home & Garden' => [
                'Garden Watering Can',
                'Ceramic Plant Pot',
                'LED Desk Lamp',
                'Throw Pillow',
                'Kitchen Utensil Set',
                'Wall Clock',
                'Storage Basket',
                'Picture Frame',
                'Candle Set',
                'Garden Tools Set',
                'Decorative Vase',
                'Area Rug',
            ],
            'Sports & Outdoors' => [
                'Yoga Mat',
                'Water Bottle',
                'Hiking Backpack',
                'Tennis Racket',
                'Basketball',
                'Camping Tent',
                'Fitness Tracker',
                'Running Shorts',
                'Sports Towel',
                'Bicycle Helmet',
                'Swimming Goggles',
                'Dumbbells Set',
            ],
            'Books & Media' => [
                'Fiction Novel',
                'Cookbook',
                'Travel Guide',
                'Art Book',
                'Biography',
                'Self-Help Book',
                'Children\'s Book',
                'Photography Book',
                'History Book',
                'Science Textbook',
                'Poetry Collection',
                'Comic Book',
            ],
            'Health & Beauty' => [
                'Face Moisturizer',
                'Vitamin Supplements',
                'Hair Shampoo',
                'Body Lotion',
                'Lip Balm',
                'Sunscreen',
                'Makeup Brush Set',
                'Nail Polish',
                'Face Mask',
                'Essential Oils',
                'Hand Cream',
                'Perfume',
            ],
        ];

        $types = $productTypes[$categoryName] ?? $productTypes['Electronics'];
        $baseName = $this->faker->randomElement($types);
        
        // Add brand-like prefix sometimes
        if ($this->faker->boolean(40)) {
            $brands = ['Premium', 'Pro', 'Elite', 'Classic', 'Modern', 'Eco', 'Smart', 'Ultra'];
            $baseName = $this->faker->randomElement($brands) . ' ' . $baseName;
        }
        
        return $baseName;
    }

    /**
     * Generate realistic product descriptions
     */
    private function generateProductDescription(string $categoryName): string
    {
        $features = [
            'Electronics' => [
                'High-quality materials',
                'Latest technology',
                'Energy efficient',
                'User-friendly design',
                'Long battery life',
                'Fast charging capability',
                'Wireless connectivity',
                'Compact and portable',
            ],
            'Clothing' => [
                'Comfortable fit',
                'Durable fabric',
                'Machine washable',
                'Breathable material',
                'Classic design',
                'Available in multiple colors',
                'Perfect for everyday wear',
                'High-quality stitching',
            ],
            'Home & Garden' => [
                'Elegant design',
                'Durable construction',
                'Easy to clean',
                'Weather resistant',
                'Functional and stylish',
                'Perfect for home decoration',
                'Easy assembly',
                'Space-saving design',
            ],
            'Sports & Outdoors' => [
                'Professional quality',
                'Lightweight and durable',
                'Weather resistant',
                'Ergonomic design',
                'Non-slip surface',
                'Easy to carry',
                'Suitable for all skill levels',
                'High performance',
            ],
            'Books & Media' => [
                'Engaging content',
                'Well-researched information',
                'Easy to read',
                'Beautifully illustrated',
                'Educational value',
                'Perfect for gift giving',
                'High-quality printing',
                'Comprehensive coverage',
            ],
            'Health & Beauty' => [
                'Natural ingredients',
                'Dermatologically tested',
                'Suitable for all skin types',
                'Long-lasting formula',
                'Pleasant fragrance',
                'Easy application',
                'Cruelty-free',
                'Paraben-free',
            ],
        ];

        $categoryFeatures = $features[$categoryName] ?? $features['Electronics'];
        $selectedFeatures = $this->faker->randomElements($categoryFeatures, $this->faker->numberBetween(2, 4));
        
        return implode('. ', $selectedFeatures) . '.';
    }

    /**
     * Generate realistic SKU
     */
    private function generateSKU(): string
    {
        return strtoupper($this->faker->lexify('???')) . '-' . $this->faker->numerify('####');
    }

    /**
     * Generate product images array
     */
    private function generateProductImages(): array
    {
        // Always include the default product image
        $images = ['product.png'];
        
        // Sometimes add additional placeholder images
        $additionalImages = $this->faker->numberBetween(0, 3);
        for ($i = 0; $i < $additionalImages; $i++) {
            $images[] = 'product.png'; // Using same image as placeholder
        }
        
        return $images;
    }

    /**
     * Create a featured product
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'featured' => true,
            'status' => 'active',
            'stock_quantity' => $this->faker->numberBetween(50, 150),
        ]);
    }

    /**
     * Create an out of stock product
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 0,
        ]);
    }

    /**
     * Create a discounted product
     */
    public function discounted(): static
    {
        return $this->state(function (array $attributes) {
            $price = $attributes['price'];
            return [
                'compare_price' => $price + $this->faker->randomFloat(2, 20, 100),
            ];
        });
    }
}
