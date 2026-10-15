<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Per-photo moderation of review photos (routes are scoped: the photo must belong to the review in the URL).
 */
class ReviewMediaController extends Controller
{
    public function approve(Review $review, ReviewMedia $media): RedirectResponse
    {
        $media->approve();
        Log::info('Review photo approved', ['review_id' => $review->id, 'media_id' => $media->id]);

        return back()->with('success', __('reviews.admin.photo_approved'));
    }

    public function reject(Review $review, ReviewMedia $media): RedirectResponse
    {
        $media->reject();
        Log::info('Review photo rejected', ['review_id' => $review->id, 'media_id' => $media->id]);

        return back()->with('success', __('reviews.admin.photo_rejected'));
    }

    /** "Feature on home page": only for approved photos. */
    public function feature(Request $request, Review $review, ReviewMedia $media): RedirectResponse
    {
        $featured = $request->validate(['featured' => 'required|boolean'])['featured'];
        $featured = filter_var($featured, FILTER_VALIDATE_BOOLEAN);

        if ($featured && $media->status !== ReviewMedia::STATUS_APPROVED) {
            return back()->with('error', __('reviews.admin.feature_needs_approval'));
        }

        $media->update(['featured' => $featured]);

        return back()->with('success', $featured ? __('reviews.admin.photo_featured') : __('reviews.admin.photo_unfeatured'));
    }
}
