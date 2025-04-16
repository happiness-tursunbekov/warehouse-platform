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
    public function productCatalog(Request $request, Cin7Service $cin7Service, ConnectWiseService $connectWiseService, BigCommerceService $bigCommerceService)
    {
        $action = $request->get('Action');
//        $entity = $request->get('Entity');
        $id = $request->get('ID');

        switch ($action) {
            case ConnectWiseService::ACTION_ADDED:
            case ConnectWiseService::ACTION_UPDATED:
                $catalogItem = $connectWiseService->getCatalogItem($id);

                if ($catalogItem->productClass == 'Bundle') {
                    break;
                }

                if ($catalogItem->inactiveFlag && ($cin7ProductFamilyId = $connectWiseService->extractCin7ProductFamilyId($catalogItem))) {

                    $cin7ProductFamily = $cin7Service->productFamily($cin7ProductFamilyId);

                    if ($cin7ProductFamily) {
                        $cin7Service->updateProductFamily([
                            'ID' => $cin7ProductFamily->ID,
                            'Name' => Cin7Service::PRODUCT_FAMILY_INACTIVE . $cin7ProductFamily->Name
                        ]);

                        $bigCommerceProduct = $bigCommerceService->getProductBySku($cin7ProductFamily->SKU);

                        if ($bigCommerceProduct) {
                            $bigCommerceService->updateProduct($bigCommerceProduct->id, [
                                'is_visible' => false
                            ]);
                        }

                        $bigCommerceProductVariants = count($bigCommerceProduct->variants) > 0 ? collect($bigCommerceProduct->variants) : null;

                        array_map(function ($product) use ($bigCommerceService, $cin7Service, $bigCommerceProductVariants) {
                            $cin7Service->updateProduct([
                                'ID' => $product->ID,
                                'Status' => Cin7Service::PRODUCT_STATUS_DEPRECATED
                            ]);

                            // Setting on hand to 0
                            $cin7Service->stockAdjust($product->ID, 0);

                            if (
                                $bigCommerceProductVariants
                                && ($variant = $bigCommerceProductVariants->where('sku', $product->SKU)->first())
                                && ($variant->inventory_level > 0)
                            ) {
                                // Setting on hand to 0
                                $bigCommerceService->adjustVariant($variant->id, -$variant->inventory_level);
                            }
                        }, $cin7ProductFamily->Products);
                    }

                    $connectWiseService->updateCatalogItemCin7ProductFamilyId($catalogItem, '');

                    break;
                }

                $connectWiseService->publishProductFamilyOnCin7($id, $catalogItem, true);

                $unitOfMeasure = $connectWiseService->unitOfMeasure($catalogItem->unitOfMeasure->id);

                if (!@$unitOfMeasure->uomScheduleXref) {
                    $cin7UnitOfMeasure = $cin7Service->createUnitOfMeasure($unitOfMeasure->name);

                    $unitOfMeasure->uomScheduleXref = substr($cin7UnitOfMeasure->ID, 0, 31);

                    $connectWiseService->updateUnitOfMeasure($unitOfMeasure);
                }
                break;
        }
    }

    public function purchaseOrder(Request $request, ConnectWiseService $connectWiseService, Cin7Service $cin7Service, BigCommerceService $bigCommerceService)
    {
        $action = $request->get('Action');
        $entity = $request->get('Entity');
        $id = $request->get('ID');

        WebhookLog::create([
            'type' => 'PurchaseOrder',
            'data' => $request->all()
        ]);

        if ($entity['vendorCompany']['id'] == ConnectWiseService::AZAD_MAY_ID && !in_array($entity['status']['name'], ['Sent to Vendor', 'Closed'])) {
            return response()->json(['message' => 'No Action']);
        }

        /** @var PurchaseOrder $po */
        $po = PurchaseOrder::find($id);

        if (!$po) {

            // If vendor is Azad May
            if ($entity['vendorCompany']['id'] == ConnectWiseService::AZAD_MAY_ID) {
                if ($entity['status']['name'] == 'Sent to Vendor') {

                    $purchaseOrder = $connectWiseService->purchaseOrder($id);

                    $poItems = $connectWiseService->purchaseOrderItemsOriginal($id);

                    $cin7SalesOrderId = $connectWiseService->extractCin7SalesOrderId($purchaseOrder);

                    $cin7SalesOrder = $cin7SalesOrderId ? $cin7Service->salesOrder($cin7SalesOrderId) : null;

                    if (!$cin7SalesOrder) {

                        PurchaseOrder::create([
                            'id' => $id,
                            'statusId' => $entity['status']['id'],
                            'closedFlag' => $entity['closedFlag']
                        ]);

                        $customerName = 'Binyod';

                        if (Str::contains($entity['businessUnit']['name'], 'Team')) {
                            $customerName .= ' Team' . explode('Team', $entity['businessUnit']['name'])[1];
                        }

                        if (!$cin7Service->customer($customerName)) {
                            $cin7Service->createCustomer($customerName);
                        }

                        $cin7Sale = $cin7Service->createSale($customerName, "ConnectWise PO: {$entity['poNumber']}", true);

                        $connectWiseService->updatePurchaseOrderCin7SalesOrderId($purchaseOrder, $cin7Sale->ID);

                        $cin7Service->createSalesOrder($cin7Sale->ID, $poItems, autoship: true);

                        collect($poItems)->map(function ($poItem) use ($purchaseOrder, $connectWiseService) {
                            $connectWiseService->purchaseOrderItemReceive($purchaseOrder->id, $poItem, $poItem->quantity);
                        });

                        return response()->json(['message' => 'Azad May Purchase']);
                    }
                } else {
                    return response()->json(['message' => 'No Action']);
                }
            }

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
                    ->filter(function ($poItem) use ($cin7Service, $connectWiseService, &$po) {

                        $cin7ProductFamily = $cin7Service->productFamilyBySku($connectWiseService->generateProductFamilySku($poItem->product->identifier));

                        if ($cin7ProductFamily) {
                            $connectWiseService->syncCatalogItemAttachmentsWithCin7(
                                $poItem->product->id,
                                $cin7ProductFamily->ID,
                                true
                            );
                        }

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

                $poItems = collect($connectWiseService->purchaseOrderItemsOriginal($po->id))
                    ->filter(function ($poItem) use (&$po) {

                        $item = $po->items->where('id', $poItem->id)->first();

                        if (!$item) {
                            $item = $po->items()->create([
                                'id' => $poItem->id,
                                'receivedStatus' => PurchaseOrderItem::RECEIVED_STATUS_WAITING,
                                'catalogItemId' => $poItem->product->id
                            ]);
                        }

                        if ($item->receivedStatus == $poItem->receivedStatus) {
                            return false;
                        }

                        if ($poItem->receivedStatus == PurchaseOrderItem::RECEIVED_STATUS_CANCELLED) {
                            $item->fill(['receivedStatus' => PurchaseOrderItem::RECEIVED_STATUS_CANCELLED])->save();
                            return false;
                        }

                        return true;
                    });

                $po->load('items');

                $poItems->map(function ($poItem) use ($bigCommerceService, $cin7Service, $po, $connectWiseService) {

                    $item = $po->items->where('id', $poItem->id)->first();

                    $picking = $item->receivedStatus == PurchaseOrderItem::RECEIVED_STATUS_WAITING
                        && $poItem->receivedStatus == PurchaseOrderItem::RECEIVED_STATUS_FULLY_RECEIVED;

                    $unpicking = $item->receivedStatus == PurchaseOrderItem::RECEIVED_STATUS_FULLY_RECEIVED
                        && $poItem->receivedStatus == PurchaseOrderItem::RECEIVED_STATUS_WAITING;

                    if ($picking) {
                        $connectWiseService->pickOrShipPurchaseOrderItem($po->id, $poItem, callback: function ($product, $quantity) use ($item, $connectWiseService) {
                            $cin7Adjustment = $connectWiseService->publishProductOnCin7($product, $quantity, true, $item->cin7AdjustmentId);

                            if ($cin7Adjustment) {
                                $item->fill(['cin7AdjustmentId' => $cin7Adjustment->TaskID])->save();
                            }
                        });
                    }

                    if ($unpicking) {

                        // Item_ID: Catalog Item Identifier
                        // SR_Service_RecID: Ticket ID
                        $ticket = $connectWiseService->getPurchaseOrderItemTicketInfo($po->id, $poItem->id)[0] ?? null;

                        if (!$ticket) {
                            return false;
                        }

                        $quantity = $poItem->quantity;

                        $products = collect($connectWiseService->getProductsByTicketInfo($ticket));

                        $products->map(function ($product) use ($item, $unpicking, $picking, $bigCommerceService, $cin7Service, &$quantity, $connectWiseService, $po) {

                            if ($quantity == 0) {
                                return false;
                            }

                            $productPoItems = collect($connectWiseService->getProductPoItems($product->id))->where('ID', $po->id);

                            if (!$productPoItems->count()) {
                                return false;
                            }

                            $productPickAndShips = collect($connectWiseService->getProductPickingShippingDetails($product->id));

                            // If unpicking
                            $unpickAvailableQuantity = $productPickAndShips->pluck('pickedQuantity')->sum() - $productPickAndShips->pluck('shippedQuantity')->sum();

                            if (!$unpickAvailableQuantity) {
                                return false;
                            }

                            $connectWiseService->unpickProduct($product->id, $quantity);

                            if ($item->cin7AdjustmentId) {
                                $cin7Service->undoStockAdjustment($item->cin7AdjustmentId);
                            }
                            $connectWiseService->stockTakeOnBigCommerce(
                                $connectWiseService->generateProductSku(
                                    $connectWiseService->generateProductFamilySku($product->catalogItem->identifier),
                                    $product->project->id ?? null,
                                    $product->ticket->id ?? null,
                                    $product->company->id ?? null,
                                ),
                                $quantity
                            );

                            $quantity = $quantity <= $unpickAvailableQuantity ? 0 : $quantity - $unpickAvailableQuantity;

                            return $product;
                        });

                        $item->fill(['receivedStatus' => $poItem->receivedStatus])->save();

                    }

                    return false;
                });

                return response()->json(['message' => 'Updated successfully']);

            default:
                return response()->json(['message' => 'No action needed']);
        }
    }

    public function member(Request $request, BigCommerceService $bigCommerceService)
    {
//        $action = $request->get('Action');
        $entity = $request->get('Entity');
//        $id = $request->get('ID');

        $customerGroup = $bigCommerceService->getCustomerGroups(1, 1, "#{$entity['defaultDepartment']['id']} -")[0] ?? null;

        if (!$customerGroup) {
            $bigCommerceService->createCustomerGroup("#{$entity['defaultDepartment']['id']} - {$entity['defaultDepartment']['name']}");
        }
    }

    public function projects(ConnectWiseService $connectWiseService)
    {
        return response()->json($connectWiseService->getProjects(1, 'status/name != "Cancelled"', 'id,name,company'));
    }

    public function phases(Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'projectId' => ['required', 'integer']
        ]);

        $phases = collect($connectWiseService->getProjectPhases($request->get('projectId'), 'id,description,parentPhase'));

        $phases = $phases->map(function ($phase) use ($phases) {

            $phase->title = $phase->description;

            if (@$phase->parentPhase) {

                $phase->title = "{$phase->parentPhase->name} -> {$phase->title}";

                $parent = $phases->where('id', $phase->parentPhase->id)->first();

                if ($parent && @$parent->parentPhase) {
                    $phase->parentPhase->parentPhase = $parent->parentPhase;

                    $phase->title = "{$phase->parentPhase->parentPhase->name} -> {$phase->title}";

                    $parent1 = $phases->where('id', $parent->parentPhase->id)->first();

                    if ($parent1 && @$parent1->parentPhase) {
                        $phase->parentPhase->parentPhase->parentPhase = $parent1->parentPhase;

                        $phase->title = "{$phase->parentPhase->parentPhase->parentPhase->name} -> {$phase->title}";

                    }
                }
            }

            return $phase;
        });

        return response()->json($phases->sortBy('title')->values());
    }

    public function projectTickets(Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'projectId' => ['required', 'integer'],
            'phaseId' => ['nullable', 'integer']
        ]);

        $projectId = $request->get('projectId');
        $phaseId = $request->get('phaseId', "null");

        $conditions = "project/id={$projectId} and phase/id={$phaseId} and (summary contains 'Product' or summary contains 'Procurement' or summary contains 'Material')";

        return $connectWiseService->getProjectTickets(1, $conditions, 'id,summary,status,closedFlag');
    }

    public function companies(ConnectWiseService $connectWiseService)
    {
        return response()->json($connectWiseService->getCompanies(1, 'status/name != "Cancelled" and deletedFlag=false', null, 'id,name,company', 1000));
    }

    public function bundles(Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'projectId' => ['required_without:ticketId', 'integer'],
            'ticketId' => ['required_without:projectId', 'integer']
        ]);

        $projectId = $request->get('projectId');
        $ticketId = $request->get('ticketId');

        $condition = "productClass='Bundle'";

        if ($ticketId) {
            $condition .= " and ticket/id={$ticketId}";
        } else {
            $condition .= " and project/id={$projectId}";
        }

        return response()->json($connectWiseService->getProducts(1, $condition, 20, fields: 'id,catalogItem,description'));
    }

    public function serviceTickets(Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'companyId' => ['required', 'integer']
        ]);

        $companyId = $request->get('companyId');

        $conditions = "company/id={$companyId} and status/name in ('New','Waiting Products','In-Progress') and closedFlag=false";

        return $connectWiseService->getServiceTickets(1, $conditions, 'id,summary');
    }
}
