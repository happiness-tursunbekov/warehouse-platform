<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Integration\ConnectWiseController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Store\ProductController as StoreProductController;
use App\Http\Controllers\Store\OrderController as StoreOrderController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/products/image/{attachmentId}/{fileName}', [ProductController::class, 'image']);


Route::prefix('/integration')->group(function () {
    Route::group([
        'middleware' => 'integration:connect-wise'
    ], function () {
        Route::prefix('connect-wise')->group(function () {
            Route::post('product-catalog', [ConnectWiseController::class, 'productCatalog']);
            Route::post('project', [ConnectWiseController::class, 'project']);
            Route::post('activity', [ConnectWiseController::class, 'activity']);
            Route::post('ticket', [ConnectWiseController::class, 'ticket']);
            Route::post('purchase-order', [ConnectWiseController::class, 'purchaseOrder']);
            Route::post('invoice', [ConnectWiseController::class, 'invoice']);
        });
    });

    Route::group([
        'middleware' => 'integration:big-commerce'
    ], function () {
        Route::prefix('big-commerce')->group(function () {

        });
    });
});

Route::group([
    'middleware' => 'auth:sanctum'
], function () {
    Route::group([
        'middleware' => 'role:admin'
    ], function () {
        Route::prefix('/products')->group(function () {
            Route::get('/', [ProductController::class, 'index']);
            Route::post('receive', [ProductController::class, 'receive']);
            Route::post('unship', [ProductController::class, 'unship']);
            Route::post('unship-as-used', [ProductController::class, 'unshipAsUsed']);
            Route::post('add-barcode', [ProductController::class, 'addBarcode']);
            Route::options('ship', [ProductController::class, 'shipOptions']);
            Route::post('ship', [ProductController::class, 'ship']);
            Route::post('upload-po-attachment', [ProductController::class, 'uploadPoAttachment']);
            Route::get('po-items', [ProductController::class, 'poItems']);
            Route::get('po-report', [ProductController::class, 'poReport']);
            Route::get('pos', [ProductController::class, 'pos']);
            Route::get('find-po-by-product', [ProductController::class, 'findPoByProduct']);
            Route::post('{id}/create-used-item', [ProductController::class, 'createUsedItem']);
            Route::post('{id}/adjust', [ProductController::class, 'adjust']);
            Route::get('{id}/on-hand', [ProductController::class, 'onHand']);
            Route::get('{id}/images', [ProductController::class, 'images']);
            Route::post('{product}/upload', [ProductController::class, 'upload']);
        });

        Route::resource('orders', OrderController::class);
    });

    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::put('/auth/user', [AuthController::class, 'update']);
    Route::get('/auth/user/reports', [AuthController::class, 'reports']);

    Route::prefix('/store')->group(function () {
        Route::options('products', [StoreProductController::class, 'options']);
        Route::get('products', [StoreProductController::class, 'index']);
        Route::get('products/{id}/on-hand', [StoreProductController::class, 'onHand']);
        Route::get('products/{id}/images', [StoreProductController::class, 'images']);
        Route::get('orders/create', [StoreOrderController::class, 'create']);
        Route::post('orders', [StoreOrderController::class, 'store']);
    })->middleware('auth:sanctum');
});
