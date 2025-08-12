<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Size;

uses(RefreshDatabase::class);

test('size can be created with valid box_type enum values', function () {
    // Test with 'box' enum value
    $size1 = Size::create([
        'name' => 'Test Size Box',
        'length' => 10,
        'width' => 10,
        'height' => 10,
        'box_type' => 'box',
        'shipping_cost' => 5.99
    ]);
    
    expect($size1)->toBeInstanceOf(Size::class);
    expect($size1->box_type)->toBe('box');
    
    // Test with 'non_rigid_box' enum value
    $size2 = Size::create([
        'name' => 'Test Size Non-Rigid',
        'length' => 15,
        'width' => 12,
        'height' => 8,
        'box_type' => 'non_rigid_box',
        'shipping_cost' => 7.99
    ]);
    
    expect($size2)->toBeInstanceOf(Size::class);
    expect($size2->box_type)->toBe('non_rigid_box');
});

test('size creation fails with invalid box_type enum value', function () {
    expect(fn() => Size::create([
        'name' => 'Test Size Invalid',
        'length' => 10,
        'width' => 10,
        'height' => 10,
        'box_type' => 'standard', // Invalid enum value
        'shipping_cost' => 5.99
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
