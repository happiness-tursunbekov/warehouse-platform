<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ConnectWiseService;
use Illuminate\Console\Command;

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
    public function handle(ConnectWiseService $connectWiseService)
    {
        $page = 1;
        while(true) {
            $items = $connectWiseService->getCatalogItems($page, null, null, 1000);
            array_map(function ($item) use ($connectWiseService) {
                $onHand = $connectWiseService->getCatalogItemOnHand($item->id)->count;

                /** @var Product $product */
                $product = Product::find($item->id);
                if ($product)
                    $product->fill([
                        'on_hand' => $onHand,
                        'on_hand_available' => $onHand,
                        'inactive_flag' => $item->inactiveFlag
                    ])->save();
                else {
                    $product = Product::create([
                        'id' => $item->id,
                        'on_hand' => $onHand,
                        'on_hand_available' => $onHand,
                        'inactive_flag' => $item->inactiveFlag
                    ]);
                }
            }, $items);
            if (count($items) < 1000)
                break;
            $page++;
        }
    }
}
