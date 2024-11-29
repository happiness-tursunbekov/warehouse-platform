<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Store\ProductController as StoreProductController;
use App\Http\Controllers\Store\OrderController as StoreOrderController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::group([
    'middleware' => 'auth:sanctum'
], function () {
    Route::group([
        'middleware' => 'role:admin'
    ], function () {
        Route::prefix('/products')->group(function () {
            Route::get('/', [ProductController::class, 'index']);
            Route::post('receive', [ProductController::class, 'receive']);
            Route::post('add-barcode', [ProductController::class, 'addBarcode']);
            Route::options('ship', [ProductController::class, 'shipOptions']);
            Route::post('ship', [ProductController::class, 'ship']);
            Route::post('upload-po-attachment', [ProductController::class, 'uploadPoAttachment']);
            Route::get('po-items', [ProductController::class, 'poItems']);
            Route::get('pos', [ProductController::class, 'pos']);
            Route::get('find-po-by-product', [ProductController::class, 'findPoByProduct']);
            Route::get('{id}/on-hand', [ProductController::class, 'onHand']);
        });

        Route::resource('orders', OrderController::class);
    });

    Route::get('/auth/user', [AuthController::class, 'user']);

    Route::prefix('/store')->group(function () {
        Route::options('products', [StoreProductController::class, 'options']);
        Route::get('products', [StoreProductController::class, 'index']);
        Route::get('orders/create', [StoreOrderController::class, 'create']);
        Route::post('orders', [StoreOrderController::class, 'store']);
    })->middleware('auth:sanctum');
});
