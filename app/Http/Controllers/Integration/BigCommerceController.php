<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use App\Services\BigCommerceService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BigCommerceController extends Controller
{
    public function productCreated(Request $request, BigCommerceService $bigCommerceService)
    {
        $data = $request->get('data');

        $product = $bigCommerceService->getProduct($data['id']);

        if (!Str::contains($product->sku, 'PROJECT')) {
            try {
                $bigCommerceService->createProductModifier($product->id, BigCommerceService::PRODUCT_OPTION_PROJECT);
                $bigCommerceService->createProductModifier($product->id, BigCommerceService::PRODUCT_OPTION_PHASE, sort_order: 1);
                $bigCommerceService->createProductModifier($product->id, BigCommerceService::PRODUCT_OPTION_PROJECT_TICKET, sort_order: 2);
                $bigCommerceService->createProductModifier($product->id, BigCommerceService::PRODUCT_OPTION_COMPANY, sort_order: 3);
                $bigCommerceService->createProductModifier($product->id, BigCommerceService::PRODUCT_OPTION_SERVICE_TICKET, sort_order: 4);
                $bigCommerceService->createProductModifier($product->id, BigCommerceService::PRODUCT_OPTION_BUNDLE, sort_order: 5);
            } catch (\Exception) {}
        }
    }
}
