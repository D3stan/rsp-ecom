<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewMedia;
use App\Services\FileUploadConfigService;
use App\Services\Reviews\InvalidReviewPhoto;
use App\Services\Reviews\ReviewPhotoProcessor;
use Artesaos\SEOTools\Facades\SEOMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ReviewController extends Controller
{
    public function index()
    {
        SEOMeta::setTitle('My Reviews – '.settings('store.name'));
        SEOMeta::setDescription('View and manage your product reviews.');

        $user = Auth::user();

        $reviews = $user->reviews()
            ->with(['product:id,name,slug,price,images'])
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        return Inertia::render('Reviews/Index', [
            'reviews' => $reviews,
        ]);
    }

    public function create(Product $product)
    {
        // Set noindex for review form
        SEOMeta::addMeta('robots', 'noindex,nofollow', 'name');
        SEOMeta::setTitle('Write Review for '.$product->name.' – '.settings('store.name'));

        $user = Auth::user();

        // Check if user has already reviewed this product
        $existingReview = $user->reviews()->where('product_id', $product->id)->first();

        if ($existingReview) {
            return redirect()->route('orders.index')->with('error', 'You have already reviewed this product.');
        }

        // Check if user has ordered this product
        $hasOrdered = $user->orders()
            ->whereHas('orderItems', function ($query) use ($product) {
                $query->where('product_id', $product->id);
            })
            ->where('status', 'delivered')
            ->exists();

        if (! $hasOrdered) {
            return redirect()->route('orders.index')->with('error', 'You can only review products you have purchased.');
        }

        return Inertia::render('Reviews/Create', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'image_url' => $product->image_url,
                'price' => (float) $product->price,
            ],
            // Limits for the photo picker (the server enforces the same ones)
            'photoLimits' => [
                'max_files' => ReviewPhotoProcessor::MAX_PHOTOS,
                'max_file_size' => FileUploadConfigService::getMaxFileUploadSize(),
                'max_request_size' => FileUploadConfigService::getMaxRequestSize(),
            ],
        ]);
    }

    public function store(Request $request, ReviewPhotoProcessor $photoProcessor)
    {
        $maxPhotoKb = max(100, (int) ceil(FileUploadConfigService::getMaxFileUploadSize() / 1024));

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'photos' => 'nullable|array|max:'.ReviewPhotoProcessor::MAX_PHOTOS,
            // mimetypes sniffs the content (finfo), not the file name or the browser's claim
            'photos.*' => ['file', 'mimetypes:'.implode(',', ReviewPhotoProcessor::MIME_TYPES), 'max:'.$maxPhotoKb],
        ], [
            'photos.array' => __('reviews.photos.invalid'),
            'photos.max' => __('reviews.photos.too_many', ['max' => ReviewPhotoProcessor::MAX_PHOTOS]),
            'photos.*.file' => __('reviews.photos.invalid'),
            'photos.*.mimetypes' => __('reviews.photos.invalid'),
            'photos.*.max' => __('reviews.photos.too_large'),
        ]);

        $user = Auth::user();

        // Check if user has already reviewed this product
        $existingReview = $user->reviews()->where('product_id', $request->product_id)->first();

        if ($existingReview) {
            return redirect()->route('orders.index')->with('error', 'You have already reviewed this product.');
        }

        // Check if user has ordered this product
        $hasOrdered = $user->orders()
            ->whereHas('orderItems', function ($query) use ($request) {
                $query->where('product_id', $request->product_id);
            })
            ->where('status', 'delivered')
            ->exists();

        if (! $hasOrdered) {
            return redirect()->route('orders.index')->with('error', 'You can only review products you have purchased.');
        }

        // Decode and re-encode every photo before anything is saved: an unreadable file is a validation error
        $photos = [];
        foreach (array_values($request->file('photos', [])) as $index => $file) {
            try {
                $photos[] = $photoProcessor->prepare($file);
            } catch (InvalidReviewPhoto) {
                throw ValidationException::withMessages(["photos.{$index}" => __('reviews.photos.invalid')]);
            }
        }

        // reviews.auto_approve: off = the review waits for moderation in the admin. Photos always wait for it.
        $autoApprove = (bool) settings('reviews.auto_approve', true);

        $written = [];
        try {
            DB::transaction(function () use ($request, $user, $autoApprove, $photos, $photoProcessor, &$written) {
                $review = Review::create([
                    'user_id' => $user->id,
                    'product_id' => $request->product_id,
                    'rating' => $request->rating,
                    'comment' => $request->comment,
                    'is_approved' => $autoApprove,
                ]);

                $photoProcessor->store($review, $photos, $written);
            });
        } catch (\Throwable $e) {
            // The rows rolled back: don't leave their files behind
            Storage::disk(ReviewMedia::DISK)->delete($written);

            throw $e;
        }

        $message = match (true) {
            ! $autoApprove => __('reviews.pending_moderation'),
            $photos !== [] => __('reviews.photos_pending'),
            default => __('reviews.thank_you'),
        };

        return redirect()->route('orders.index')->with('success', $message);
    }
}
