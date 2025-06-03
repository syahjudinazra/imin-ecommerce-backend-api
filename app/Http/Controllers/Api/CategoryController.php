<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Exception;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = Category::query();

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Sorting functionality
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');

            // Validate sort parameters
            $allowedSortFields = ['name','created_at', 'updated_at'];
            $allowedSortOrders = ['asc', 'desc'];

            if (!in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'created_at';
            }

            if (!in_array($sortOrder, $allowedSortOrders)) {
                $sortOrder = 'desc';
            }

            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 5);
            $perPage = min(max($perPage, 1), 100);

            $data = $query->paginate($perPage);

            return response()->json([
                'data' => $data,
                'message' => 'Category retrieved successfully',
                'search_term' => $request->get('search'),
                'filters' => [
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                    'per_page' => $perPage
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch category',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    // Alternative method for more advanced search
    public function search(Request $request)
    {
        try {
            $request->validate([
                'query' => 'nullable|string|max:255',
                'category_id' => 'nullable|integer|exists:categories,id',
                'min_price' => 'nullable|numeric|min:0',
                'max_price' => 'nullable|numeric|min:0',
                'in_stock' => 'nullable|boolean',
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:name,price,stock,created_at',
                'sort_order' => 'nullable|string|in:asc,desc'
            ]);

            $query = Category::with('category');

            // Text search
            if ($request->filled('query')) {
                $searchTerm = $request->query;
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('slug', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 10);
            $data = $query->paginate($perPage);

            return response()->json([
                'data' => $data,
                'message' => 'Search completed successfully',
                'applied_filters' => $request->only([
                    'query', 'category_id', 'min_price', 'max_price',
                    'in_stock', 'sort_by', 'sort_order'
                ])
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Search failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string',
                'slug' => 'required|unique:categories',
            ]);

            $category = Category::create($validated);

            return response()->json($category, 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to create category', 'message' => $e->getMessage()], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        //
    }
}
