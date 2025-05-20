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
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProductController extends Controller
{
    public function index()
    {
        try {
            $data = Product::with('category')->paginate(10);

            if ($data->isEmpty()) {
                return response()->json(['message' => 'No products found'], 404);
            }

            return response()->json([
                'data' => $data,
                'message' => 'Products retrieved successfully',
            ], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to fetch products', 'message' => $e->getMessage()], 500);
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

            return response()->json(['message' => 'Product deleted']);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to delete product', 'message' => $e->getMessage()], 500);
        }
    }
}
