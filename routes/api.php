<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;

// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // current user
    Route::get('/user', [AuthController::class, 'getUser']);

    //auth
    Route::post('/logout', [AuthController::class, 'logout']);

    //products
<<<<<<< HEAD
    Route::apiResource('/products', ProductController::class);
=======
>>>>>>> 3b88f01bb9ec2f09a403a11d9fbe477cf3e1ae7b

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

//Unauthenticated routes
Route::apiResource('/reviews', ReviewController::class);
Route::apiResource('/products', ProductController::class)->except('destroy');
