<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Size;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SizesController extends Controller
{
    public function index()
    {
        $sizes = Size::withCount('products')
            ->orderBy('name')
            ->get();

        $stats = [
            'total_sizes' => Size::count(),
            'sizes_with_products' => Size::has('products')->count(),
            'total_products' => Size::withSum('products', 'stock_quantity')->get()->sum('products_sum_stock_quantity') ?? 0,
            'avg_shipping_cost' => Size::avg('shipping_cost') ?? 0,
        ];

        return Inertia::render('Admin/Sizes/Index', [
            'sizes' => $sizes,
            'stats' => $stats,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:sizes,name',
            'length' => 'required|numeric|min:0',
            'width' => 'required|numeric|min:0',
            'height' => 'required|numeric|min:0',
            'box_type' => 'nullable|string|max:100',
            'shipping_cost' => 'required|numeric|min:0',
        ]);

        $size = Size::create($validated);

        return redirect()->back()->with('success', 'Size created successfully.');
    }

    public function update(Request $request, Size $size)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:sizes,name,' . $size->id,
            'length' => 'required|numeric|min:0',
            'width' => 'required|numeric|min:0',
            'height' => 'required|numeric|min:0',
            'box_type' => 'nullable|string|max:100',
            'shipping_cost' => 'required|numeric|min:0',
        ]);

        $size->update($validated);

        return redirect()->back()->with('success', 'Size updated successfully.');
    }

    public function destroy(Size $size)
    {
        // Check if size has products
        if ($size->products()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete size with associated products. Please assign products to another size first.');
        }

        $size->delete();

        return redirect()->back()->with('success', 'Size deleted successfully.');
    }
}
