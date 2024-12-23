<?php

namespace App\Http\Controllers;


use App\Services\ConnectWiseService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        ]);

        $identifier = $request->get('identifier');
        $description = $request->get('description');
        $barcode = $request->get('barcode');

        $conditions = "inactiveFlag=false";

        $customFieldConditions=null;

        if ($identifier)
            $conditions .= " and identifier contains '{$identifier}'";
        if ($description)
            $conditions .= " and description contains '{$description}'";
        if ($barcode) {
            $customFieldConditions = "caption='Barcodes' and value contains '{$barcode}'";
        }

        $page = (int)$request->get('page', 1);

        $products = collect($connectWiseService->getCatalogItems($page, $conditions, $customFieldConditions));

        $qty = $connectWiseService->getCatalogItemsQty($conditions)->count ?? 0;

        if ($qty > 0) {
            $onHands = collect($connectWiseService->getProductCatalogOnHand(null, "catalogItem/id in ({$products->pluck('id')->join(',')})", null, $products->count()));
            $products->map(function (\stdClass $product) use ($connectWiseService, $onHands) {
                $product->barcodes = $connectWiseService->extractBarcodesFromCatalogItem($product);
                $product->files = [];
                $product->onHand = $onHands->where('catalogItem.id', $product->id)->first()->onHand ?? 0;
                return $product;
            });
        }

        return response()->json([
            'products' => $products,
            'meta' => [
                'total' => $qty,
                'currentPage' => $page,
                'perPage' => 25,
                'totalPages' => ceil($qty / 25),
            ],
        ]);
    }

    public function onHand($id, ConnectWiseService $connectWiseService)
    {
        return response()->json($connectWiseService->getCatalogItemOnHand($id)->count);
    }

    public function images($id, ConnectWiseService $connectWiseService)
    {
        return response()->json($connectWiseService->getAttachments('ProductSetup', $id));
    }

    public function image($attachmentId, ConnectWiseService $connectWiseService)
    {
        $temp = tempnam(sys_get_temp_dir(), 'TMP_');
        file_put_contents($temp, $connectWiseService->downloadAttachment($attachmentId));
        return response()->file($temp)->deleteFileAfterSend();
    }

    public function receive(Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'id' => ['required', 'integer']
        ]);

        $id = $request->get('id');

        $quantity = $request->get('quantity');

        $poItems = $connectWiseService->getOpenPoItems();

        $item = $poItems->where('id', $id)->first();

        if (!$item)
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Try to search by the description on "Products" section!'], 422);

        try {
            $item = $connectWiseService->purchaseOrderItemReceive($item, $quantity);
        } catch (GuzzleException $e) {
            return response()->json(['code' => 'ERROR', 'message' => json_decode($e->getResponse()->getBody()->getContents())->errors[0]->message], 500);
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
            $catalogItems = $connectWiseService->getCatalogItemsByBarcode($barcode, null, null, 1000);
            if (count($catalogItems) == 0) {
                return response()->json([
                    'items' => [],
                    'code' => 'BARCODE_NOT_FOUND'
                ]);
            }
            $catalogItems = collect($catalogItems);
            $poItems = $connectWiseService->getOpenPoItems()->whereIn('product.id', $catalogItems->pluck('id')->values());
        }

        if ($identifier) {
            $poItems = $connectWiseService->getOpenPoItems()->filter(function (\stdClass $item) use ($identifier) {
                return false !== stripos($item->product->identifier, $identifier);
            });
        }

        if ($poId) {
            $poItems = collect($connectWiseService->purchaseOrderItems($poId, null, 'canceledFlag=false'));
        }

        if (!$catalogItems)
            $catalogItems = collect($connectWiseService->getCatalogItems(null, "id in ({$poItems->pluck('product.id')->values()->join(',')})"));

        return response()->json([
            'items' => $poItems->map(function (\stdClass $product) use ($catalogItems, $connectWiseService) {
                $product->barcodes = $connectWiseService->extractBarcodesFromCatalogItem($catalogItems->where('id', $product->product->id)->first());
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
            'file' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf'],
            'poId' => ['required', 'integer']
        ]);
        $file = $request->file('file');
        $poId = $request->get('poId');

        $result = $connectWiseService->systemDocumentUpload(
            $file,
            'PurchaseOrder',
            $poId,
            'Packing Slip'
        );

        return response()->json([
            'code' => 'SUCCESS',
            'item' => $result
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
            'products' => collect($connectWiseService->getProducts(null, "cancelledFlag=false and catalogItem/identifier='{$productIdentifier}'"))
                ->map(function ($product) use ($connectWiseService) {

                    $pickShip = collect($connectWiseService->getProductPickingShippingDetails($product->id));

                    $product->shippedQuantity = $pickShip->map(function ($ps) {
                        return $ps->pickedQuantity ?: $ps->shippedQuantity;
                    })->sum();

                    return $product;
                })
        ]);
    }

    public function ship(Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'productId' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1']
        ]);

        $productId = $request->get('productId');
        $quantity = $request->get('quantity');

        return $connectWiseService->productPickShip($productId, $quantity);
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

    public function upload($productId, Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'images.*' => 'required|image'
        ]);

        foreach ($request->file('images') as $image) {
            $files[] = $connectWiseService->systemDocumentUpload(
                $image,
                'ProductSetup',
                $productId,
                'Product image'
            );
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

    public function adjust($id, Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'quantity' => ['required', 'integer']
        ]);

        $catalogItem = $connectWiseService->getCatalogItem($id);

        $connectWiseService->catalogItemAdjust($catalogItem, $request->get('quantity'));

        return $catalogItem;
    }
}
