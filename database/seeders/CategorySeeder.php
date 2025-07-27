<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Electronics',
                'slug' => 'electronics',
                'description' => 'Phones, laptops, and electronic accessories',
                'image' => '/images/categories/electronics.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Clothing',
                'slug' => 'clothing',
                'description' => 'Men\'s and women\'s apparel',
                'image' => '/images/categories/clothing.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Home & Garden',
                'slug' => 'home-garden',
                'description' => 'Furniture, appliances, and garden supplies',
                'image' => '/images/categories/home-garden.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Sports & Outdoors',
                'slug' => 'sports-outdoors',
                'description' => 'Sports equipment and outdoor gear',
                'image' => '/images/categories/sports-outdoors.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Books & Media',
                'slug' => 'books-media',
                'description' => 'Books, movies, music, and digital media',
                'image' => '/images/categories/books-media.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Health & Beauty',
                'slug' => 'health-beauty',
                'description' => 'Health supplements, beauty products, and personal care',
                'image' => '/images/categories/health-beauty.jpg',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
