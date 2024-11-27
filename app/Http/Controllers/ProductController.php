<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Services\ConnectWiseService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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

        if ($identifier)
            $conditions .= " and identifier like '*{$identifier}*'";
        if ($description)
            $conditions .= " and description like '*{$description}*'";
        if ($barcode) {
            $ids = ProductBarcode::getByBarcode($barcode)->pluck('product_id')->values()->join(',');
            $conditions .= " and id in ({$ids})";
        }

        $page = (int)$request->get('page', 1);

        $products = $connectWiseService->getCatalogItems($page, $conditions);

        $qty = $connectWiseService->getCatalogItemsQty($conditions)->count ?? 0;

        if ($qty > 0) {
            /** @var Collection $barcodes */
            $barcodes = ProductBarcode::whereIn('product_id', array_column($products, 'id'))->get();

            $products = array_map(function (\stdClass $product) use ($barcodes) {
                $product->barcodes = $barcodes->where('product_id', $product->id)->pluck('barcode')->values();
                return $product;
            }, $products);
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
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Try to search by the description on "Products" section!']);

        try {
            $item = $connectWiseService->purchaseOrderItemReceive($item, $quantity);
        } catch (GuzzleException $e) {
            return response()->json(['code' => 'ERROR', 'message' => json_decode($e->getResponse()->getBody()->getContents())->errors[0]->message]);
        }

        /** @var Product $product */
        $product = Product::find($id);

        $product->receive($quantity);

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

        if ($barcode) {
            $productBarcodes = ProductBarcode::getByBarcode($barcode);
            if ($productBarcodes->count() == 0) {
                return response()->json([
                    'items' => [],
                    'code' => 'BARCODE_NOT_FOUND'
                ]);
            }
            $poItems = $connectWiseService->getOpenPoItems()->whereIn('product.id', $productBarcodes->pluck('product_id')->values());
        }

        if ($identifier) {
            $poItems = $connectWiseService->getOpenPoItems()->filter(function (\stdClass $item) use ($identifier) {
                return false !== stripos($item->product->identifier, $identifier);
            });
        }

        if ($poId) {
            $poItems = collect($connectWiseService->purchaseOrderItems($poId, null, 'canceledFlag=false'));
        }

        $barcodes = ProductBarcode::whereIn('product_id', $poItems->pluck('product.id'))->get();

        return response()->json([
            'items' => $poItems->map(function (\stdClass $product) use ($barcodes) {
                $product->barcodes = $barcodes->where('product_id', $product->product->id)->pluck('barcode')->values();
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

        $pos = $connectWiseService->purchaseOrders(null, "poNumber like '*{$poNumber}*'");

        return response()->json([
            'items' => $pos
        ]);
    }

    public function addBarcode(Request $request)
    {
        $request->validate([
            'productIds.*' => ['required', 'integer', 'min:1'],
            'barcode' => ['required', 'string']
        ]);

        $barcode = ProductBarcode::addBarcode($request->get('productIds'), $request->get('barcode'));

        return response()->json($barcode);
    }

    public function uploadPoAttachment(Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'file' => ['required', 'string'],
            'poId' => ['required', 'integer']
        ]);
        $file = $request->get('file');
        $poId = $request->get('poId');

        try {
            $result = $connectWiseService->systemDocumentUpload(
                $file,
                'PurchaseOrder',
                $poId,
                'Packing Slip'
            );
        } catch (GuzzleException $e) {
            return response()->json(['code' => 'ERROR', 'message' => json_decode($e->getResponse()->getBody()->getContents())]);
        }

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

        return response()->json([
            'items' => $connectWiseService->findItemFromPos($request->get('productIdentifier'))
        ]);
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

    public function ship(Request $request)
    {

    }
}
