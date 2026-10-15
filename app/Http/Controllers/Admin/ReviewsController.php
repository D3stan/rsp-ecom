<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ReviewsController extends Controller
{
    /**
     * Display a listing of reviews for moderation
     */
    public function index(Request $request): Response
    {
        $query = Review::with(['user', 'product', 'media'])
            ->orderBy('created_at', 'desc');

        // Apply filters - default to pending reviews
        if ($request->filled('status')) {
            if ($request->status === 'approved') {
                $query->approved();
            } elseif ($request->status === 'pending') {
                $query->pending();
            } elseif ($request->status === 'photos') {
                // Photos always wait for moderation, also on reviews whose text was auto-approved
                $query->whereHas('media', fn ($media) => $media->where('status', ReviewMedia::STATUS_PENDING));
            }
            // If status is empty string, show all
        } else {
            // Default to pending reviews when no status filter is applied
            $query->pending();
        }

        if ($request->filled('rating')) {
            $query->rating((int) $request->rating);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                               ->orWhere('email', 'like', "%{$search}%");
                  })
                  ->orWhereHas('product', function ($productQuery) use ($search) {
                      $productQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $reviews = $query->paginate(15);

        // Transform the pagination response to ensure proper structure
        $reviewsData = [
            'data' => collect($reviews->items())->map(fn (Review $review) => $this->withMedia($review))->all(),
            'meta' => [
                'total' => $reviews->total(),
                'per_page' => $reviews->perPage(),
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'from' => $reviews->firstItem(),
                'to' => $reviews->lastItem(),
            ],
            'links' => [],
        ];

        // Calculate KPI data
        $today = now()->startOfDay();
        $thisWeek = now()->startOfWeek();
        $thisMonth = now()->startOfMonth();

        $kpis = [
            'total' => Review::count(),
            'approved' => Review::approved()->count(),
            'pending' => Review::pending()->count(),
            'this_week' => Review::whereDate('created_at', '>=', $thisWeek)->count(),
            'average_rating' => (float) (Review::approved()->avg('rating') ?? 0),
            'pending_photos' => ReviewMedia::where('status', ReviewMedia::STATUS_PENDING)->count(),
        ];

        $filters = $request->only(['status', 'rating', 'search', 'date_from', 'date_to']);
        // Set default status to pending if not specified
        if (!$request->filled('status')) {
            $filters['status'] = 'pending';
        }

        return Inertia::render('Admin/Reviews/Index', [
            'reviews' => $reviewsData,
            'kpis' => $kpis,
            'filters' => $filters,
        ]);
    }

    /**
     * Display the specified review
     */
    public function show(Review $review): Response
    {
        $review->load(['user', 'product', 'media']);

        // Get navigation data for next/previous reviews
        $nextReview = Review::where('id', '>', $review->id)
            ->orderBy('id', 'asc')
            ->first();
        
        $previousReview = Review::where('id', '<', $review->id)
            ->orderBy('id', 'desc')
            ->first();

        return Inertia::render('Admin/Reviews/Show', [
            'review' => $this->withMedia($review),
            'navigation' => [
                'next' => $nextReview ? ['id' => $nextReview->id] : null,
                'previous' => $previousReview ? ['id' => $previousReview->id] : null,
            ],
        ]);
    }

    /**
     * Update review approval status
     */
    public function update(Request $request, Review $review)
    {
        try {
            $request->validate([
                'is_approved' => 'required|boolean',
            ]);

            // reject() also rejects the photos
            $request->boolean('is_approved') ? $review->approve() : $review->reject();

            $action = $request->is_approved ? 'approved' : 'rejected';
            
            Log::info("Review {$action}", [
                'review_id' => $review->id,
                'product_id' => $review->product_id,
                'user_id' => $review->user_id ?? 'guest',
            ]);

            return back()->with('success', "Review {$action} successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to update review status', [
                'review_id' => $review->id,
                'exception' => get_class($e),
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Failed to update review: ' . $e->getMessage());
        }
    }

    /**
     * Approve a review
     */
    public function approve(Review $review)
    {
        try {
            $review->approve();
            
            Log::info('Review approved', [
                'review_id' => $review->id,
                'product_id' => $review->product_id,
                'user_id' => $review->user_id ?? 'guest',
            ]);

            return back()->with('success', 'Review approved successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to approve review', [
                'review_id' => $review->id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Failed to approve review.');
        }
    }

    /**
     * Reject a review
     */
    public function reject(Review $review)
    {
        try {
            $review->reject();
            
            Log::info('Review rejected', [
                'review_id' => $review->id,
                'product_id' => $review->product_id,
                'user_id' => $review->user_id ?? 'guest',
            ]);

            return back()->with('success', 'Review rejected successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to reject review', [
                'review_id' => $review->id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Failed to reject review.');
        }
    }

    /**
     * Approve a review together with its pending photos
     */
    public function approveWithMedia(Review $review)
    {
        $review->approveWithMedia();

        Log::info('Review approved with photos', ['review_id' => $review->id]);

        return back()->with('success', __('reviews.admin.approved_with_photos'));
    }

    /**
     * Bulk approve/reject reviews
     */
    public function bulkUpdate(Request $request)
    {
        try {
            $request->validate([
                'review_ids' => 'required|array',
                'review_ids.*' => 'exists:reviews,id',
                'action' => 'required|string|in:approve,reject',
            ]);

            $isApproved = $request->action === 'approve';
            
            DB::transaction(function () use ($request, $isApproved) {
                Review::whereIn('id', $request->review_ids)->update(['is_approved' => $isApproved]);

                // Rejecting a review rejects its photos; approving leaves them to per-photo moderation
                if (! $isApproved) {
                    Review::rejectMediaOf($request->review_ids);
                }
            });

            $count = count($request->review_ids);
            $action = $request->action . 'd';

            return back()->with('success', "{$count} reviews {$action} successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to bulk update reviews', [
                'exception' => get_class($e),
                'error' => $e->getMessage(),
                'action' => $request->input('action'),
                'review_count' => count((array) $request->input('review_ids', [])),
            ]);
            return back()->with('error', 'Failed to update reviews: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified review
     */
    public function destroy(Review $review)
    {
        try {
            $reviewId = $review->id;
            $review->delete();

            Log::info('Review deleted', [
                'review_id' => $reviewId,
            ]);

            // not back(): from the Show page that would redirect to the deleted review (404) and lose the flash
            return redirect()->route('admin.reviews.index')->with('success', 'Review deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete review', [
                'review_id' => $review->id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Failed to delete review.');
        }
    }

    /**
     * A review for the moderation pages, with every photo (all states) and its URLs.
     *
     * @return array<string, mixed>
     */
    private function withMedia(Review $review): array
    {
        return array_merge($review->toArray(), [
            'media' => $review->media->map->toAdminArray()->values()->all(),
        ]);
    }
}
