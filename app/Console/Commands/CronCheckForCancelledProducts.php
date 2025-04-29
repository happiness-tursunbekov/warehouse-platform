<?php

namespace App\Console\Commands;

use App\Services\Cin7Service;
use App\Services\ConnectWiseService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CronCheckForCancelledProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cron-check-for-cancelled-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(ConnectWiseService $connectWiseService, Cin7Service $cin7Service)
    {
        $now = Carbon::now();

        $lastChecked = cache()->get('cancelled-products-last-checked-at') ?: $now->subMinutes(5);

        $time = "[" .$lastChecked->toDateTimeLocalString() . "Z]";

        $adjustmentDetails = collect();
        $purchaseOrderLines = collect();

        collect($connectWiseService->getProducts(1, "cancelledFlag=true and _info/lastUpdated > {$time}", 1000))
            ->map(function ($product) use ($connectWiseService, $cin7Service, &$adjustmentDetails, &$purchaseOrderLines) {
                $onHand = $connectWiseService->getCatalogItemOnHand($product->catalogItem->id)->count;

                if ($onHand == 0) {
                    return false;
                }

                $cin7Product = $cin7Service->productBySku($product->catalogItem->identifier);
                $catalogItem = $connectWiseService->getCatalogItem($product->catalogItem->id);

                if (!$cin7Product) {
                    $cin7Product = $cin7Service->createProduct(
                        $catalogItem->identifier,
                        $catalogItem->description,
                        $catalogItem->category->name,
                        $catalogItem->unitOfMeasure->name,
                        $catalogItem->customerDescription,
                        $product->cost
                    );

                    $connectWiseService->syncCatalogItemAttachmentsWithCin7($catalogItem->id, $cin7Product->ID, isProductFamily: false);
                }

                $purchaseOrderLines->push($cin7Service->convertProductToPurchaseOrderLine($product, $onHand));
                $adjustmentDetails->push($connectWiseService->convertCatalogItemToAdjustmentDetail($catalogItem, -1 * $onHand));

                return true;
            });

        if ($purchaseOrderLines->count() > 0) {
            $cin7Service->stockAdjustBulk($purchaseOrderLines);

            $purchaseOrder = $cin7Service->createPurchaseOrder($purchaseOrderLines->toArray(), '6f54ff7f-03cb-4dac-aaa3-6adaa5b92e41', 'Cancelled products on ConnectWise');
        }

        if ($adjustmentDetails->count() > 0) {
            $connectWiseService->catalogItemAdjustBulk($adjustmentDetails, 'Taking to Azad May');
        }

        cache()->put('cancelled-products-last-checked-at', $now);
    }
}
