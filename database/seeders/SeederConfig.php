<?php

namespace Database\Seeders;

/**
 * Seeding Configuration
 * 
 * Single point to control the volume of data generation
 * Following the principle of clean, maintainable code
 */
class SeederConfig
{
    /**
     * Master volume multiplier
     * Increase this to generate more test data
     */
    public const VOLUME_MULTIPLIER = 1;

    /**
     * Base quantities for each entity
     * All quantities are multiplied by VOLUME_MULTIPLIER
     */
    public const BASE_QUANTITIES = [
        'users' => 25,              // Customer users (excluding admin)
        'products' => 60,           // Products across all categories
        'addresses_per_user' => 2,  // Average addresses per user
        'orders' => 40,             // Total orders
        'order_items_per_order' => 3, // Average items per order
        'reviews' => 80,            // Product reviews
        'wishlists' => 30,          // Wishlist entries
        'pages' => 8,               // CMS pages
    ];

    /**
     * Order status distribution (percentages)
     */
    public const ORDER_STATUS_DISTRIBUTION = [
        'delivered' => 70,
        'shipped' => 15,
        'processing' => 10,
        'pending' => 4,
        'cancelled' => 1,
    ];

    /**
     * EU Cities and Countries for realistic addresses
     */
    public const EU_LOCATIONS = [
        'Germany' => ['Berlin', 'Munich', 'Hamburg', 'Frankfurt', 'Cologne'],
        'France' => ['Paris', 'Lyon', 'Marseille', 'Nice', 'Toulouse'],
        'Italy' => ['Rome', 'Milan', 'Naples', 'Turin', 'Florence'],
        'Spain' => ['Madrid', 'Barcelona', 'Valencia', 'Seville', 'Bilbao'],
        'Netherlands' => ['Amsterdam', 'Rotterdam', 'The Hague', 'Utrecht', 'Eindhoven'],
        'Belgium' => ['Brussels', 'Antwerp', 'Ghent', 'Bruges', 'Leuven'],
        'Austria' => ['Vienna', 'Salzburg', 'Innsbruck', 'Graz', 'Linz'],
        'Sweden' => ['Stockholm', 'Gothenburg', 'Malmö', 'Uppsala', 'Västerås'],
        'Denmark' => ['Copenhagen', 'Aarhus', 'Odense', 'Aalborg', 'Esbjerg'],
        'Finland' => ['Helsinki', 'Espoo', 'Tampere', 'Vantaa', 'Oulu'],
    ];

    /**
     * Product categories with realistic price ranges
     */
    public const CATEGORY_PRICE_RANGES = [
        'Electronics' => ['min' => 29.99, 'max' => 1999.99],
        'Clothing' => ['min' => 9.99, 'max' => 299.99],
        'Home & Garden' => ['min' => 14.99, 'max' => 899.99],
        'Sports & Outdoors' => ['min' => 19.99, 'max' => 599.99],
        'Books & Media' => ['min' => 4.99, 'max' => 79.99],
        'Health & Beauty' => ['min' => 7.99, 'max' => 149.99],
    ];

    /**
     * Get calculated quantity for a given entity
     */
    public static function getQuantity(string $entity): int
    {
        $base = self::BASE_QUANTITIES[$entity] ?? 1;
        return (int) ($base * self::VOLUME_MULTIPLIER);
    }

    /**
     * Get a random EU location
     */
    public static function getRandomEuLocation(): array
    {
        $country = array_rand(self::EU_LOCATIONS);
        $cities = self::EU_LOCATIONS[$country];
        $city = $cities[array_rand($cities)];
        
        return [
            'country' => $country,
            'city' => $city,
        ];
    }

    /**
     * Get price range for a category
     */
    public static function getPriceRange(string $categoryName): array
    {
        return self::CATEGORY_PRICE_RANGES[$categoryName] ?? ['min' => 10.00, 'max' => 100.00];
    }
}
