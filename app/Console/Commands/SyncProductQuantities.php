<?php

namespace App\Console\Commands;

use App\Services\BigCommerceService;
use App\Services\ConnectWiseService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncProductQuantities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-product-quantities';

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
            $items = collect($connectWiseService->getCatalogItems($page, "inactiveFlag=false and id>2524", null, null, 1000));

            $items->map(function ($item) use ($connectWiseService, $bigCommerceService) {
                $productId = $connectWiseService->getBigCommerceProductId($item);

                if (!$productId) return false;

                $qty = $connectWiseService->getCatalogItemOnHand($item->id)->count;


                if ($qty === 0) return false;

                $bigCommerceService->adjust($productId, $qty);

                echo $item->identifier . "\n";
            });

            if ($items->count() < 1000)
                break;
            $page++;
        }
    }
}
