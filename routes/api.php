<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\ProductController;

// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // current user
    Route::get('/user', [AuthController::class, 'getUser']);

    //auth
    Route::post('/logout', [AuthController::class, 'logout']);

    //products
    Route::apiResource('/products', ProductController::class);

    // Category
    Route::apiResource('/categories', CategoryController::class);

    // Cart
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::delete('/cart/{id}', [CartController::class, 'destroy']);

    // Order
    Route::post('/orders/{order}/ship', [OrderController::class, 'markAsShipped'])
        ->middleware('role:admin');
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
});
