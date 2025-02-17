<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\WebhookLog;
use App\Services\BigCommerceService;
use App\Services\Cin7Service;
use App\Services\ConnectWiseService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConnectWiseController extends Controller
{
    public function productCatalog(Request $request)
    {
        WebhookLog::create([
            'type' => 'ProductCatalog',
            'data' => $request->all()
        ]);
    }

    public function project(Request $request)
    {
        WebhookLog::create([
            'type' => 'Project',
            'data' => $request->all()
        ]);
    }

    public function purchaseOrder(Request $request, ConnectWiseService $connectWiseService, Cin7Service $cin7Service, BigCommerceService $bigCommerceService)
    {
        return response()->json([
            'data' => WebhookLog::create([
                'type' => 'PurchaseOrder',
                'data' => $request->all()
            ])
        ]);

        $action = $request->get('Action');
        $entity = $request->get('Entity');
        $id = $request->get('ID');

        /** @var PurchaseOrder $po */
        $po = PurchaseOrder::find($id);

        if (!$po) {

            if ($action == ConnectWiseService::ACTION_DELETED) {
                return response()->json(['message', 'Deleted successfully!']);
            }

            $po = PurchaseOrder::create([
                'id' => $id,
                'statusId' => $entity['status']['id'],
                'closedFlag' => $entity['closedFlag']
            ]);
        } else {
            $po->fill([
                'statusId' => $entity['status']['id'],
                'closedFlag' => $entity['closedFlag']
            ])->save();
        }

        switch ($action) {
            case ConnectWiseService::ACTION_ADDED:

                $itemsData = collect($connectWiseService->purchaseOrderItemsOriginal($po->id))
                    ->filter(function ($poItem) use (&$po) {
                        return !$po->items->where('id', $poItem->id)->first();
                    })
                    ->map(function ($poItem) {
                        return [
                            'id' => $poItem->id,
                            'receivedStatus' => $poItem->receivedStatus,
                            'catalogItemId' => $poItem->product->id
                        ];
                    });

                $po->items()->createMany($itemsData);

                return response()->json(['message' => 'Added successfully']);

            case ConnectWiseService::ACTION_UPDATED:

                collect($connectWiseService->purchaseOrderItemsOriginal($po->id))
                    ->filter(function ($poItem) use (&$po) {

                        $item = $po->items->where('id', $poItem->id)->first();

                        if (!$item) {
                            $po->items()->create([
                                'id' => $poItem->id,
                                'receivedStatus' => $poItem->receivedStatus,
                                'catalogItemId' => $poItem->product->id
                            ]);

                            return false;
                        }

                        if ($item->receivedStatus == $poItem->receivedStatus) {
                            return false;
                        }

                        if ($poItem->receivedStatus == PurchaseOrderItem::RECEIVED_STATUS_CANCELLED) {
                            $item->fill(['receivedStatus' => PurchaseOrderItem::RECEIVED_STATUS_CANCELLED])->save();
                            return false;
                        }

                        return true;
                    })
                    ->map(function ($poItem) use ($bigCommerceService, $cin7Service, $po, $connectWiseService) {

                        $item = $po->items->where('id', $poItem->id)->first();

                        if (
                            ($item->receivedStatus == PurchaseOrderItem::RECEIVED_STATUS_WAITING
                            && $poItem->receivedStatus == PurchaseOrderItem::RECEIVED_STATUS_FULLY_RECEIVED)
                        ) {
                            // Item_ID: Catalog Item Identifier
                            // SR_Service_RecID: Ticket ID
                            $ticket = $connectWiseService->getPurchaseOrderItemTicketInfo($po->id, $poItem->id)[0];

                            $conditions = "catalogItem/identifier='{$ticket->Item_ID}' and cancelledFlag=false";

                            $conditions .= $ticket->SR_Service_RecID ? " and ticket/id={$ticket->SR_Service_RecID}" : " and project/id={$ticket->PM_Project_RecID}";

                            $pickQuantity = $poItem->quantity;

                            array_map(function ($product) use ($bigCommerceService, $cin7Service, &$pickQuantity, $connectWiseService, $po) {

                                if ($pickQuantity == 0) {
                                    return false;
                                }

                                $productPoItems = collect($connectWiseService->getProductPoItems($product->id))->where('ID', $po->id);

                                if (!$productPoItems->count()) {
                                    return false;
                                }

                                $productPickAndShips = collect($connectWiseService->getProductPickingShippingDetails($product->id));

                                $pickAvailableQuantity = $product->quantity - $productPickAndShips->pluck('pickedQuantity')->sum();

                                if ($product->quantity == $productPickAndShips->pluck('shippedQuantity')->sum()) {
                                    return false;
                                }

                                $connectWiseService->pickProduct($product->id, $pickQuantity);

                                $cin7Product = $connectWiseService->publishProductOnCin7($product, $pickQuantity, true);

                                $pickQuantity = $pickQuantity <= $pickAvailableQuantity ? 0 : $pickQuantity - $pickAvailableQuantity;

                                return $cin7Product;

                            }, $connectWiseService->getProducts(null, $conditions, 1000));

                            $item->fill(['receivedStatus' => $poItem->receivedStatus])->save();
                        }

                        // TODO: Handle unreceived products
                    })
                ;

                return response()->json(['message' => 'Updated successfully']);
        }
    }
}
