<?php

namespace App\Console\Commands;

use App\Services\Cin7Service;
use App\Services\ConnectWiseService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncQuantityWithCin7 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-quantity-with-cin7 {catalogItemIds}';

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
        $catalogItemIds = $this->argument('catalogItemIds');

        $catalogItems = collect($connectWiseService->getCatalogItems(1, "id in ({$catalogItemIds})", pageSize: 1000));

        $adjustmentDetails = $catalogItems
            ->map(function ($catalogItem) use ($cin7Service, $connectWiseService) {
                $productSku = $catalogItem->identifier;

                $cin7OnHand = $cin7Service->productAvailabilityBySku($productSku);

                $available = !$cin7OnHand ? 0 : $cin7OnHand->Available;

                $onHand = $connectWiseService->getCatalogItemOnHand($catalogItem->id, ConnectWiseService::AZAD_MAY_WAREHOUSE_DEFAULT_BIN)->count;

                if ($onHand == $available) {
                    return false;
                }

                $quantity = $available - $onHand;

                return $connectWiseService->convertCatalogItemToAdjustmentDetail($catalogItem, $quantity, ConnectWiseService::AZAD_MAY_WAREHOUSE);
            })
            ->filter(fn($detail) => !!$detail);

        if ($adjustmentDetails->count() > 0) {
            $connectWiseService->catalogItemAdjustBulk($adjustmentDetails, 'Azad May Available Quantity Changed');
        }
    }
}
