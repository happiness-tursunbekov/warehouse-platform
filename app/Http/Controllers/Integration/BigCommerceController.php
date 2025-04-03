<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\WebhookLog;
use App\Services\BigCommerceService;
use App\Services\ConnectWiseService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BigCommerceController extends Controller
{
    public function productCreated(Request $request, BigCommerceService $bigCommerceService, ConnectWiseService $connectWiseService)
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

                $catalogItem = $connectWiseService->getCatalogItemByIdentifier($product->sku);

                if ($catalogItem) {
                    $bigCommerceService->setProductChannels($product->id, [1]);
                    $bigCommerceService->setProductCategories($product->id, [332]);
                }

                sleep(0.3);
            } catch (\Exception) {}
        }
    }
}
