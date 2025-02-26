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

    public function project(Request $request, BigCommerceService $bigCommerceService, ConnectWiseService $connectWiseService)
    {
        $action = $request->get('Action');
        $entity = $request->get('Entity');
        $id = $request->get('ID');

        $project = $connectWiseService->getProject($id);

        switch ($action) {
            case ConnectWiseService::ACTION_ADDED:
            case ConnectWiseService::ACTION_UPDATED:

                $sharedModifierProject = $bigCommerceService->getSharedModifierProject();
                $sharedOptionProject = $bigCommerceService->getSharedOptionProject();

                $modifierId = $connectWiseService->extractBigCommerceModifierId($project);
                $optionId = $connectWiseService->extractBigCommerceOptionId($project);

                $sharedModifierProjectValue = $modifierId ? $bigCommerceService->getSharedValueById($sharedModifierProject, $modifierId) : null;
                $sharedOptionProjectValue = $optionId ? $bigCommerceService->getSharedValueById($sharedOptionProject, $optionId) : null;

                $generatedProjectName = $connectWiseService->generateProjectName($project->id, $project->name);

                if ($project->status->name == 'Cancelled') {

                    if ($sharedModifierProjectValue) {
                        $bigCommerceService->removeSharedModifierValue($sharedModifierProject->id, $sharedModifierProjectValue->id);
                    }

                    if ($sharedOptionProjectValue) {
                        $bigCommerceService->removeSharedOptionValue($sharedOptionProject->id, $sharedOptionProjectValue->id);
                    }

                    $project = $connectWiseService->setBigCommerceOptionId($project, '');

                    $project = $connectWiseService->setBigCommerceModifierId($project, '');

                    $connectWiseService->updateProject($project);

                } else {

                    if (!$sharedOptionProjectValue) {

                        $optionValue = $bigCommerceService->addSharedOptionValueIfNotExists($bigCommerceService->getSharedOptionProject(), $generatedProjectName);

                        $project = $connectWiseService->setBigCommerceOptionId($project, $optionValue->id);

                    } elseif ($sharedOptionProjectValue->label != $generatedProjectName) {

                        $sharedOptionProjectValue->label = $generatedProjectName;

                        $bigCommerceService->updateSharedOptionValue($sharedOptionProject->id, $sharedOptionProjectValue);

                    }

                    if (!$sharedModifierProjectValue) {

                        $modifierValue = $bigCommerceService->addSharedModifierValueIfNotExists($bigCommerceService->getSharedModifierProject(), $generatedProjectName);

                        $project = $connectWiseService->setBigCommerceModifierId($project, $modifierValue->id);

                    }  elseif ($sharedModifierProjectValue->label != $generatedProjectName) {

                        $sharedModifierProjectValue->label = $generatedProjectName;

                        $bigCommerceService->updateSharedModifierValue($sharedModifierProject->id, $sharedModifierProjectValue);

                    }

                    if (!$sharedOptionProjectValue || !$sharedModifierProjectValue) {

                        $connectWiseService->updateProject($project);

                    }
                }

                break;

            case ConnectWiseService::ACTION_DELETED:

                $projectTitle = $connectWiseService->generateProjectName($entity['project']['id'], $entity['project']['name']);

                $sharedModifierProject = $bigCommerceService->getSharedModifierProject();
                $sharedOptionProject = $bigCommerceService->getSharedOptionProject();

                $sharedModifierServiceProject = $bigCommerceService->getSharedValueByTitle($sharedModifierProject, $projectTitle);
                $sharedOptionServiceProject = $bigCommerceService->getSharedValueByTitle($sharedOptionProject, $projectTitle);

                if ($sharedModifierServiceProject) {
                    $bigCommerceService->removeSharedModifierValue($sharedModifierProject->id, $sharedModifierServiceProject->id);
                }

                if ($sharedOptionServiceProject) {
                    $bigCommerceService->removeSharedOptionValue($sharedOptionProject->id, $sharedOptionServiceProject->id);
                }

                break;
        }

        return response()->json(['message' => 'Successful!']);
    }

    public function ticket(Request $request, ConnectWiseService $connectWiseService, BigCommerceService $bigCommerceService)
    {
        $action = $request->get('Action');
        $entity = $request->get('Entity');
        $id = $request->get('ID');

        if (@$entity['project']) {

            $ticket = $connectWiseService->ticket($id, true);

            $phase = @$ticket->phase ? $connectWiseService->getProjectPhase($ticket->project->id, $ticket->phase->id) : null;

            switch ($action) {
                case ConnectWiseService::ACTION_ADDED:
                case ConnectWiseService::ACTION_UPDATED:

                    $sharedModifierProjectTicket = $bigCommerceService->getSharedModifierProjectTicket();
                    $sharedOptionProjectTicket = $bigCommerceService->getSharedOptionProjectTicket();

                    $modifierId = $connectWiseService->extractBigCommerceModifierId($ticket);
                    $optionId = $connectWiseService->extractBigCommerceOptionId($ticket);

                    $sharedModifierProjectTicketValue = $modifierId ? $bigCommerceService->getSharedValueById($sharedModifierProjectTicket, $modifierId) : null;
                    $sharedOptionProjectTicketValue = $optionId ? $bigCommerceService->getSharedValueById($sharedOptionProjectTicket, $optionId) : null;

                    $ticketTitle = $connectWiseService->generateProjectTicketName($ticket->project->id, $ticket->id, $ticket->summary, $phase->id ?? null);

                    if ($ticket->status->name == '>Closed') {

                        if ($sharedModifierProjectTicketValue) {
                            $bigCommerceService->removeSharedModifierValue($sharedModifierProjectTicket->id, $sharedModifierProjectTicketValue->id);
                        }

                        if ($sharedOptionProjectTicketValue) {
                            $bigCommerceService->removeSharedOptionValue($sharedOptionProjectTicket->id, $sharedOptionProjectTicketValue->id);
                        }

                        $ticket = $connectWiseService->setBigCommerceOptionId($ticket, '');

                        $ticket = $connectWiseService->setBigCommerceModifierId($ticket, '');

                        $connectWiseService->updateTicket($ticket);
                    } else {
                        if (!$phase) {
                            if (!$sharedOptionProjectTicketValue) {

                                $optionValue = $bigCommerceService->addSharedOptionValueIfNotExists($bigCommerceService->getSharedOptionProjectTicket(), $ticketTitle);

                                $ticket = $connectWiseService->setBigCommerceOptionId($ticket, $optionValue->id);

                            } elseif ($sharedOptionProjectTicketValue != $ticketTitle) {

                                $bigCommerceService->updateSharedOptionValue($sharedOptionProjectTicket->id, $sharedOptionProjectTicketValue);

                            }
                        }

                        if (!$sharedModifierProjectTicketValue) {

                            $modifierValue = $bigCommerceService->addSharedModifierValueIfNotExists($bigCommerceService->getSharedModifierProjectTicket(), $ticketTitle);

                            $ticket = $connectWiseService->setBigCommerceModifierId($ticket, $modifierValue->id);

                        } elseif ($sharedModifierProjectTicketValue->label != $ticketTitle) {

                            $bigCommerceService->updateSharedModifierValue($sharedModifierProjectTicket->id, $sharedModifierProjectTicketValue);

                        }

                        if (!$sharedOptionProjectTicketValue || !$sharedModifierProjectTicketValue) {

                            $connectWiseService->updateTicket($ticket);

                        }
                    }

                    // Handling phase actions. Because there is no phase webhook
                    if ($phase) {

                        $phaseTitle = $connectWiseService->generatePhaseName($ticket->project->id, $ticket->phase->id, $phase);

                        $sharedModifierPhase = $bigCommerceService->getSharedModifierPhase();
                        $sharedOptionPhase = $bigCommerceService->getSharedOptionPhase();

                        $modifierId = $connectWiseService->extractBigCommerceModifierId($phase);
                        $optionId = $connectWiseService->extractBigCommerceOptionId($phase);

                        $sharedModifierPhaseValue = $bigCommerceService->getSharedValueById($sharedModifierPhase, $modifierId);
                        $sharedOptionPhaseValue = $bigCommerceService->getSharedValueById($sharedOptionPhase, $optionId);

                        if ($phase->status->name == 'Closed') {

                            if ($sharedModifierPhaseValue) {
                                $bigCommerceService->removeSharedModifierValue($sharedModifierPhase->id, $sharedModifierPhaseValue->id);
                            }

                            if ($sharedOptionPhaseValue) {
                                $bigCommerceService->removeSharedOptionValue($sharedOptionPhase->id, $sharedOptionPhaseValue->id);
                            }

                            $phase = $connectWiseService->setBigCommerceOptionId($phase, '');

                            $phase = $connectWiseService->setBigCommerceModifierId($phase, '');

                            $connectWiseService->updatePhase($phase);

                        } else {

                            if (!$sharedOptionPhaseValue) {
                                $optionValue = $bigCommerceService->addSharedOptionValueIfNotExists($bigCommerceService->getSharedOptionPhase(), $phaseTitle);

                                $phase = $connectWiseService->setBigCommerceOptionId($phase, $optionValue->id);
                            } elseif ($sharedOptionPhaseValue->label != $phaseTitle) {
                                $bigCommerceService->updateSharedOptionValue($sharedOptionPhase->id, $sharedOptionPhaseValue);
                            }

                            if (!$sharedModifierPhaseValue) {
                                $modifierValue = $bigCommerceService->addSharedModifierValueIfNotExists($bigCommerceService->getSharedModifierPhase(), $phaseTitle);

                                $phase = $connectWiseService->setBigCommerceModifierId($phase, $modifierValue->id);
                            } elseif ($sharedModifierPhaseValue->label != $phaseTitle) {
                                $bigCommerceService->updateSharedModifierValue($sharedModifierPhase->id, $sharedModifierPhaseValue);
                            }

                            if (!$sharedOptionPhaseValue || !$sharedModifierPhaseValue) {
                                $connectWiseService->updatePhase($phase);
                            }

                        }
                    }

                    break;

                case ConnectWiseService::ACTION_DELETED:

                    $ticketTitle = $connectWiseService->generateProjectTicketName($entity['project']['id'], $id, $ticket['summary'], $ticket['phase']['id'] ?? null);

                    $sharedModifierProjectTicket = $bigCommerceService->getSharedModifierProjectTicket();

                    $sharedModifierProjectTicketValue = $bigCommerceService->getSharedValueByTitle($sharedModifierProjectTicket, $ticketTitle);

                    if ($sharedModifierProjectTicketValue) {
                        $bigCommerceService->removeSharedModifierValue($sharedModifierProjectTicket->id, $sharedModifierProjectTicketValue->id);
                    }

                    if (!@$ticket['phase']['id']) {

                        $sharedOptionProjectTicket = $bigCommerceService->getSharedOptionProjectTicket();

                        $sharedOptionProjectTicketValue = $bigCommerceService->getSharedValueByTitle($sharedOptionProjectTicket, $ticketTitle);

                        if ($sharedOptionProjectTicketValue) {
                            $bigCommerceService->removeSharedModifierValue($sharedOptionProjectTicket->id, $sharedOptionProjectTicketValue->id);
                        }

                    }

                    break;
            }

        } elseif (@$entity['company']) {
            $ticket = $connectWiseService->ticket($id);

            switch ($action) {
                case ConnectWiseService::ACTION_ADDED:
                case ConnectWiseService::ACTION_UPDATED:

                    $sharedModifierServiceTicket = $bigCommerceService->getSharedModifierServiceTicket();
                    $sharedOptionServiceTicket = $bigCommerceService->getSharedOptionServiceTicket();

                    $modifierId = $connectWiseService->extractBigCommerceModifierId($ticket);
                    $optionId = $connectWiseService->extractBigCommerceOptionId($ticket);

                    $sharedModifierServiceTicketValue = $modifierId ? $bigCommerceService->getSharedValueById($sharedModifierServiceTicket, $modifierId) : null;
                    $sharedOptionServiceTicketValue = $optionId ? $bigCommerceService->getSharedValueById($sharedOptionServiceTicket, $optionId) : null;

                    $ticketTitle = $connectWiseService->generateServiceTicketName($ticket->company->id, $ticket->id, $ticket->summary);

                    if ($ticket->status->name == '>Closed') {

                        if ($sharedModifierServiceTicketValue) {
                            $bigCommerceService->removeSharedModifierValue($sharedModifierServiceTicket->id, $sharedModifierServiceTicketValue->id);
                        }

                        if ($sharedOptionServiceTicketValue) {
                            $bigCommerceService->removeSharedOptionValue($sharedOptionServiceTicket->id, $sharedOptionServiceTicketValue->id);
                        }

                        $ticket = $connectWiseService->setBigCommerceOptionId($ticket, '');

                        $ticket = $connectWiseService->setBigCommerceModifierId($ticket, '');

                        $connectWiseService->updateTicket($ticket);

                        break;

                    }

                    if (!$sharedOptionServiceTicketValue) {

                        $optionValue = $bigCommerceService->addSharedOptionValueIfNotExists($sharedOptionServiceTicket, $ticketTitle);

                        $ticket = $connectWiseService->setBigCommerceOptionId($ticket, $optionValue->id);

                    } elseif ($sharedOptionServiceTicketValue->label != $ticketTitle) {

                        $sharedOptionServiceTicketValue->label = $ticketTitle;

                        $bigCommerceService->updateSharedOptionValue($sharedOptionServiceTicket->id, $sharedOptionServiceTicketValue);

                    }

                    if (!$sharedModifierServiceTicketValue) {

                        $modifierValue = $bigCommerceService->addSharedModifierValueIfNotExists($sharedModifierServiceTicket, $ticketTitle);

                        $ticket = $connectWiseService->setBigCommerceModifierId($ticket, $modifierValue->id);

                    } elseif ($sharedModifierServiceTicketValue->label != $ticketTitle) {

                        $sharedModifierServiceTicketValue->label = $ticketTitle;

                        $bigCommerceService->updateSharedOptionValue($sharedModifierServiceTicket->id, $sharedModifierServiceTicketValue);

                    }

                    if (!$sharedOptionServiceTicketValue || !$sharedModifierServiceTicketValue) {
                        $connectWiseService->updateTicket($ticket);
                    }

                    break;

                case ConnectWiseService::ACTION_DELETED:

                    $ticketTitle = $connectWiseService->generateServiceTicketName($entity['company']['id'], $id, $ticket['summary']);

                    $sharedModifierServiceTicket = $bigCommerceService->getSharedModifierServiceTicket();
                    $sharedOptionServiceTicket = $bigCommerceService->getSharedOptionServiceTicket();

                    $sharedModifierServiceTicketValue = $bigCommerceService->getSharedValueByTitle($sharedModifierServiceTicket, $ticketTitle);
                    $sharedOptionServiceTicketValue = $bigCommerceService->getSharedValueByTitle($sharedOptionServiceTicket, $ticketTitle);

                    if ($sharedModifierServiceTicketValue) {
                        $bigCommerceService->removeSharedModifierValue($sharedModifierServiceTicket->id, $sharedModifierServiceTicketValue->id);
                    }

                    if ($sharedOptionServiceTicketValue) {
                        $bigCommerceService->removeSharedOptionValue($sharedOptionServiceTicket->id, $sharedOptionServiceTicketValue->id);
                    }

                    break;
            }
        }
    }

    public function company(Request $request, BigCommerceService $bigCommerceService, ConnectWiseService $connectWiseService)
    {
        $action = $request->get('Action');
        $entity = $request->get('Entity');
        $id = $request->get('ID');

        $company = $connectWiseService->company($id);

        switch ($action) {
            case ConnectWiseService::ACTION_ADDED:
            case ConnectWiseService::ACTION_UPDATED:

                $sharedModifierCompany = $bigCommerceService->getSharedModifierCompany();
                $sharedOptionCompany = $bigCommerceService->getSharedOptionCompany();

                $modifierId = $connectWiseService->extractBigCommerceModifierId($company);
                $optionId = $connectWiseService->extractBigCommerceOptionId($company);

                $sharedModifierCompanyValue = $modifierId ? $bigCommerceService->getSharedValueById($sharedModifierCompany, $modifierId) : null;
                $sharedOptionCompanyValue = $optionId ? $bigCommerceService->getSharedValueById($sharedOptionCompany, $optionId) : null;

                $generatedCompanyName = $connectWiseService->generateCompanyName($company->id, $company->name);

                if ($company->status->name != 'Active') {

                    if ($sharedModifierCompanyValue) {
                        $bigCommerceService->removeSharedModifierValue($sharedModifierCompany->id, $sharedModifierCompanyValue->id);
                    }

                    if ($sharedOptionCompanyValue) {
                        $bigCommerceService->removeSharedOptionValue($sharedOptionCompany->id, $sharedOptionCompanyValue->id);
                    }

                    $company = $connectWiseService->setBigCommerceOptionId($company, '');

                    $company = $connectWiseService->setBigCommerceModifierId($company, '');

                    $connectWiseService->updateCompany($company);

                } else {

                    if (!$sharedOptionCompanyValue) {

                        $optionValue = $bigCommerceService->addSharedOptionValueIfNotExists($bigCommerceService->getSharedOptionCompany(), $generatedCompanyName);

                        $company = $connectWiseService->setBigCommerceOptionId($company, $optionValue->id);

                    } elseif ($sharedOptionCompanyValue->label != $generatedCompanyName) {

                        $sharedOptionCompanyValue->label = $generatedCompanyName;

                        $bigCommerceService->updateSharedOptionValue($sharedOptionCompany->id, $sharedOptionCompanyValue);

                    }

                    if (!$sharedModifierCompanyValue) {

                        $modifierValue = $bigCommerceService->addSharedModifierValueIfNotExists($bigCommerceService->getSharedModifierCompany(), $generatedCompanyName);

                        $company = $connectWiseService->setBigCommerceModifierId($company, $modifierValue->id);

                    }  elseif ($sharedModifierCompanyValue->label != $generatedCompanyName) {

                        $sharedModifierCompanyValue->label = $generatedCompanyName;

                        $bigCommerceService->updateSharedModifierValue($sharedModifierCompany->id, $sharedModifierCompanyValue);

                    }

                    if (!$sharedOptionCompanyValue || !$sharedModifierCompanyValue) {

                        $connectWiseService->updateCompany($company);

                    }
                }

                break;

            case ConnectWiseService::ACTION_DELETED:

                $companyTitle = $connectWiseService->generateCompanyName($entity['company']['id'], $entity['company']['name']);

                $sharedModifierCompany = $bigCommerceService->getSharedModifierCompany();
                $sharedOptionCompany = $bigCommerceService->getSharedOptionCompany();

                $sharedModifierServiceCompany = $bigCommerceService->getSharedValueByTitle($sharedModifierCompany, $companyTitle);
                $sharedOptionServiceCompany = $bigCommerceService->getSharedValueByTitle($sharedOptionCompany, $companyTitle);

                if ($sharedModifierServiceCompany) {
                    $bigCommerceService->removeSharedModifierValue($sharedModifierCompany->id, $sharedModifierServiceCompany->id);
                }

                if ($sharedOptionServiceCompany) {
                    $bigCommerceService->removeSharedOptionValue($sharedOptionCompany->id, $sharedOptionServiceCompany->id);
                }

                break;
        }

        return response()->json(['message' => 'Successful!']);
    }

    public function purchaseOrder(Request $request, ConnectWiseService $connectWiseService, Cin7Service $cin7Service, BigCommerceService $bigCommerceService)
    {
        $action = $request->get('Action');
        $entity = $request->get('Entity');
        $id = $request->get('ID');

        // TODO: remove
        return false;

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

                                    if (!@$product->project) {
                                        // TODO: Handle sales order product
                                        // Skipping for now
                                        return false;
                                    }

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
}
