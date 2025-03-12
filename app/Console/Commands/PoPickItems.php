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
    protected $signature = 'app:po-pick-items {--poId}';

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
        $poId = $this->option('poId');

        $po = $connectWiseService->purchaseOrder($poId);

        echo "$po->poNumber\n";

        collect($connectWiseService->purchaseOrderItemsOriginal($po->id, 1, 'receivedStatus="FullyReceived"'))->map(function ($poItem) use ($po, $connectWiseService) {

            $ticket = @$connectWiseService->getPurchaseOrderItemTicketInfo($po->id, $poItem->id)[0];

            if (!$ticket) {
                return false;
            }

            $quantity = $poItem->quantity;

            $onHand = $connectWiseService->getCatalogItemOnHand($connectWiseService->getCatalogItemByIdentifier($ticket->Item_ID)->id)->count;

            if ($onHand == 0) {
                return false;
            }

            if ($quantity > $onHand) {
                $quantity = $onHand;
            }

            $products = $connectWiseService->getProductsByTicketInfo($ticket);

            sleep(1);

            // Checking if pick/unpick quantity matches available quantity before processing to sync
            $results = collect($products)
                ->map(function ($product) use (&$quantity, $connectWiseService, $po) {

                    if ($quantity == 0) {
                        return false;
                    }

                    if (@$product->invoice && $connectWiseService->getInvoice($product->invoice->id)->status->isClosed) {
                        return false;
                    }

                    $productPoItems = collect($connectWiseService->getProductPoItems($product->id))->where('ID', $po->id);

                    if (!$productPoItems->count()) {
                        return false;
                    }

                    $productPickAndShips = collect($connectWiseService->getProductPickingShippingDetails($product->id));

                    $pickAvailableQuantity = $product->quantity - $productPickAndShips->pluck('pickedQuantity')->sum();

                    if ($pickAvailableQuantity == 0 || $product->quantity == $productPickAndShips->pluck('shippedQuantity')->sum()) {
                        return false;
                    }

                    $result = [
                        'product' => $product,
                        'quantity' => min($quantity,$pickAvailableQuantity)
                    ];

                    $quantity = $quantity <= $pickAvailableQuantity ? 0 : $quantity - $pickAvailableQuantity;

                    return $result;
                });

            // Processing syncing
            $results->filter(fn($results) => !!$results)
                ->map(function (array $result) use ($connectWiseService) {
                    try {
                        $connectWiseService->pickProduct($result['product']->id, $result['quantity']);
                    } catch (\Exception $e) {
                        echo "Error: {$result['product']->id}: {$result['quantity']}";

                        throw $e;
                    }

                    echo "{$result['product']->id}:{$result['quantity']}\n";
                });

        });
    }
}
