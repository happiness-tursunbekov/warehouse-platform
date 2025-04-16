<?php

namespace App\Http\Controllers;


use App\Models\User;
use App\Services\BigCommerceService;
use App\Services\Cin7Service;
use App\Services\ConnectWiseService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ConnectWiseService $connectWiseService, Request $request)
    {
        $request->validate([
            'page' => ['integer', 'min:1'],
            'identifier' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'barcode' => ['nullable', 'string'],
            'conditions' => ['nullable', 'string'],
            'perPage' => ['nullable', 'integer']
        ]);

        $perPage = $request->get('perPage', 25);

        $user = $request->user();

        $identifier = $request->get('identifier');
        $description = $request->get('description');
        $barcode = $request->get('barcode');

        $conditions = $request->get('conditions', '');

        if ($conditions) {
            $conditions .= ' and ';
        }

        $conditions .= "inactiveFlag=false";

        $customFieldConditions=null;

        if ($identifier)
            $conditions .= " and identifier contains '{$identifier}'";
        if ($description)
            $conditions .= " and customerDescription contains '{$description}'";
        if ($barcode) {
            $customFieldConditions = "caption='Barcodes' and value contains '{$barcode}'";
        }

        $page = (int)$request->get('page', 1);

        $products = collect($connectWiseService->getCatalogItems($page, $conditions, $customFieldConditions));

        $qty = $connectWiseService->getCatalogItemsQty($conditions)->count ?? 0;

        if ($qty > 0) {

            $checked = new Collection();

            if ($user->reportMode) {
                User::all()->map(function (User $user) use ($connectWiseService, &$checked) {
                    $checked->push(...$connectWiseService->getUserReportByUserId($user->id, 'ProductChecked'));
                });
            }

            $onHands = collect($connectWiseService->getProductCatalogOnHand(null, "catalogItem/id in ({$products->pluck('id')->join(',')})", null, $perPage));
            $products->map(function (\stdClass $product) use ($connectWiseService, $onHands, $checked, $user) {
                $product->barcodes = $connectWiseService->extractBarcodesFromCatalogItem($product);
                $product->files = [];
                $product->onHand = $onHands->where('catalogItem.id', $product->id)->first()->onHand ?? 0;

                if ($user->reportMode) {
                    $product->checked = $checked->where('item.id', '=', $product->id)->where('item.checked', '=', true)->count() > 0;
                }

                return $product;
            });
        }

        return response()->json([
            'products' => $products,
            'meta' => [
                'total' => $qty,
                'currentPage' => $page,
                'perPage' => $perPage,
                'totalPages' => ceil($qty / $perPage),
            ],
        ]);
    }

    public function onHand($id, ConnectWiseService $connectWiseService)
    {
        return response()->json($connectWiseService->getCatalogItemOnHand($id, ConnectWiseService::AZAD_MAY_WAREHOUSE_DEFAULT_BIN)->count);
    }

    public function images($id, ConnectWiseService $connectWiseService)
    {
        return response()->json($connectWiseService->getAttachments('ProductSetup', $id));
    }

    public function image($attachmentId, ConnectWiseService $connectWiseService)
    {
        return $connectWiseService->downloadAttachment($attachmentId);
    }

    public function receive(Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'id' => ['required', 'integer']
        ]);

        $id = $request->get('id');

        $quantity = $request->get('quantity');

        try {
            $item = $connectWiseService->purchaseOrderItemReceiveUsingCache($id, $quantity);
        } catch (GuzzleException $e) {
            return response()->json(['code' => 'ERROR', 'message' => json_decode($e->getResponse()->getBody()->getContents())->errors[0]->message]);
        }

        return response()->json(['code' => 'SUCCESS', 'item' => $item]);
    }

    public function poItems(Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'poId' => ['nullable', 'string', 'required_without_all:identifier,barcode'],
            'identifier' => ['nullable', 'string', 'required_without_all:poId,barcode'],
            'barcode' => ['nullable', 'string', 'required_without_all:poId,identifier']
        ]);

        $poId = $request->get('poId');
        $identifier = $request->get('identifier');
        $barcode = $request->get('barcode');

        $poItems = new Collection();

        $catalogItems = null;

        if ($barcode) {
            $catalogItems = $connectWiseService->getCatalogItemsByBarcode($barcode);
            if (count($catalogItems) == 0) {
                return response()->json([
                    'items' => [],
                    'code' => 'BARCODE_NOT_FOUND'
                ]);
            }
            $catalogItems = collect($catalogItems);
            $poItems = $connectWiseService->getOpenPoItems()->whereIn('productId', $catalogItems->pluck('id')->values());
        }

        if ($identifier) {
            $poItems = $connectWiseService->getOpenPoItems()->filter(function (\stdClass $item) use ($identifier) {
                return false !== stripos($item->productIdentifier, $identifier);
            });
        }

        if ($poId) {
            $poItems = collect($connectWiseService->purchaseOrderItems($poId, null, 'canceledFlag=false'));
        }

        if (!$catalogItems)
            $catalogItems = collect($connectWiseService->getCatalogItems(null, "id in ({$poItems->pluck('productId')->values()->join(',')})"));

        return response()->json([
            'items' => $poItems->map(function (\stdClass $product) use ($catalogItems, $connectWiseService) {

                $catalogItem = $catalogItems->where('id', $product->productId)->first();

                $product->barcodes = $catalogItem ? $connectWiseService->extractBarcodesFromCatalogItem($catalogItem) : [];
                return $product;
            }),
            'code' => 'SUCCESS'
        ]);
    }

    public function pos(Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'poNumber' => ['required', 'string']
        ]);

        $poNumber = $request->get('poNumber');

        $pos = $connectWiseService->purchaseOrders(null, "poNumber contains '{$poNumber}'");

        return response()->json([
            'items' => $pos
        ]);
    }

    public function addBarcode(Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'productIds.*' => ['required', 'integer', 'min:1'],
            'barcode' => ['required', 'string']
        ]);

        $barcode = $request->get('barcode');

        foreach ($request->get('productIds') as $productId) {
            $connectWiseService->addBarcode($productId, [$barcode]);
        }

        return response()->json($barcode);
    }

    public function uploadPoAttachment(Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'files.*' => ['required', 'file'],
            'poId' => ['required', 'integer']
        ]);
        $files = $request->file('files');
        $poId = $request->get('poId');

        $result = [];
        foreach ($files as $file) {
            $result[] = $connectWiseService->systemDocumentUpload(
                $file,
                'PurchaseOrder',
                $poId,
                'Packing Slip'
            );
        }

        return response()->json([
            'code' => 'SUCCESS',
            'items' => $result
        ]);
    }

    public function findPoByProduct(Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'productIdentifier' => ['required', 'string']
        ]);

        $productIdentifier = $request->get('productIdentifier');

        return response()->json([
            'items' => $connectWiseService->findItemFromPos($productIdentifier),
            'products' => collect($connectWiseService->getProducts(null, "cancelledFlag=false and catalogItem/identifier='{$productIdentifier}'", 50))
                ->map(function ($product) use ($connectWiseService) {

                    $pickShip = collect($connectWiseService->getProductPickingShippingDetails($product->id));

                    $product->shippedQuantity = $pickShip->map(function ($ps) {
                        return $ps->shippedQuantity;
                    })->sum();

                    $product->pickedQuantity = $pickShip->map(function ($ps) {
                        return $ps->pickedQuantity;
                    })->sum();

                    return $product;
                })
        ]);
    }

    public function uoms(ConnectWiseService $connectWiseService)
    {
        return $connectWiseService->unitOfMeasures();
    }

    public function updateUom($id, Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'uomId' => ['required', 'integer']
        ]);

        $catalogItem = $connectWiseService->getCatalogItem($id);

        $uom = $connectWiseService->unitOfMeasure($request->get('uomId'));

        $uomQty = Str::numbers($uom->name);

        $catalogItem->price = $catalogItem->price * $uomQty;
        $catalogItem->cost = $catalogItem->cost * $uomQty;

        $unitOfMeasureShort = new \stdClass();

        $unitOfMeasureShort->_info = new \stdClass();
        $unitOfMeasureShort->_info->uom_href = Str::replace($catalogItem->unitOfMeasure->id, $uom->id, $catalogItem->unitOfMeasure->_info->uom_href);
        $unitOfMeasureShort->id = $uom->id;
        $unitOfMeasureShort->name = $uom->name;

        $catalogItem->unitOfMeasure = $unitOfMeasureShort;

        $onHand = $connectWiseService->getCatalogItemOnHand($id)->count ?? 0;

        if (!is_int($onHand / $uomQty)) {
            abort(500, 'Check the catalogItem quantity. There is a wrong quantity');
        }

        $products = collect();

        collect($connectWiseService->getProducts(null, "cancelledFlag=false and catalogItem/id={$id}", 1000))
            ->map(function ($product) use ($connectWiseService, $uomQty, &$products, $unitOfMeasureShort) {

                if (!isset($product->project) || $product->id != 11316) {
                    return false;
                }

                $product->original = clone $product;

                $product->original->quantity = $product->original->quantity / $uomQty;

                $product->original->unitOfMeasure = $unitOfMeasureShort;

                $product->original->price = $product->original->price * $uomQty;
                $product->original->cost = $product->original->cost * $uomQty;

                $ships = collect($connectWiseService->getProductPickingShippingDetails($product->id, null, 'lineNumber!=0'));

                $product->shippedQuantity = $ships->map(function ($ps) {
                    return $ps->pickedQuantity ?: $ps->shippedQuantity;
                })->sum();

                if ($product->shippedQuantity == $product->quantity || $product->quantity < $uomQty) {
                    return false;
                }

                if (strval($product->original->quantity) !== strval(intval($product->original->quantity))) {
                    $product->original->quantity = round($product->original->quantity);
                }

                $ships = $ships->map(function ($ship) use ($uomQty, $connectWiseService) {
                    $ship->pickedQuantity = $ship->shippedQuantity = round($ship->shippedQuantity / $uomQty);
                    $ship->quantity = $ship->quantity / $uomQty;

                    if (strval($ship->quantity) !== strval(intval($ship->quantity))) {
                        abort(500, 'Check the shipped quantities. There is a wrong quantity shipped');
                    }

                    return $ship;
                });

                $product->ships = $ships;

                $ships->map(function ($ship) use ($connectWiseService) {
                    $connectWiseService->productPickShipDelete($ship->productItem->id, $ship->id);
                });

                $connectWiseService->updateProduct($product->original);

                $products->push($product);
            });

        $onHand = $connectWiseService->getCatalogItemOnHand($id)->count ?? 0;

        if ($onHand > 0) {
            $connectWiseService->catalogItemAdjust($catalogItem, ($onHand / $uomQty) - $onHand);
        }

        $connectWiseService->updateCatalogItem($catalogItem);

        $products->map(function ($product) use ($connectWiseService) {
            $product->ships->map(function ($ship) use ($connectWiseService) {
                $connectWiseService->productPickShip($ship->productItem->id, $ship->shippedQuantity);
            });
        });

        return $catalogItem;
    }

    public function ship(Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'productId' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1']
        ]);

        $productId = $request->get('productId');
        $quantity = $request->get('quantity');

        $pickShips = $connectWiseService->shipProduct($productId, $quantity);

        $connectWiseService->stockTakeFromCin7ByProjectProductId($productId, $quantity, true);

        return $pickShips;
    }

    public function pick(Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'productId' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1']
        ]);

        $productId = $request->get('productId');
        $quantity = $request->get('quantity');

        return $connectWiseService->pickProduct($productId, $quantity);
    }

    public function unship(Request $request, ConnectWiseService $connectWiseService, BigCommerceService $bigCommerceService)
    {
        $request->validate([
            'productId' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1']
        ]);

        $productId = $request->get('productId');
        $quantity = $request->get('quantity');

        $connectWiseService->unshipProduct($productId, $quantity);

        $product = $connectWiseService->getProduct($productId);

        $connectWiseService->publishProductOnCin7($product, $quantity, true);

        return response()->json($request->all());
    }

    public function shipOptions(ConnectWiseService $connectWiseService)
    {
        $members = $connectWiseService->getSystemMembers(null, 'inactiveFlag=false and hideMemberInDispatchPortalFlag=false and lastName!=null');
        $projects = $connectWiseService->getProjects(null, 'closedFlag=false');
        $teams = $connectWiseService->getSystemDepartments();

        return response()->json([
            'members' => $members,
            'projects' => $projects,
            'teams' => $teams
        ]);
    }

    public function upload($productId, Request $request, ConnectWiseService $connectWiseService, Cin7Service $cin7Service)
    {
        $request->validate([
            'images.*' => 'required|image'
        ]);

        $files = [];

        foreach ($request->file('images') as $image) {
            $files[] = $connectWiseService->systemDocumentUpload(
                $image,
                'ProductSetup',
                $productId,
                'Product image'
            );
        }

        $catalogItem = $connectWiseService->getCatalogItem($productId);

        $product = $cin7Service->productBySku($catalogItem->identifier);

        if ($product) {
            $connectWiseService->syncCatalogItemAttachmentsWithCin7($catalogItem->id, $product->ID, true, isProductFamily: false);
        }

        return response()->json($files);
    }

    public function createUsedItem($id, Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'quantity' => ['required', 'integer', 'min:1']
        ]);

        return $connectWiseService->createUsedCatalogItem($id, $request->get('quantity'));
    }

    public function poReport(Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'poId' => ['required', 'integer']
        ]);

        return $connectWiseService->getPoReport($request->get('poId'));
    }

    public function check($id, Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'checked' => ['required', 'boolean']
        ]);

        $catalogItem = $connectWiseService->getCatalogItem($id);

        $catalogItem->checked = $request->get('checked');

        $connectWiseService->addToReport('ProductChecked', $catalogItem, 'checked');

        return $catalogItem;
    }

    public function sellable($id, Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'quantity' => ['required', 'integer']
        ]);

        $catalogItem = $connectWiseService->getCatalogItem($id);

        $connectWiseService->addToReport('ProductSellable', $catalogItem, $request->get('quantity'));

        return $catalogItem;
    }

    public function takeProductsToAzadMay(Request $request, ConnectWiseService $connectWiseService, Cin7Service $cin7Service)
    {
        $request->validate([
            'supplierId' => ['required', 'string'],
            'products' => ['required', 'array'],
            'products.*.id' => ['required', 'integer'],
            'products.*.quantity' => ['required', 'min:1'],
            'isCatalogItem' => ['nullable', 'boolean']
        ]);

        $supplierId = $request->get('supplierId');

        $isCatalogItem = $request->get('isCatalogItem', false);

        $productsData = collect($request->get('products'));

        $adjustmentDetails = collect();

        $memo = "";

        $purchaseOrderLine = $productsData->filter(fn($productData) => !@$productData['doNotCharge'])->map(function ($productData) use ($cin7Service, $connectWiseService, &$memo, &$adjustmentDetails, $isCatalogItem) {
            $product = $isCatalogItem ? $connectWiseService->getCatalogItem($productData['id']) : $connectWiseService->getProduct($productData['id']);

            $quantity = $productData['quantity'];

            if (!$isCatalogItem) {
                $connectWiseService->unpickProduct($product->id, $quantity);

                $catalogItem = $connectWiseService->getCatalogItem($product->catalogItem->id);

                $adjustmentDetails->push($connectWiseService->convertCatalogItemToAdjustmentDetail($catalogItem, -1 * $quantity));

                $memo .= $catalogItem->identifier . ' - Unpicked from' . (@$product->project ? " project: #{$product->project->id}"
                        : (@$product->ticket ? " service ticket: #{$product->ticket->id}" : " sales order: #{$product->salesOrder->id} &#13;"));
            }

            return $cin7Service->convertProductToPurchaseOrderLine($product, $quantity, $isCatalogItem);
        });

        if (!$isCatalogItem) {
            $connectWiseService->catalogItemAdjustBulk($adjustmentDetails, 'Taking to Azad May Inventory');
        } else {
            $adjustmentLine = $productsData->filter(fn($productData) => @$productData['doNotCharge'])->map(function ($productData) use ($cin7Service, $connectWiseService) {
                $catalogItem = $connectWiseService->getCatalogItem($productData['id']);

                $product = $cin7Service->productBySku($catalogItem->identifier);

                if (!$product) {

                    $connectWiseService = new ConnectWiseService();

                    $product = $cin7Service->createProduct(
                        $catalogItem->identifier,
                        $connectWiseService->generateProductName($catalogItem->description, $catalogItem->identifier),
                        $catalogItem->category->name,
                        $catalogItem->unitOfMeasure->name,
                        $catalogItem->customerDescription,
                        $catalogItem->cost
                    );

                    $connectWiseService->syncCatalogItemAttachmentsWithCin7($catalogItem->id, $product->ID, isProductFamily: false);
                }

                $stock = $cin7Service->productAvailability($product->ID);

                return $cin7Service->convertProductToAdjustmentLine($product->ID, $productData['quantity'] + ($stock->OnHand ?? 0));
            });

            if ($adjustmentLine->count() > 0) {
                $cin7Service->stockAdjustBulk($adjustmentLine);
            }
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

        return $request->all();
    }

    public function moveProductToDifferentProject(Request $request, ConnectWiseService $connectWiseService, Cin7Service $cin7Service)
    {
        $request->validate([
            'productId' => ['required', 'integer'],
            'quantity' => ['required', 'integer'],
            'projectId' => ['nullable', 'required_without_all:companyId,toProductId', 'integer'],
            'companyId' => ['nullable', 'required_without_all:projectId,toProductId', 'integer'],
            'phaseId' => ['nullable', 'integer'],
            'ticketId' => ['nullable', 'integer'],
            'bundleId' => ['nullable', 'integer'],
            'toProductId' => ['nullable', 'required_without_all:companyId,projectId']
        ]);

        $productId = $request->get('productId');
        $quantity = $request->get('quantity');
        $projectId = $request->get('projectId');
        $companyId = $request->get('companyId');
        $phaseId = $request->get('phaseId');
        $ticketId = $request->get('ticketId');
        $bundleId = $request->get('bundleId');
        $toProductId = $request->get('toProductId');

        $product = $connectWiseService->getProduct($productId);

        if (!$toProductId) {
            if ($bundleId) {
                $newProduct = $connectWiseService->getProduct($connectWiseService->createProductComponent($bundleId, $product->catalogItem->id, $quantity, $product->price, $product->cost)->productItem->id);
            } else {
                if ($projectId) {
                    $project = $connectWiseService->getProject($projectId);

                    $companyId = $project->company->id;
                }

                $newProduct = $connectWiseService->cloneProduct(
                    $product,
                    $ticketId,
                    $projectId,
                    $phaseId,
                    $companyId,
                    null,
                    null,
                    $quantity
                );
            }
        }

        $connectWiseService->unpickProduct($product->id, $quantity);

        $connectWiseService->stockTakeFromCin7ByProjectProductId($productId, $quantity, true, $product);

        $connectWiseService->pickProduct($newProduct->id ?? $toProductId, $quantity);

        $connectWiseService->publishProductOnCin7($newProduct ?? $connectWiseService->getProduct($toProductId), $quantity, true);

        return $newProduct->id ?? $toProductId;
    }

    public function cin7Suppliers(Cin7Service $cin7Service)
    {
        return collect($cin7Service->suppliers()->SupplierList)->filter(fn($sup) => Str::contains(Str::lower($sup->Name), 'binyod'));
    }
}
