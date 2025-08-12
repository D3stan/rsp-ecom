<?php

require 'vendor/autoload.php';
require 'bootstrap/app.php';

use App\Models\Size;

try {
    // Test with valid enum value
    $size = Size::create([
        'name' => 'Test Size Valid', 
        'length' => 10, 
        'width' => 10, 
        'height' => 10, 
        'box_type' => 'box', 
        'shipping_cost' => 5.99
    ]);
    echo "✅ Size created successfully with ID: " . $size->id . PHP_EOL;
    
    // Test with invalid enum value (should fail)
    try {
        $invalidSize = Size::create([
            'name' => 'Test Size Invalid', 
            'length' => 10, 
            'width' => 10, 
            'height' => 10, 
            'box_type' => 'standard', // This should fail
            'shipping_cost' => 5.99
        ]);
        echo "❌ This should not have been created: " . $invalidSize->id . PHP_EOL;
    } catch (Exception $e) {
        echo "✅ Invalid enum value correctly rejected: " . $e->getMessage() . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "❌ Error creating valid size: " . $e->getMessage() . PHP_EOL;
}
