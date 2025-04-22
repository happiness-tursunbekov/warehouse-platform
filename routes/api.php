<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Integration\BigCommerceController;
use App\Http\Controllers\Integration\Cin7Controller;
use App\Http\Controllers\Integration\ConnectWiseController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Store\ProductController as StoreProductController;
use App\Http\Controllers\Store\OrderController as StoreOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('/binyod')->group(function () {
    Route::group([
        'middleware' => 'binyod'
    ], function () {
        Route::get('projects', [ConnectWiseController::class, 'projects']);
        Route::get('phases', [ConnectWiseController::class, 'phases']);
        Route::get('companies', [ConnectWiseController::class, 'companies']);
        Route::get('project-tickets', [ConnectWiseController::class, 'projectTickets']);
        Route::get('service-tickets', [ConnectWiseController::class, 'serviceTickets']);
        Route::get('bundles', [ConnectWiseController::class, 'bundles']);
    });
});

Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/products/image/{attachmentId}/{fileName}', [ProductController::class, 'image']);


Route::prefix('/integration')->group(function () {
    Route::group([
        'middleware' => 'integration:connect-wise'
    ], function () {
        Route::prefix('connect-wise')->group(function () {
            Route::post('product-catalog', [ConnectWiseController::class, 'productCatalog']);
            Route::post('purchase-order', [ConnectWiseController::class, 'purchaseOrder']);
            Route::post('member', [ConnectWiseController::class, 'member']);
        });
    });

    Route::group([
        'middleware' => 'integration:cin7'
    ], function () {
        Route::prefix('cin7')->group(function () {
            Route::post('sale-shipment-authorized', [Cin7Controller::class, 'saleShipmentAuthorized']);
            Route::post('available-stock-level-changed', [Cin7Controller::class, 'availableStockLevelChanged']);
        });
    });

    Route::group([
        'middleware' => 'integration:big-commerce'
    ], function () {
        Route::prefix('big-commerce')->group(function () {
            Route::post('product-created', [BigCommerceController::class, 'productCreated']);
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
            Route::post('add-barcode', [ProductController::class, 'addBarcode']);
            Route::options('ship', [ProductController::class, 'shipOptions']);
            Route::post('ship', [ProductController::class, 'ship']);
            Route::post('pick', [ProductController::class, 'pick']);
            Route::post('upload-po-attachment', [ProductController::class, 'uploadPoAttachment']);
            Route::get('po-items', [ProductController::class, 'poItems']);
            Route::get('po-report', [ProductController::class, 'poReport']);
            Route::get('pos', [ProductController::class, 'pos']);
            Route::get('uoms', [ProductController::class, 'uoms']);
            Route::get('find-po-by-product', [ProductController::class, 'findPoByProduct']);
            Route::post('{id}/uom', [ProductController::class, 'updateUom']);
            Route::post('{id}/create-used-item', [ProductController::class, 'createUsedItem']);
            Route::post('{id}/check', [ProductController::class, 'check']);
            Route::post('{id}/sellable', [ProductController::class, 'sellable']);
            Route::get('{id}/on-hand', [ProductController::class, 'onHand']);
            Route::get('{id}/images', [ProductController::class, 'images']);
            Route::post('{product}/upload', [ProductController::class, 'upload']);
            Route::post('take-products-to-azad-may', [ProductController::class, 'takeProductsToAzadMay']);
            Route::post('take-catalog-items-to-azad-may', [ProductController::class, 'takeCatalogItemsToAzadMay']);
            Route::post('move-product-to-different-project', [ProductController::class, 'moveProductToDifferentProject']);
            Route::get('cin7-suppliers', [ProductController::class, 'cin7Suppliers']);
            Route::post('{id}/sync-images', [ProductController::class, 'syncImages']);
        });
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
