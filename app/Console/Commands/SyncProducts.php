<?php

namespace App\Console\Commands;

use App\Services\BigCommerceService;
use App\Services\ConnectWiseService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(ConnectWiseService $connectWiseService, BigCommerceService $bigCommerceService)
    {
        $page = 1;
        while (true) {
//            $items = collect($connectWiseService->getCatalogItems($page, "inactiveFlag=false", null, null, 1000));
//
//            $items->map(function ($item) use ($connectWiseService, $bigCommerceService) {
//                if (!$item) return false;
//                $category = $connectWiseService->getCategory($item->category->id);
//                $subcategory = $connectWiseService->getSubcategory($item->subcategory->id);
//
//                $categories = [$category->integrationXref];
//
//                if ($subcategory && @$subcategory->integrationXref) {
//                    $categories[] = $subcategory->integrationXref;
//                }
//
//                if ($subcategory && $subcategory->inactiveFlag) {
//                    return false;
//                }
//
//                $barcodes = $connectWiseService->extractBarcodesFromCatalogItem($item);
//
//                $product = $bigCommerceService->createProduct($item->sku ?? $item->identifier, $item->description, $item->description, $categories, $item->price, $item->cost, $barcodes[0] ?? '');
//
//                if (!$product) return false;
//            });
//
//            if ($items->count() < 1000)
//                break;
            $products = collect($bigCommerceService->getProducts($page, 250)->data);

            $products->map(function ($product) use ($bigCommerceService) {
                $bigCommerceService->updateProduct($product->id, ['inventory_tracking' => "variant"]);

                echo $product->sku . "\n";
            });

            if ($products->count() < 250)
                break;
            $page++;
        }
    }
}
