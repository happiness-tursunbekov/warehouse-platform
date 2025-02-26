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
    public function saleShipmentAuthorized(Request $request, Cin7Service $cin7Service, ConnectWiseService $connectWiseService, BigCommerceService $bigCommerceService)
    {
        $request->validate([
            'SaleTaskID' => ['required', 'string'],
            'OrderNumber' => ['required', 'string'],
            'EventType' => ['required', 'string'],
            'CustomerReference' => ['nullable', 'string']
        ]);

        // TODO: Remove
        return response()->json(['message' => 'Service temporarily inactive']);

        $bigCommerceOrderId = $request->get('CustomerReference');

        $bigCommerceOrder = null;

        if (!$bigCommerceOrderId || !($bigCommerceOrder = $bigCommerceService->getOrder($bigCommerceOrderId)) ) {
            return null;
        }

        return array_map(function ($item) use ($connectWiseService, $cin7Service) {

            if (!Str::contains($item->SKU, 'PROJECT') && !Str::contains($item->SKU, 'TICKET')) {
                // If product type is not a project
                // TODO: handle Azad May Product
                return false;
            }

            $skuParts = array_reverse(explode('-', $item->SKU));

            $ticketId = $skuParts[1] != 'PROJECT' ? $skuParts[0] : null;

            $projectId = $skuParts[1] == 'PROJECT' ? $skuParts[0] : ($skuParts[3] == 'PROJECT' ? $skuParts[2] : null);

            $catalogItemIdentifier = explode('-PROJECT', $item->SKU)[0];

            $shipQuantity = $item->Quantity;

            array_map(function ($product) use ($cin7Service, &$shipQuantity, $connectWiseService) {

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

                try {
                    $connectWiseService->shipProduct($product->id, $shipQuantity);
                } catch (\Exception) {
                    // TODO: Will need to log errors
                    return false;
                }

                $shipQuantity = $shipQuantity <= $shipAvailableQuantity ? 0 : $shipQuantity - $shipAvailableQuantity;

                return $product;

            }, $connectWiseService->getProductsByTicketInfo($catalogItemIdentifier, $projectId, $ticketId));

            return $item;

        }, $cin7Service->saleOrder($request->get('SaleTaskID'))->Lines);
    }
}
