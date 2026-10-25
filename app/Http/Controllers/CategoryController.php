<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function show(string $slug, Request $request): RedirectResponse
    {
        Category::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return redirect()->route('products', ['category' => $slug] + $request->query(), 301);
    }
}
