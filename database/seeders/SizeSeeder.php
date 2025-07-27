<?php

namespace Database\Seeders;

use App\Models\Size;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SizeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sizes = [
            [
                'name' => 'Small Electronics',
                'length' => 15.0,
                'width' => 10.0,
                'height' => 5.0,
                'box_type' => 'box',
                'shipping_cost' => 8.99,
            ],
            [
                'name' => 'Medium Electronics',
                'length' => 30.0,
                'width' => 20.0,
                'height' => 10.0,
                'box_type' => 'box',
                'shipping_cost' => 12.99,
            ],
            [
                'name' => 'Large Electronics',
                'length' => 50.0,
                'width' => 40.0,
                'height' => 20.0,
                'box_type' => 'box',
                'shipping_cost' => 24.99,
            ],
            [
                'name' => 'Small Apparel',
                'length' => 25.0,
                'width' => 20.0,
                'height' => 5.0,
                'box_type' => 'non_rigid_box',
                'shipping_cost' => 6.99,
            ],
            [
                'name' => 'Large Apparel',
                'length' => 35.0,
                'width' => 25.0,
                'height' => 8.0,
                'box_type' => 'non_rigid_box',
                'shipping_cost' => 9.99,
            ],
            [
                'name' => 'Books & Media',
                'length' => 20.0,
                'width' => 15.0,
                'height' => 3.0,
                'box_type' => 'box',
                'shipping_cost' => 4.99,
            ],
            [
                'name' => 'Home Accessories',
                'length' => 30.0,
                'width' => 30.0,
                'height' => 15.0,
                'box_type' => 'box',
                'shipping_cost' => 15.99,
            ],
            [
                'name' => 'Large Home Items',
                'length' => 100.0,
                'width' => 60.0,
                'height' => 40.0,
                'box_type' => 'box',
                'shipping_cost' => 49.99,
            ],
        ];

        foreach ($sizes as $size) {
            Size::create($size);
        }
    }
}
