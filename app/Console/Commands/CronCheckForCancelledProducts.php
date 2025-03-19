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

        collect($connectWiseService->getProducts(1, "cancelledFlag=true and _info/lastUpdated > {$time}", 1000))
            ->map(function ($product) use ($connectWiseService, $cin7Service) {
                $onHand = $connectWiseService->getCatalogItemOnHand($product->catalogItem->id)->count;

                if ($onHand == 0) {
                    return false;
                }

                $onHandAzadMay = $connectWiseService->getCatalogItemOnHand($product->catalogItem->id, ConnectWiseService::AZAD_MAY_WAREHOUSE_DEFAULT_BIN)->count;

                $cin7Product = $cin7Service->productBySku($product->catalogItem->identifier);
                sleep(1);
                $catalogItem = $connectWiseService->getCatalogItem($product->catalogItem->id);

                if (!$cin7Product) {
                    $cin7Product = $cin7Service->createProduct(
                        $catalogItem->identifier,
                        $catalogItem->description,
                        $catalogItem->category->name,
                        $catalogItem->unitOfMeasure->name,
                        $catalogItem->customerDescription,
                        $product->cost * 0.9 * 1.07
                    );

                    $connectWiseService->syncCatalogItemAttachmentsWithCin7($catalogItem->id, $cin7Product->ID, isProductFamily: false);
                }

                $cin7Service->stockAdjust($cin7Product->ID, $onHand + $onHandAzadMay);

                if ($onHand > 0) {
                    $connectWiseService->catalogItemAdjustBulk($connectWiseService->convertCatalogItemToAdjustmentDetail($catalogItem, -1 * $onHand), 'Taking to Azad May');
                }

                return true;
            });

        cache()->put('cancelled-products-last-checked-at', $now);
    }
}
