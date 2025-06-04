<?php

namespace App\Jobs;

use App\Services\Cin7Service;
use App\Services\ConnectWiseService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;

class TakeProductsToAzadMay implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    private array $body;

    /**
     * Create a new job instance.
     */
    public function __construct(array $body)
    {
        $this->body = $body;
    }

    /**
     * Execute the job.
     */
    public function handle(Cin7Service $cin7Service, ConnectWiseService $connectWiseService): void
    {
        $supplierId = $this->body['supplierId'];

        $isCatalogItem = $this->body['isCatalogItem'] ?? false;

        $productsData = collect($this->body['products']);

        $adjustmentDetails = collect();

        $memo = "";

        $purchaseOrderLine = $productsData->map(function ($productData) use ($cin7Service, $connectWiseService, &$memo, &$adjustmentDetails, $isCatalogItem) {
            $product = $isCatalogItem ? $connectWiseService->getCatalogItem($productData['id']) : $connectWiseService->getProduct($productData['id']);

            $quantity = $productData['quantity'];

            if (!$isCatalogItem) {
                $connectWiseService->unpickProduct($product->id, $quantity);

                $connectWiseService->stockTakeFromCin7ByProjectProductId($product->id, $quantity, true, $product);

                $catalogItem = $connectWiseService->getCatalogItem($product->catalogItem->id);

                $adjustmentDetails->push($connectWiseService->convertCatalogItemToAdjustmentDetail($catalogItem, -1 * $quantity));

                $memo .= $catalogItem->identifier . ' - Unpicked from' . (@$product->project ? " project: #{$product->project->id} &#13;\n"
                        : (@$product->ticket ? " service ticket: #{$product->ticket->id}" : " sales order: #{$product->salesOrder->id} &#13;\n"));
            }

            $product->cost = $productData['cost'];

            return $cin7Service->convertProductToPurchaseOrderLine($product, $quantity, $isCatalogItem, !!@$productData['doNotCharge']);
        });

        if (!$isCatalogItem) {
            $connectWiseService->catalogItemAdjustBulk($adjustmentDetails, 'Taking to Azad May Inventory');
        }

        if ($purchaseOrderLine->count() > 0) {
            $purchaseOrder = $cin7Service->createPurchaseOrder($purchaseOrderLine->toArray(), $supplierId, $memo);

            $cin7Service->receivePurchaseOrderItems($purchaseOrder->TaskID, array_map(fn($line) => ([
                'ProductID' => $line->ProductID,
                'Quantity' => $line->Quantity,
                'Date' => date('Y-m-d H:i:s'),
                'Received' => true,
                'Location' => Cin7Service::INVENTORY_AZAD_MAY
            ]), $purchaseOrder->Lines));
        }
    }
}
