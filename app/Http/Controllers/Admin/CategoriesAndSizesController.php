<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Size;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoriesAndSizesController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $sizes = Size::withCount('products')
            ->orderBy('name')
            ->get()
            ->map(function ($size) {
                $size->volume = $size->volume; // Ensure volume accessor is called
                return $size;
            });

        $categoryStats = [
            'total_categories' => Category::count(),
            'active_categories' => Category::where('is_active', true)->count(),
            'products_with_categories' => Category::has('products')->count(),
            'total_products' => Category::withSum('products', 'stock_quantity')->get()->sum('products_sum_stock_quantity') ?? 0,
        ];

        $sizeStats = [
            'total_sizes' => Size::count(),
            'sizes_with_products' => Size::has('products')->count(),
            'total_products' => Size::withSum('products', 'stock_quantity')->get()->sum('products_sum_stock_quantity') ?? 0,
            'avg_shipping_cost' => Size::avg('shipping_cost') ?? 0,
        ];

        return Inertia::render('Admin/CategoriesAndSizes/Index', [
            'categories' => $categories,
            'sizes' => $sizes,
            'categoryStats' => $categoryStats,
            'sizeStats' => $sizeStats,
        ]);
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        
        // Auto-generate sort_order if not provided
        if (!isset($validated['sort_order'])) {
            $validated['sort_order'] = (Category::max('sort_order') ?? 0) + 1;
        }

        $category = Category::create($validated);

        return redirect()->back()->with('success', 'Category created successfully.');
    }

    public function updateCategory(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category->id)],
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $category->update($validated);

        return redirect()->back()->with('success', 'Category updated successfully.');
    }

    public function destroyCategory(Category $category)
    {
        // Check if category has products
        if ($category->products()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete category with associated products. Please move products to another category first.');
        }

        $category->delete();

        return redirect()->back()->with('success', 'Category deleted successfully.');
    }

    public function storeSize(Request $request)
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

    public function updateSize(Request $request, Size $size)
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

    public function destroySize(Size $size)
    {
        // Check if size has products
        if ($size->products()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete size with associated products. Please assign products to another size first.');
        }

        $size->delete();

        return redirect()->back()->with('success', 'Size deleted successfully.');
    }
}
