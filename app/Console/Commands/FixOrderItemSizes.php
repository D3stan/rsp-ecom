<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrderItem;
use App\Models\Size;

class FixOrderItemSizes extends Command
{
    protected $signature = 'fix:order-item-sizes';
    protected $description = 'Add size_id to order items that are missing it';

    public function handle()
    {
        $this->info('Fixing order item sizes...');

        $orderItems = OrderItem::whereNull('size_id')->get();
        $this->info('Found ' . $orderItems->count() . ' order items without size_id');

        $sizes = Size::all();
        if ($sizes->isEmpty()) {
            $this->error('No sizes found in database!');
            return 1;
        }

        $updated = 0;

        foreach ($orderItems as $orderItem) {
            // Try to get size from product first
            if ($orderItem->product_id && $orderItem->product && $orderItem->product->size_id) {
                $orderItem->update(['size_id' => $orderItem->product->size_id]);
                $this->line("Updated order item {$orderItem->id} with size from product");
            } else {
                // Assign random size if no product size available
                $randomSize = $sizes->random();
                $orderItem->update(['size_id' => $randomSize->id]);
                $this->line("Updated order item {$orderItem->id} with random size: {$randomSize->name}");
            }
            $updated++;

            // Progress indicator
            if ($updated % 50 == 0) {
                $this->info("Progress: {$updated} items updated...");
            }
        }

        $this->info("Finished updating {$updated} order items!");

        // Verify results
        $remaining = OrderItem::whereNull('size_id')->count();
        $this->info("Order items without size remaining: {$remaining}");

        return 0;
    }
}
