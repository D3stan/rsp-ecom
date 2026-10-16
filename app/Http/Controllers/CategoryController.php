<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Category landing URLs live on the product list (/products?category=slug); keep old links working.
     */
    public function show(string $slug, Request $request): RedirectResponse
    {
        Category::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return redirect()->route('products', ['category' => $slug] + $request->query(), 301);
    }
}
