<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\OrderController;
use Illuminate\Support\Facades\Route;

/*
 * API v1 untuk aplikasi mobile (bagian 10). Berbagi lapisan Domain dengan halaman web.
 */
Route::prefix('v1')->name('api.')->middleware('throttle:api')->group(function () {
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/password/forgot', [AuthController::class, 'forgot']);
    });

    Route::get('categories', [CatalogController::class, 'categories']);
    Route::get('products', [CatalogController::class, 'products']);
    Route::get('products/{slug}', [CatalogController::class, 'product']);
    Route::get('producers/{slug}', [CatalogController::class, 'producer']);
    Route::get('search/suggest', [CatalogController::class, 'suggest']);
    Route::get('search', [CatalogController::class, 'products']);
    Route::get('recommendations/home', [CatalogController::class, 'home']);
    Route::get('recommendations/product/{slug}', [CatalogController::class, 'related']);
    Route::get('banners', [CatalogController::class, 'banners']);
    Route::get('posts', [CatalogController::class, 'posts']);
    Route::get('posts/{slug}', [CatalogController::class, 'post']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/email/resend', [AuthController::class, 'resend'])->middleware('throttle:3,60');

        Route::get('me', [AccountController::class, 'me']);
        Route::patch('me', [AccountController::class, 'update']);
        Route::delete('me', [AccountController::class, 'destroy']);

        Route::get('addresses', [AccountController::class, 'addresses']);
        Route::post('addresses', [AccountController::class, 'storeAddress']);
        Route::patch('addresses/{id}', [AccountController::class, 'updateAddress']);
        Route::delete('addresses/{id}', [AccountController::class, 'destroyAddress']);
        Route::get('shipping/destinations', [AccountController::class, 'destinations']);

        Route::get('cart', [OrderController::class, 'cart']);
        Route::post('cart/items', [OrderController::class, 'addToCart']);
        Route::patch('cart/items/{variant}', [OrderController::class, 'updateCart']);
        Route::delete('cart/items/{variant}', [OrderController::class, 'removeFromCart']);

        Route::get('wishlist', [AccountController::class, 'wishlist']);
        Route::post('wishlist/{product}', [AccountController::class, 'addWishlist']);
        Route::delete('wishlist/{product}', [AccountController::class, 'removeWishlist']);

        Route::get('notifications', [AccountController::class, 'notifications']);
        Route::post('notifications/read', [AccountController::class, 'readNotifications']);

        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{code}', [OrderController::class, 'show']);

        Route::middleware('verified')->group(function () {
            Route::post('checkout/quote', [OrderController::class, 'quote']);
            Route::post('checkout/shipping-options', [OrderController::class, 'quote']);
            Route::post('orders', [OrderController::class, 'store']);
            Route::post('orders/{code}/payment-proof', [OrderController::class, 'proof'])->middleware('throttle:proof-upload');
            Route::post('orders/{code}/cancel', [OrderController::class, 'cancel']);
            Route::post('orders/{code}/complete', [OrderController::class, 'complete']);
            Route::post('order-items/{id}/review', [OrderController::class, 'review']);
        });
    });
});
