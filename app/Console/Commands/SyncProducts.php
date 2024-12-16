<?php

namespace App\Console\Commands;

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
        while (true) {
            $items = collect($connectWiseService->getCatalogItems($page, 'inactiveFlag=false'));

            $items->map(function ($item) use ($connectWiseService) {
                $qty = $connectWiseService->getCatalogItemOnHand($item->id)->count;
            });


            if ($items->count() < 1000)
                break;
            $page++;
        }
    }
}
