<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Product::with('category');

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('price', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('stock', 'LIKE', "%{$searchTerm}%")
                      ->orWhereHas('category', function ($categoryQuery) use ($searchTerm) {
                          $categoryQuery->where('name', 'LIKE', "%{$searchTerm}%");
                      });
                });
            }

            // Sorting functionality
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');

            // Validate sort parameters
            $allowedSortFields = ['name', 'price', 'stock', 'created_at', 'updated_at'];
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

            if ($data->isEmpty()) {
                return response()->json([
                    'message' => 'No products found',
                    'data' => [
                        'data' => [],
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => $perPage,
                        'total' => 0,
                        'from' => null,
                        'to' => null
                    ]
                ], 200);
            }

            return response()->json([
                'data' => $data,
                'message' => 'Products retrieved successfully',
                'search_term' => $request->get('search'),
                'filters' => [
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                    'per_page' => $perPage
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch products',
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

            $query = Product::with('category');

            // Text search
            if ($request->filled('query')) {
                $searchTerm = $request->query;
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                      ->orWhereHas('category', function ($categoryQuery) use ($searchTerm) {
                          $categoryQuery->where('name', 'LIKE', "%{$searchTerm}%");
                      });
                });
            }

            // Category filter
            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Price range filter
            if ($request->filled('min_price')) {
                $query->where('price', '>=', $request->min_price);
            }

            if ($request->filled('max_price')) {
                $query->where('price', '<=', $request->max_price);
            }

            // Stock filter
            if ($request->filled('in_stock')) {
                if ($request->in_stock) {
                    $query->where('stock', '>', 0);
                } else {
                    $query->where('stock', '<=', 0);
                }
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

    public function show(Product $product)
    {
        try {
            $product->load('category');

            if (!$product) {
                return response()->json(['message' => 'Product not found'], 404);
            }

            return response()->json($product);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to fetch product', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string',
                'slug' => 'required|unique:products',
                'price' => 'required|numeric',
                'stock' => 'required|integer',
                'description' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'category_id' => 'required|exists:categories,id'
            ]);

            // Create the product first without the image
            $productData = $request->except('image');
            $product = Product::create($productData);

            // Handle image upload
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $filename = 'product_' . Str::random(20) . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('products/images', $filename, 'public');
                $product->image = Storage::url($path);
                $product->save();
            }

            return response()->json($product, 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to create product', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'slug' => 'required|unique:products,slug,' . $product->id,
            'price' => 'required|numeric',
            'stock' => 'required|integer',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'category_id' => 'required|exists:categories,id'
        ]);

        DB::beginTransaction();

        try {
            $productData = $validated;
            unset($productData['image']);

            $product->update($productData);

            // Handle new image upload and delete the old one
            if ($request->hasFile('image')) {
                // Delete old image file if it exists
                if ($product->image) {
                    $oldImagePath = str_replace('/storage/', '', $product->image);
                    Storage::disk('public')->delete($oldImagePath);
                }

                // Upload new image
                $image = $request->file('image');
                $filename = 'product_' . Str::random(20) . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('products/images', $filename, 'public');
                $product->image = Storage::url($path);
                $product->save();
            }

            DB::commit();

            return response()->json($product, 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Product update failed', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => 'Failed to update product',
                'message' => $e->getMessage()
            ], 500);
        }
    }

public function destroy(Product $product)
{
    try {
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ], 200);

    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'Failed to delete product',
            'message' => $e->getMessage()
        ], 500);
    }
}
}
