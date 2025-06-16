<?php

namespace App\Http\Controllers\Api;

use DB;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;

class ReviewController extends Controller
{
    /**
     * Display a listing of reviews
     */
    public function index(Request $request)
    {
        $query = Review::with(['user:id,name', 'product:id,name,image']);

        // Add filters
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $reviews = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $reviews->items(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ]
        ]);
    }

    /**
     * Get detailed review by ID
     */
    public function show($id)
    {
        try {
            $review = Review::with([
                'user:id,name,email,avatar',
                'product:id,name,description,price,image,category_id',
                'product.category:id,name'
            ])->findOrFail($id);

            // Add additional review statistics
            $reviewStats = [
                'helpful_count' => $review->helpful_count ?? 0,
                'total_votes' => $review->total_votes ?? 0,
                'verified_purchase' => $review->verified_purchase ?? false,
                'review_images' => $review->images ?? [],
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'title' => $review->title,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at,
                    'updated_at' => $review->updated_at,
                    'user' => $review->user,
                    'product' => $review->product,
                    'stats' => $reviewStats,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found',
                'error' => $e->getMessage()
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Store a new review
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'required|string|max:255',
            'comment' => 'required|string|max:1000',
        ]);

        try {
            $review = Review::create([
                'user_id' => auth()->id(),
                'product_id' => $request->product_id,
                'rating' => $request->rating,
                'title' => $request->title,
                'comment' => $request->comment,
                'verified_purchase' => $this->checkVerifiedPurchase($request->product_id),
            ]);

            $review->load(['user:id,name', 'product:id,name']);

            return response()->json([
                'success' => true,
                'message' => 'Review created successfully',
                'data' => $review
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create review',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update an existing review
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'rating' => 'sometimes|integer|min:1|max:5',
            'title' => 'sometimes|string|max:255',
            'comment' => 'sometimes|string|max:1000',
        ]);

        try {
            $review = Review::findOrFail($id);

            // Check if user owns this review
            if ($review->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this review'
                ], Response::HTTP_FORBIDDEN);
            }

            $review->update($request->only(['rating', 'title', 'comment']));
            $review->load(['user:id,name', 'product:id,name']);

            return response()->json([
                'success' => true,
                'message' => 'Review updated successfully',
                'data' => $review
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update review',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a review
     */
    public function destroy($id)
    {
        try {
            $review = Review::findOrFail($id);

            // Check if user owns this review or is admin
            if ($review->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this review'
                ], Response::HTTP_FORBIDDEN);
            }

            $review->delete();

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get reviews for a specific product
     */
    public function getProductReviews($productId, Request $request)
    {
        $query = Review::with(['user:id,name'])
            ->where('product_id', $productId);

        // Add filters
        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 10);
        $reviews = $query->paginate($perPage);

        // Get review statistics for the product
        $reviewStats = Review::where('product_id', $productId)
            ->selectRaw('
                AVG(rating) as average_rating,
                COUNT(*) as total_reviews,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
            ')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $reviews->items(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
            'stats' => [
                'average_rating' => round($reviewStats->average_rating, 1),
                'total_reviews' => $reviewStats->total_reviews,
                'rating_breakdown' => [
                    5 => $reviewStats->five_star,
                    4 => $reviewStats->four_star,
                    3 => $reviewStats->three_star,
                    2 => $reviewStats->two_star,
                    1 => $reviewStats->one_star,
                ]
            ]
        ]);
    }

    /**
     * Check if user has purchased the product (for verified purchase badge)
     */
    private function checkVerifiedPurchase($productId)
    {
        // This assumes you have orders and order_items tables
        return DB::table('orders')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.user_id', auth()->id())
            ->where('order_items.product_id', $productId)
            ->where('orders.status', 'completed')
            ->exists();
    }
}
