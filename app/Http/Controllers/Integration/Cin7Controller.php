<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use App\Services\BigCommerceService;
use App\Services\Cin7Service;
use App\Services\ConnectWiseService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Cin7Controller extends Controller
{
    public function availableStockLevelChanged(Request $request, ConnectWiseService $connectWiseService)
    {
//        WebhookLog::create([
//            'type' => 'Stock/AvailableStockLevelChanged',
//            'data' => $request->post()
//        ]);

        $adjustmentDetails = collect($request->post())
            ->unique('ID')
            ->map(function ($stock) use ($connectWiseService) {
                $productSku = $stock['SKU'] ?? null;

                if (!$productSku) {
                    return false;
                }

                $available = $stock['Available'];

                if (Str::contains($productSku, '-PROJECT')) {
                    return false;
                }

                $catalogItem = $connectWiseService->getCatalogItemByIdentifier($productSku);

                if (!$catalogItem) {
                    return false;
                }

                $onHand = $connectWiseService->getCatalogItemOnHand($catalogItem->id, ConnectWiseService::AZAD_MAY_WAREHOUSE_DEFAULT_BIN)->count;

                if ($onHand == $available) {
                    return false;
                }

                $quantity = $available - $onHand;

                return $connectWiseService->convertCatalogItemToAdjustmentDetail($catalogItem, $quantity, ConnectWiseService::AZAD_MAY_WAREHOUSE);
            })
            ->filter(fn($detail) => !!$detail);

        $connectWiseService->catalogItemAdjustBulk($adjustmentDetails, 'Azad May Available Quantity Changed');

        return response()->json(['message' => 'Product adjusted successfully!']);
    }

    public function saleShipmentAuthorized(Request $request, ConnectWiseService $connectWiseService, BigCommerceService $bigCommerceService)
    {
        $request->validate([
            'SaleTaskID' => ['required', 'string'],
            'OrderNumber' => ['required', 'string'],
            'EventType' => ['required', 'string'],
            'CustomerReference' => ['nullable', 'string']
        ]);

        WebhookLog::create([
            'type' => 'Sale/ShipmentAuthorized',
            'data' => $request->post()
        ]);

        $bigCommerceOrderId = $request->get('CustomerReference');

        if (!$bigCommerceOrderId || !($bigCommerceOrder = $bigCommerceService->getOrder($bigCommerceOrderId)) || $bigCommerceOrder->channel_id != 1) {
            return null;
        }

        $bigCommerceOrderProducts = collect($bigCommerceService->getOrderProducts($bigCommerceOrder->id));

        // Handling Azad May products

        $azadMayProducts = $bigCommerceOrderProducts->filter(fn($item) => !Str::contains($item->sku, '-PROJECT'));

        if ($azadMayProducts->count() > 0) {

            $customer = $bigCommerceService->getCustomer($bigCommerceOrder->customer_id);

            if (
                !$customer->customer_group_id
                || !($group = $bigCommerceService->getCustomerGroup($customer->customer_group_id))
                || !($departmentId = Str::numbers(explode('-', $group->name)[0]))
            ) {
                $departmentId = $connectWiseService->getSystemDepartments(1, 'name contains "*Team A*"')[0]->id;
            }

            $cwProducts = $connectWiseService->createAzadMayPO($azadMayProducts, $departmentId);

            $cwProducts->map(function ($cwProduct) use ($connectWiseService) {
                $connectWiseService->pickAndShipProduct($cwProduct->id, $cwProduct->quantity);
            });
        }

        // Shipping project products

        $bigCommerceOrderProducts->filter(fn($item) => Str::contains($item->sku, '-PROJECT'))->map(function ($item) use ($connectWiseService) {

            $skuParts = array_reverse(explode('-', $item->sku));

            $ticketId = $skuParts[1] != 'PROJECT' ? $skuParts[0] : null;

            $projectId = $skuParts[1] == 'PROJECT' ? $skuParts[0] : ($skuParts[3] == 'PROJECT' ? $skuParts[2] : null);

            $catalogItemIdentifier = explode('-PROJECT', $item->sku)[0];

            $shipQuantity = $item->Quantity;

            array_map(function ($product) use (&$shipQuantity, $connectWiseService) {

                if ($shipQuantity == 0) {
                    return false;
                }

                $productPoItems = collect($connectWiseService->getProductPoItems($product->id))
                    ->where('Received_Qty', '!=', 0)
                ;

                if (!$productPoItems->count()) {
                    return false;
                }

                $productPickAndShips = collect($connectWiseService->getProductPickingShippingDetails($product->id));

                if ($product->quantity == $productPickAndShips->pluck('shippedQuantity')->sum()) {
                    return false;
                }

                $shipAvailableQuantity = $product->quantity - $productPickAndShips->pluck('shippedQuantity')->sum();

                $connectWiseService->shipProduct($product->id, $shipQuantity);

                $shipQuantity = $shipQuantity <= $shipAvailableQuantity ? 0 : $shipQuantity - $shipAvailableQuantity;

                return $product;

            }, $connectWiseService->getProductsBy($catalogItemIdentifier, $ticketId, $projectId));

            return $item;

        });
    }
}
