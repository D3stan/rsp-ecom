<?php

use Illuminate\Support\Facades\Artisan;

Artisan::call('tinker', [], [
    '--execute' => '
        use App\Models\OrderItem;
        use App\Models\Size;
        
        echo "Fixing order item sizes...\n";
        
        $orderItems = OrderItem::whereNull("size_id")->get();
        echo "Found " . $orderItems->count() . " order items without size_id\n";
        
        foreach ($orderItems as $orderItem) {
            if ($orderItem->product_id && $orderItem->product && $orderItem->product->size_id) {
                $orderItem->update(["size_id" => $orderItem->product->size_id]);
                echo "Updated order item {$orderItem->id} with size from product\n";
            } else {
                $randomSize = Size::inRandomOrder()->first();
                if ($randomSize) {
                    $orderItem->update(["size_id" => $randomSize->id]);
                    echo "Updated order item {$orderItem->id} with random size\n";
                }
            }
        }
        
        echo "Finished!\n";
    '
]);
