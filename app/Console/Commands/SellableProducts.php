<?php

namespace App\Console\Commands;

use App\Services\ConnectWiseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SellableProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sellable-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(ConnectWiseService $connectWiseService)
    {
        $page = 1;
        while (true) {
            $items = collect($connectWiseService->getCatalogItems($page, 'inactiveFlag=false', null, null, 1000));

            $items->map(function ($item) use ($connectWiseService) {
                $onHand = $connectWiseService->getCatalogItemOnHand($item->id)->count;

                if ($onHand == 0) {
                    return false;
                }

                $products = collect($connectWiseService->getProducts(null, "catalogItem/id={$item->id} and cancelledFlag=false", 1000));

                $needQty = $products->map(function ($product) use ($connectWiseService) {
                    $pickShip = collect($connectWiseService->getProductPickingShippingDetails($product->id));

                    $shippedQty = $pickShip->map(function ($ps) {
                        return $ps->pickedQuantity ?: $ps->shippedQuantity;
                    })->sum();

                    return $product->quantity - $shippedQty;
                })->sum();

                $available = $onHand - $needQty;

                if ($available > 0) {
                    DB::table('sellables')->insert([
                        'item_id' => $item->id,
                        'identifier' => $item->identifier,
                        'description' => $item->description,
                        'available' => $available,
                        'category' => $item->category->name,
                        'category_id' => $item->category->id
                    ]);
                }

                return false;
            });


            if ($items->count() < 1000)
                break;

            $page++;
        }
    }
}
