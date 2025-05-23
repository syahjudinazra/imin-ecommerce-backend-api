<?php

namespace App\Http\Controllers\Api;

use App\Models\Review;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ReviewController extends Controller
{
    /**
     * Display a listing of reviews for a product.
     */
    public function index(Request $request, int $productId): JsonResponse
    {
        try {
            $product = Product::findOrFail($productId);

            $query = $product->reviews()->approved();

            // Filter by rating if specified
            if ($request->has('rating')) {
                $query->withRating($request->rating);
            }

            // Filter by verified purchases
            if ($request->boolean('verified_only')) {
                $query->verified();
            }

            // Sort options
            $sortBy = $request->get('sort', 'recent');
            switch ($sortBy) {
                case 'helpful':
                    $query->orderByHelpfulness();
                    break;
                case 'rating_high':
                    $query->orderBy('rating', 'desc');
                    break;
                case 'rating_low':
                    $query->orderBy('rating', 'asc');
                    break;
                case 'oldest':
                    $query->oldest();
                    break;
                default:
                    $query->latest();
            }

            $reviews = $query->paginate($request->get('per_page', 10));

            return response()->json([
                'success' => true,
                'data' => $reviews,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'rating' => $product->rating,
                    'review_count' => $product->review_count,
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }
    }

    /**
     * Store a newly created review.
     */
    public function store(Request $request, int $productId): JsonResponse
    {
        try {
            $product = Product::findOrFail($productId);

            $validator = Validator::make($request->all(), [
                'rating' => 'required|numeric|min:1|max:5',
                'title' => 'nullable|string|max:255',
                'comment' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if user already reviewed this product
            $existingReview = Review::where('product_id', $productId)
                ->where('user_id', Auth::id())
                ->first();

            if ($existingReview) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reviewed this product'
                ], 409);
            }

            $review = Review::create([
                'product_id' => $productId,
                'user_id' => Auth::id(),
                'rating' => $request->rating,
                'title' => $request->title,
                'comment' => $request->comment,
                'is_verified' => $this->isVerifiedPurchase($productId, Auth::id()),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Review created successfully',
                'data' => $review->load('user')
            ], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }
    }

    /**
     * Display the specified review.
     */
    public function show(int $reviewId): JsonResponse
    {
        try {
            $review = Review::with(['user', 'product'])->findOrFail($reviewId);

            return response()->json([
                'success' => true,
                'data' => $review
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found'
            ], 404);
        }
    }

    /**
     * Update the specified review.
     */
    public function update(Request $request, int $reviewId): JsonResponse
    {
        try {
            $review = Review::findOrFail($reviewId);

            // Check if user owns this review
            if ($review->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this review'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'rating' => 'required|numeric|min:1|max:5',
                'title' => 'nullable|string|max:255',
                'comment' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $review->update([
                'rating' => $request->rating,
                'title' => $request->title,
                'comment' => $request->comment,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Review updated successfully',
                'data' => $review->load('user')
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found'
            ], 404);
        }
    }

    /**
     * Remove the specified review.
     */
    public function destroy(int $reviewId): JsonResponse
    {
        try {
            $review = Review::findOrFail($reviewId);

            // Check if user owns this review or is admin
            if ($review->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this review'
                ], 403);
            }

            $review->delete();

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found'
            ], 404);
        }
    }

    /**
     * Mark review as helpful.
     */
    public function markHelpful(int $reviewId): JsonResponse
    {
        try {
            $review = Review::findOrFail($reviewId);
            $userId = Auth::id();

            if ($review->user_id === $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot mark your own review as helpful'
                ], 400);
            }

            $marked = $review->markHelpful($userId);

            return response()->json([
                'success' => true,
                'message' => $marked ? 'Review marked as helpful' : 'Already marked as helpful',
                'helpful_count' => $review->helpful_count
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found'
            ], 404);
        }
    }

    /**
     * Unmark review as helpful.
     */
    public function unmarkHelpful(int $reviewId): JsonResponse
    {
        try {
            $review = Review::findOrFail($reviewId);
            $userId = Auth::id();

            $unmarked = $review->unmarkHelpful($userId);

            return response()->json([
                'success' => true,
                'message' => $unmarked ? 'Review unmarked as helpful' : 'Was not marked as helpful',
                'helpful_count' => $review->helpful_count
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found'
            ], 404);
        }
    }

    /**
     * Get review statistics for a product.
     */
    public function statistics(int $productId): JsonResponse
    {
        try {
            $product = Product::findOrFail($productId);

            $reviews = $product->reviews()->approved();

            $stats = [
                'total_reviews' => $reviews->count(),
                'average_rating' => $product->rating,
                'rating_breakdown' => [
                    '5' => $reviews->clone()->withRating(5)->count(),
                    '4' => $reviews->clone()->withRating(4)->count(),
                    '3' => $reviews->clone()->withRating(3)->count(),
                    '2' => $reviews->clone()->withRating(2)->count(),
                    '1' => $reviews->clone()->withRating(1)->count(),
                ],
                'verified_reviews' => $reviews->clone()->verified()->count(),
                'recent_reviews' => $reviews->clone()->recent(30)->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }
    }

    /**
     * Get user's review for a specific product.
     */
    public function userReview(int $productId): JsonResponse
    {
        try {
            Product::findOrFail($productId);

            $review = Review::where('product_id', $productId)
                ->where('user_id', Auth::id())
                ->with('user')
                ->first();

            return response()->json([
                'success' => true,
                'data' => $review
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }
    }

    /**
     * Check if user has purchased the product (for verified reviews).
     */
    private function isVerifiedPurchase(int $productId, int $userId): bool
    {
        // This should check your orders/purchases table
        // For now, returning false - implement based on your order system

        // Example implementation:
        // return Order::where('user_id', $userId)
        //     ->whereHas('items', function($query) use ($productId) {
        //         $query->where('product_id', $productId);
        //     })
        //     ->where('status', 'completed')
        //     ->exists();

        return false;
    }
}
