<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
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
        $query = Review::with(['user', 'product'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('status')) {
            if ($request->status === 'approved') {
                $query->approved();
            } elseif ($request->status === 'pending') {
                $query->pending();
            }
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

        // Transform the pagination response
        $reviewsData = [
            'data' => $reviews->items(),
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
        ];

        return Inertia::render('Admin/Reviews/Index', [
            'reviews' => $reviewsData,
            'kpis' => $kpis,
            'filters' => $request->only(['status', 'rating', 'search', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Display the specified review
     */
    public function show(Review $review): Response
    {
        $review->load(['user', 'product']);

        return Inertia::render('Admin/Reviews/Show', [
            'review' => $review,
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

            $review->update(['is_approved' => $request->is_approved]);

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
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
            
            Review::whereIn('id', $request->review_ids)
                ->update(['is_approved' => $isApproved]);

            $count = count($request->review_ids);
            $action = $request->action . 'd';

            Log::info("Bulk reviews {$action}", [
                'review_ids' => $request->review_ids,
                'action' => $request->action,
                'count' => $count,
            ]);

            return back()->with('success', "{$count} reviews {$action} successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to bulk update reviews', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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

            return back()->with('success', 'Review deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete review', [
                'review_id' => $review->id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Failed to delete review.');
        }
    }
}
