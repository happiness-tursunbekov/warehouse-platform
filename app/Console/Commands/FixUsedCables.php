<?php

namespace App\Console\Commands;

use App\Services\ConnectWiseService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class FixUsedCables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-used-cables';

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
        $unitOfMeasureShort = new \stdClass();

        $unitOfMeasureShort->_info = new \stdClass();
        $unitOfMeasureShort->_info->uom_href = "https://api-na.myconnectwise.net/v4_6_release/apis/3.0//procurement/unitOfMeasures/22";
        $unitOfMeasureShort->id = 22;
        $unitOfMeasureShort->name = "Box (used cable)";

        collect($connectWiseService->getCatalogItems(null, 'identifier contains "-used)" and inactiveFlag=false', null, null, 1000))->map(function ($item) use ($connectWiseService, $unitOfMeasureShort) {
            echo $item->identifier . "\n";

            $onHand = $connectWiseService->getCatalogItemOnHand($item->id)->count ?? 0;

            if ($onHand > 1) {
                $connectWiseService->catalogItemAdjust($item, 1 - $onHand, $item->id);
            }

            $item->unitOfMeasure = $unitOfMeasureShort;

            $idArr = explode('(', $item->identifier);
            $qty = Str::numbers($idArr[1]);

            $item->price = $item->cost = $item->cost * $qty;

            $item->identifier = Str::trim($idArr[0] . "({$qty}ft)");

            $connectWiseService->updateCatalogItem($item);

            echo $item->identifier . "\n";

            return $item;
        });
    }
}
