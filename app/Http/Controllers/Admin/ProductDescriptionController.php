<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FileUploadConfigService;
use App\Services\ImageUploadService;
use App\Services\MarkdownRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Support endpoints of the markdown description editor (Admin MarkdownEditor.tsx).
 */
class ProductDescriptionController extends Controller
{
    /**
     * Render markdown with the same renderer the product page uses.
     */
    public function preview(Request $request, MarkdownRenderer $markdown): JsonResponse
    {
        $validated = $request->validate(['markdown' => 'nullable|string|max:'.MarkdownRenderer::MAX_LENGTH]);

        return response()->json(['html' => $markdown->toHtml($validated['markdown'] ?? null)]);
    }

    /**
     * Store a photo for use inside a description and return its URL.
     */
    public function image(Request $request, ImageUploadService $images): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|'.FileUploadConfigService::getFileValidationRule(),
        ]);

        return response()->json(['url' => $images->uploadDescriptionImage($request->file('image'))]);
    }
}
