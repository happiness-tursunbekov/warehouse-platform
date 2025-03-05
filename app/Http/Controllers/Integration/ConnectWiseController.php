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

                    $sharedModifierBundle = $bigCommerceService->getSharedModifierByName(BigCommerceService::PRODUCT_OPTION_BUNDLE);

                    $modifierValueId = $connectWiseService->extractBigCommerceModifierId($catalogItem);

                    if ($catalogItem->inactiveFlag) {
                        if ($modifierValueId) {
                            $bigCommerceService->removeSharedModifierValue($sharedModifierBundle->id, $modifierValueId);
                        }

                        $catalogItem = $connectWiseService->setBigCommerceModifierId($catalogItem, '');

                        $connectWiseService->updateCatalogItem($catalogItem);

                        break;
                    }

                    if ($modifierValueId) {

                        $modifierValue = $bigCommerceService->getSharedValueById($sharedModifierBundle, $modifierValueId);

                        if ($modifierValue) {
                            $modifierValue->label = $catalogItem->identifier;

                            $bigCommerceService->updateSharedModifierValue($sharedModifierBundle, $modifierValue);

                            break;
                        }
                    }

                    $modifierValue = $bigCommerceService->addSharedModifierValue($sharedModifierBundle, $catalogItem->identifier);

                    $catalogItem = $connectWiseService->setBigCommerceModifierId($catalogItem, $modifierValue->id);

                    $connectWiseService->updateCatalogItem($catalogItem);

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

        return response()->json(['message' => 'Service is temporarily inactive'], 200);

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

                        $picking = $item->receivedStatus == PurchaseOrderItem::RECEIVED_STATUS_WAITING
                            && $poItem->receivedStatus == PurchaseOrderItem::RECEIVED_STATUS_FULLY_RECEIVED;

                        $unpicking = $item->receivedStatus == PurchaseOrderItem::RECEIVED_STATUS_FULLY_RECEIVED
                            && $poItem->receivedStatus == PurchaseOrderItem::RECEIVED_STATUS_WAITING;

                        if ($picking || $unpicking) {

                            // Item_ID: Catalog Item Identifier
                            // SR_Service_RecID: Ticket ID
                            $ticket = $connectWiseService->getPurchaseOrderItemTicketInfo($po->id, $poItem->id)[0];

                            $quantity = $poItem->quantity;

                            // Checking if pick/unpick quantity matches available quantity before processing to sync
                            $results = collect($connectWiseService->getProductsByTicketInfo($ticket->Item_ID, $ticket->PM_Project_RecID, $ticket->SR_Service_RecID))
                                ->map(function ($product) use ($unpicking, $picking, $bigCommerceService, $cin7Service, &$quantity, $connectWiseService, $po) {

                                    if (!$quantity) {
                                        return false;
                                    }

                                    $productPoItems = collect($connectWiseService->getProductPoItems($product->id))->where('ID', $po->id);

                                    if (!$productPoItems->count()) {
                                        return false;
                                    }

                                    $productPickAndShips = collect($connectWiseService->getProductPickingShippingDetails($product->id));

                                    if ($picking) {

                                        $pickAvailableQuantity = $product->quantity - $productPickAndShips->pluck('pickedQuantity')->sum();

                                        if ($product->quantity == $productPickAndShips->pluck('shippedQuantity')->sum()) {
                                            return false;
                                        }

                                        $result = [
                                            'product' => $product,
                                            'quantity' => $quantity
                                        ];

                                        $quantity = $quantity <= $pickAvailableQuantity ? 0 : $quantity - $pickAvailableQuantity;

                                        return $result;

                                    }

                                    // If unpicking
                                    $unpickAvailableQuantity = $productPickAndShips->pluck('pickedQuantity')->sum() - $productPickAndShips->pluck('shippedQuantity')->sum();

                                    if (!$unpickAvailableQuantity) {
                                        return false;
                                    }

                                    $result = [
                                        'product' => $product,
                                        'quantity' => $quantity
                                    ];

                                    $quantity = $quantity <= $unpickAvailableQuantity ? 0 : $quantity - $unpickAvailableQuantity;

                                    return $result;
                                })
                            ;

                            // Skipping in case pick/unpick quantity is greater than available quantity
                            if ($quantity) {
                                return false;
                            }

                            // Processing syncing
                            $results->filter(fn($results) => !!$results)
                                ->map(function (array $result) use ($item, $cin7Service, $connectWiseService, $picking) {
                                    if ($picking) {
                                        try {
                                            $connectWiseService->pickProduct($result['product']->id, $result['quantity']);
                                        } catch (\Exception) {
                                            // TODO: Will need to log errors
                                            return false;
                                        }

                                        $cin7Adjustment = $connectWiseService->publishProductOnCin7($result['product'], $result['quantity'], true, $item->cin7AdjustmentId);

                                        if ($cin7Adjustment) {
                                            $item->fill(['cin7AdjustmentId' => $cin7Adjustment->TaskID])->save();
                                        }

                                        return $result['product'];
                                    }

                                    // If unpicking
                                    try {
                                        $connectWiseService->unpickProduct($result['product']->id, $result['quantity']);
                                    } catch (\Exception) {
                                        // TODO: Will need to log errors
                                        return false;
                                    }

                                    if ($item->cin7AdjustmentId) {
                                        $cin7Service->undoStockAdjustment($item->cin7AdjustmentId);
                                    }
                                    $connectWiseService->stockTakeOnBigCommerce(
                                        $connectWiseService->generateProductSku(
                                            $connectWiseService->generateProductFamilySku($result['product']->catalogItem->identifier),
                                            $result['product']->project->id ?? null,
                                            $result['product']->ticket->id ?? null,
                                            $result['product']->company->id ?? null,
                                        ),
                                        $result['quantity']
                                    );

                                    return $result['product'];
                                });

                            $item->fill(['receivedStatus' => $poItem->receivedStatus])->save();

                        }

                        return false;
                    })
                ;

                return response()->json(['message' => 'Updated successfully']);

            default:
                return response()->json(['message' => 'No action needed']);
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

        $conditions = "project/id={$projectId} and phase/id={$phaseId}";

        return $connectWiseService->getProjectTickets(1, $conditions, 'id,summary,status,closedFlag');
    }

    public function companies(ConnectWiseService $connectWiseService)
    {
        return response()->json($connectWiseService->getCompanies(1, 'status/name != "Cancelled" and deletedFlag=false', null, 'id,name,company', 1000));
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
