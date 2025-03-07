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
    public function saleShipmentAuthorized(Request $request, ConnectWiseService $connectWiseService, BigCommerceService $bigCommerceService)
    {
        $request->validate([
            'SaleTaskID' => ['required', 'string'],
            'OrderNumber' => ['required', 'string'],
            'EventType' => ['required', 'string'],
            'CustomerReference' => ['nullable', 'string']
        ]);

        return response(['message' => 'temporarily inactive']);

        $bigCommerceOrderId = $request->get('CustomerReference');

        if (!$bigCommerceOrderId || !($bigCommerceOrder = $bigCommerceService->getOrder($bigCommerceOrderId)) || $bigCommerceOrder->channel_id != 1) {
            return null;
        }

        $bigCommerceOrderProducts = collect($bigCommerceService->getOrderProducts($bigCommerceOrder->id));

        // Handling Azad May products

        $azadMayProducts = $bigCommerceOrderProducts->filter(fn($item) => !Str::contains($item->sku, '-PROJECT'));

        if ($azadMayProducts->count() > 0) {

            $customer = $bigCommerceService->getCustomer($bigCommerceOrder->customer_id);

            dd($customer);

            $cwProducts = $connectWiseService->createAzadMayPO($azadMayProducts);

            $cwProducts->map(function ($cwProduct) use ($connectWiseService) {
                $connectWiseService->pickProduct($cwProduct->id, $cwProduct->quantity);
                $connectWiseService->shipProduct($cwProduct->id, $cwProduct->quantity);
            });
        }

        return response()->json(['Message Handling Azad May Products']);

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

                try {
                    $connectWiseService->shipProduct($product->id, $shipQuantity);
                } catch (\Exception) {
                    // TODO: Will need to log errors
                    return false;
                }

                $shipQuantity = $shipQuantity <= $shipAvailableQuantity ? 0 : $shipQuantity - $shipAvailableQuantity;

                return $product;

            }, $connectWiseService->getProductsByTicketInfo($catalogItemIdentifier, $ticketId, $projectId));

            return $item;

        });
    }
}
