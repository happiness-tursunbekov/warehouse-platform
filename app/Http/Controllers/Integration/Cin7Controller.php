<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use App\Services\BigCommerceService;
use App\Services\Cin7Service;
use App\Services\ConnectWiseService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Cin7Controller extends Controller
{
    public function availableStockLevelChanged(Request $request, ConnectWiseService $connectWiseService)
    {
        WebhookLog::create([
            'type' => 'Stock/AvailableStockLevelChanged',
            'data' => $request->post()
        ]);

        try {
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

            if ($adjustmentDetails->count() > 0) {
                $connectWiseService->catalogItemAdjustBulk($adjustmentDetails, 'Azad May Available Quantity Changed');
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage() . "\n" . $e->getTraceAsString());
        }

        return response()->json(['message' => 'Product adjusted successfully!']);
    }

    public function saleShipmentAuthorized(Request $request, ConnectWiseService $connectWiseService, BigCommerceService $bigCommerceService, Cin7Service $cin7Service)
    {
        $request->validate([
            'SaleTaskID' => ['required', 'string'],
            'OrderNumber' => ['required', 'string'],
            'EventType' => ['required', 'string'],
            'CustomerReference' => ['nullable', 'string'],
            'CustomerName' => ['nullable', 'string'],
        ]);

        WebhookLog::create([
            'type' => 'Sale/ShipmentAuthorized',
            'data' => $request->post()
        ]);

        try {
            $salesOrderId = $request->get('SaleTaskID');
            $customerName = $request->get('CustomerName');

            $bigCommerceOrderId = $request->get('CustomerReference');

            $bigCommerceOrder = $bigCommerceService->getOrder($bigCommerceOrderId);

            $purchaseOrder = $connectWiseService->purchaseOrders(1, cin7SalesOrderId: $salesOrderId)[0] ?? null;

            if ($purchaseOrder) {
                return response()->json(['message' => 'Purchase order for this sales order already exists!']);
            }

            if (!$bigCommerceOrderId || !$bigCommerceOrder || $bigCommerceOrder->channel_id != 1) {
                return response()->json(['message' => "Sales order doesn't belong to Binyod!"]);
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

                $createdPO = null;

                $cwProducts = $connectWiseService->createAzadMayPO($azadMayProducts, $departmentId, $salesOrderId, $createdPO);

                $cwProducts->map(function ($cwProduct) use ($connectWiseService) {
                    $connectWiseService->pickAndShipProduct($cwProduct->id, $cwProduct->quantity);
                });

                $cin7Service->updateSale([
                    'ID' => $salesOrderId,
                    'ShippingNotes' => 'ConnectWise PO: ' . $createdPO->poNumber,
                    'Customer' => $customerName
                ]);
            }

            // Shipping project products

            $bigCommerceOrderProducts->filter(fn($item) => Str::contains($item->sku, '-PROJECT'))
                ->map(function ($item) use ($connectWiseService) {

                    $skuParts = array_reverse(explode('-', $item->sku));

                    $ticketId = $skuParts[1] != 'PROJECT' ? $skuParts[0] : null;

                    $projectId = $skuParts[1] == 'PROJECT' ? $skuParts[0] : ($skuParts[3] == 'PROJECT' ? $skuParts[2] : null);

                    $catalogItemIdentifier = explode('-PROJECT', $item->sku)[0];

                    $shipQuantity = $item->quantity;

                    array_map(function ($product) use (&$shipQuantity, $connectWiseService) {

                        if ($shipQuantity == 0) {
                            return false;
                        }

                        $productPickAndShips = collect($connectWiseService->getProductPickingShippingDetails($product->id));

                        $shippedQuantity = $productPickAndShips->pluck('shippedQuantity')->sum();
                        $pickedQuantity = $productPickAndShips->pluck('pickedQuantity')->sum();

                        if ($pickedQuantity == $shippedQuantity) {
                            return false;
                        }

                        $shipAvailableQuantity = $pickedQuantity - $shippedQuantity;

                        $connectWiseService->shipProduct($product->id, min($shipAvailableQuantity, $shipQuantity));

                        $shipQuantity = $shipQuantity <= $shipAvailableQuantity ? 0 : $shipQuantity - $shipAvailableQuantity;

                        return $product;

                    }, $connectWiseService->getProductsBy($catalogItemIdentifier, $ticketId, $projectId));

                    return $item;

                });
        } catch (\Exception $e) {
            Log::error($e->getMessage() . "\n" . $e->getTraceAsString());
        }

        return response()->json(['message' => 'Sales order handled successfully!']);
    }
}
