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
                $bigCommerceService->createProductModifier($product->id, BigCommerceService::PRODUCT_OPTION_PHASE);
                $bigCommerceService->createProductModifier($product->id, BigCommerceService::PRODUCT_OPTION_PROJECT_TICKET);
                $bigCommerceService->createProductModifier($product->id, BigCommerceService::PRODUCT_OPTION_COMPANY);
                $bigCommerceService->createProductModifier($product->id, BigCommerceService::PRODUCT_OPTION_SERVICE_TICKET);

                $sharedModifierBundle = $bigCommerceService->getSharedModifierByName(BigCommerceService::PRODUCT_OPTION_BUNDLE);

                $bigCommerceService->addProductSharedModifier($product->id, $sharedModifierBundle);
            } catch (\Exception) {}
        }
    }
}
