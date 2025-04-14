<?php

namespace App\Console\Commands;

use App\Services\ConnectWiseService;
use Illuminate\Console\Command;

class PoPickItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:po-pick-items {poId} {--from}';

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
        $poId = $this->argument('poId');
        $from = $this->option('from');

        $from = $from ? '>=' : '=';

        $page=1;

        while (true) {
            $pos = collect($connectWiseService->purchaseOrders($page, "id {$from} {$poId} and status/name!='Cancelled'", null, 'id'));

            $pos->map(function ($po) use ($connectWiseService) {

                echo "$po->poNumber\n";

                collect($connectWiseService->purchaseOrderItemsOriginal($po->id, 1, 'receivedStatus="FullyReceived"'))->map(function ($poItem) use ($po, $connectWiseService) {

                    try {
                        $connectWiseService->pickOrShipPurchaseOrderItem($po->id, $poItem, callback: function ($product, $quantity) {
                            echo "{$product->id}: {$quantity}\n";
                        });
                    } catch (\Exception $e) {
                        echo "Error msg: {$e->getMessage()}\n";
                    }

                });
            });

            if ($pos->count() < 1000) {
                break;
            }

            $page++;
        }
    }
}
