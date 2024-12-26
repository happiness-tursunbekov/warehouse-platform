<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Psr\Http\Message\RequestInterface;
use Psr\SimpleCache\InvalidArgumentException;

class ConnectWiseService
{
    private Client $http;
    private string $clientId;
    public function __construct()
    {
        $this->clientId = config('cw.client_id');
        $this->http = new Client([
            'auth' => [config('cw.company_id') . '+' . config('cw.public_key'), config('cw.private_key')],
            'base_uri' => config('cw.base_uri'),
        ]);
    }

    public function addToReport($type, \stdClass $item, $action, $additional=null)
    {
        $user = \request()->user();

        if ($user && $user->reportMode) {
            $reportType = "{$user->id}-reports";
            $reports = cache()->has($reportType) ? cache()->get($reportType) : new \stdClass();

            if ($type == 'ProductShipment') {
                $item->productInfo = $this->getProduct($item->productItem->id);
            }

            $newItem = new \stdClass();

            $newItem->item = $item;
            $newItem->action = $action;
            $newItem->additional = $additional;

            if (isset($reports->{$type})) {
                $reports->{$type}->push($newItem);
            } else {
                $reports->{$type} = new Collection([$newItem]);
            }

            cache()->forever($reportType, $reports);
        }
    }

    public function clearUserReports()
    {
        $user = \request()->user();

        $reportType = "{$user->id}-reports";

        try {
            cache()->forget($reportType);
        } catch (InvalidArgumentException $e) {}
    }

    public function getUserReport($type)
    {
        $user = \request()->user();

        $reportType = "{$user->id}-reports";

        $reports = cache()->has($reportType) ? cache()->get($reportType) : null;

        if (!$reports || !isset($reports->{$type})) {
            return new Collection();
        }

        return $reports->{$type};
    }

    public function getAllUserReports()
    {
        $user = \request()->user();

        $reportType = "{$user->id}-reports";

        $reports = cache()->has($reportType) ? cache()->get($reportType) : null;

        if (!$reports) {
            return new \stdClass();
        }

        return $reports;
    }

    public function getCatalogItems($page=null, $conditions=null, $customFieldConditions=null, $fields=null, $pageSize=25)
    {
        try {
            $result = $this->http->get('procurement/catalog', [
                'query' => [
                    'page' => $page,
                    'clientId' => $this->clientId,
                    'conditions' => $conditions,
                    'fields' => $fields,
                    'pageSize' => $pageSize,
                    'customFieldConditions' => $customFieldConditions
                ],
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($result->getBody()->getContents());
    }

    public function getCatalogItemsByBarcode(string $barcode, $fields=null, $page=null, $pageSize=25)
    {
        try {
            $result = $this->http->get('procurement/catalog', [
                'query' => [
                    'page' => $page,
                    'clientId' => $this->clientId,
                    'fields' => $fields,
                    'pageSize' => $pageSize,
                    'customFieldConditions' => "caption='Barcodes' and value contains '{$barcode}'"
                ],
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($result->getBody()->getContents());
    }

    public function getCatalogItemsQty($conditions=null)
    {
        try {
            $result = $this->http->get('procurement/catalog/count', [
                'query' => [
                    'clientId' => $this->clientId,
                    'conditions' => $conditions,
                ]
            ]);
        } catch (GuzzleException $e) {
            return new \stdClass();
        }
        return json_decode($result->getBody()->getContents());
    }

    public function getCatalogItemOnHand($id)
    {
        try {
            $result = $this->http->get("procurement/catalog/{$id}/quantityOnHand", [
                'query' => [
                    'clientId' => $this->clientId,
                    'warehouseBinId' => 1
                ]
            ]);
        } catch (GuzzleException $e) {
            return new \stdClass();
        }
        return json_decode($result->getBody()->getContents());
    }

    public function purchaseOrders($page=null, $conditions=null, $fields=null, $orderBy=null)
    {
        try {
            $result = $this->http->get('procurement/purchaseorders', [
                'query' => [
                    'page' => $page,
                    'clientId' => $this->clientId,
                    'conditions' => $conditions,
                    'fields' => $fields,
                    'pageSize' => 1000,
                    'orderBy' => $orderBy
                ],
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($result->getBody()->getContents());
    }

    public function purchaseOrder(int $id)
    {
        try {
            $result = $this->http->get("procurement/purchaseorders/{$id}?clientId={$this->clientId}");
        } catch (GuzzleException $e) {
            return new \stdClass();
        }
        return json_decode($result->getBody()->getContents());
    }

    public function purchaseOrderItems($id, $page=null, $conditions=null, $fields=null)
    {
        $po = $this->purchaseOrders(null, 'id=' . $id)[0];
        try {
            $result = $this->http->get("procurement/purchaseorders/{$id}/lineitems", [
                'query' => [
                    'page' => $page,
                    'clientId' => $this->clientId,
                    'conditions' => $conditions,
                    'pageSize' => 1000,
                    'fields' => $fields
                ],
            ]);
        } catch (GuzzleException $e) {
            return [];
        }

        return array_map(function (\stdClass $item) use ($po) {
            return $this->preparePoItem($po->id, $item, $po);
        }, json_decode($result->getBody()->getContents()));
    }

    public function purchaseOrderItem($poId, $itemId)
    {
        try {
            $result = $this->http->get("procurement/purchaseorders/{$poId}/lineitems/{$itemId}?clientId={$this->clientId}");
        } catch (GuzzleException $e) {
            return new \stdClass();
        }

        return json_decode($result->getBody()->getContents());
    }

    public function getOpenPoItems() : Collection
    {
        if (!cache()->has('poItems')) {
            $poItems = $this->cachePos();
        } else {
            $poItems = cache()->get('poItems');
        }

        return $poItems->whereIn('poStatus.id', [1,3])->where('poClosedFlag', false);
    }

    private function preparePoItem(int $poId, $poItem, \stdClass $po=null)
    {
        if (!$po) {
            $po = $this->purchaseOrder($poId);
        }

        $item = new \stdClass();
        $item->id = $poItem->id;
        $item->description = $poItem->description;
        $item->quantity = $poItem->quantity;
        $item->dateReceived = @$poItem->dateReceived;
        $item->receivedStatus = $poItem->receivedStatus;
        $item->receivedQuantity = $poItem->receivedQuantity;
        $item->canceledFlag = $poItem->canceledFlag;
        $item->closedFlag = $poItem->closedFlag;
        $item->productId = $poItem->product->id;
        $item->productIdentifier = $poItem->product->identifier;
        $item->poId = $po->id;
        $item->poNumber = $po->poNumber;
        $item->poStatus = $po->status;
        $item->poClosedFlag = $po->closedFlag;

        return $item;
    }

    private function updatePoItems(int $poId, \stdClass $po=null) : Collection
    {
        if (!$po) {
            $po = $this->purchaseOrder($poId);
        }

        if (cache()->has('poItems')) {
            $poItems = cache()->get('poItems');
        } else {
            $poItems = new Collection();
        }

        $poItems = $poItems->where('poId', '!=', $poId);

        $poItems->push(...array_map(function ($poItem) use ($po) {
            return $this->preparePoItem($po->id, $poItem, $po);
        }, $this->purchaseOrderItems($po->id)));

        cache()->forever('poItems', $poItems);

        return $poItems;
    }

    /**
     * @throws GuzzleException
     */
    public function purchaseOrderItemReceive(int $itemId, $quantity)
    {
        $item = $this->getOpenPoItems()->where('id', $itemId)->first();

        $poId = $item->poId;

        $putItem = $this->purchaseOrderItem($poId, $itemId);

        if ($item->quantity != $quantity) {
            $putItem->receivedStatus = 'PartiallyReceiveCloneRest';
        }

        $putItem->receivedQuantity = $quantity;
        $putItem->closedFlag = true;
        $result = $this->http->put("procurement/purchaseorders/{$poId}/lineitems/{$item->id}", [
            'query' => [
                'clientId' => $this->clientId
            ],
            'json' => $putItem
        ]);

        $this->updatePoItems($poId);

        $result = json_decode($result->getBody()->getContents());

        return $this->preparePoItem($poId, $result);
    }

    public function findItemFromPos($itemIdentifier)
    {
        if (cache()->has('poItems')) {
            $poItems =  cache()->get('poItems');
        } else {
            $poItems = $this->cachePos();
        }

        return $poItems->where('productIdentifier', $itemIdentifier)->values();
    }

    public function findItemFromPosById($productId)
    {
        if (cache()->has('poItems')) {
            $poItems =  cache()->get('poItems');
        } else {
            $poItems = $this->cachePos();
        }

        return $poItems->where('id', $productId)->values();
    }

    public function cachePos()
    {
        $poItems = new Collection();
        $pos = $this->purchaseOrders(null, null, 'id,poNumber,status,closedFlag,canceledFlag', 'id desc');

        foreach ($pos as $po) {
            $poItems = $this->updatePoItems($po->id, $po);
        }

        return $poItems;
    }

    public function getProducts($page=null, $conditions=null, $pageSize=null)
    {
        try {
            $result = $this->http->get('procurement/products', [
                'query' => [
                    'page' => $page,
                    'clientId' => $this->clientId,
                    'conditions' => $conditions,
                    'fields' => 'id,catalogItem,project,phase,quantity,description,company,poApprovedFlag',
                    'pageSize' => $pageSize
                ],
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($result->getBody()->getContents());
    }

    public function getProduct($id)
    {
        try {
            $result = $this->http->get("procurement/products/{$id}?clientId={$this->clientId}");
        } catch (GuzzleException $e) {
            return new \stdClass();
        }
        return json_decode($result->getBody()->getContents());
    }

    public function getProductPickingShippingDetails($id, $page=null, $conditions=null)
    {
        try {
            $result = $this->http->get("procurement/products/{$id}/pickingShippingDetails", [
                'query' => [
                    'page' => $page,
                    'clientId' => $this->clientId,
                    'conditions' => $conditions
                ],
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($result->getBody()->getContents());
    }

    public function productPickShip($id, $quantity)
    {
        $pickShip = $this->getProductPickingShippingDetails($id, null, 'lineNumber=0')[0];
        $pickShip->pickedQuantity = $pickShip->shippedQuantity = $quantity;

        try {
            $request = $this->http->put("procurement/products/{$id}/pickingShippingDetails/{$pickShip->id}?clientId=" . $this->clientId, [
                'json' => $pickShip,
            ]);
        } catch (GuzzleException $e) {
            return response()->json(['code' => 'ERROR', 'message' => $e->getResponse()->getBody()->getContents()], 500);
        }

        $result = json_decode($request->getBody()->getContents());

        $this->addToReport('ProductShipment', $result, $quantity < 0 ? 'unshipped/returned' : 'shipped');

        return $result;
    }

    /**
     * @throws GuzzleException
     * @throws \Exception
     */
    public function systemDocumentUpload(UploadedFile $file, $recordType, $recordId, $title, $privateFlag=true, $readonlyFlag=false, $isAvatar=false)
    {
        $ext = $file->extension();
        if (Str::contains($file->getMimeType(), 'image')) {
            $img = Image::read($file->path());
            if ($img->width() > 1920 || $img->height() > 1440) {
                $file = $img->scale(1920, 1440)->encode();
            }
        }

        $filename = md5($file->__toString()) . '.' . $ext;

        $file = method_exists($file, 'getContent') ? $file->getContent() : $file;

        $request = $this->http->post( 'system/documents?clientId=' . $this->clientId, [
            'multipart' => [
                [
                    'name'     => 'file',
                    'contents' => $file,
                    'filename' => $filename,
                ],
                [
                    'name' => 'recordType',
                    'contents' => $recordType
                ],
                [
                    'name' => 'recordId',
                    'contents' => $recordId
                ],
                [
                    'name' => 'title',
                    'contents' => $title
                ],
                [
                    'name' => 'privateFlag',
                    'contents' => $privateFlag ? 1 : 0
                ],
                [
                    'name' => 'readonlyFlag',
                    'contents' => $readonlyFlag ? 1 : 0
                ],
                [
                    'name' => 'isAvatar',
                    'contents' => $isAvatar ? 1 : 0
                ]
            ]
        ]);

       return json_decode($request->getBody()->getContents());
    }

    public function getSystemMembers($page=null, $conditions=null)
    {
        try {
            $result = $this->http->get('system/members', [
                'query' => [
                    'page' => $page,
                    'clientId' => $this->clientId,
                    'conditions' => $conditions,
                ],
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($result->getBody()->getContents());
    }

    public function getProjects($page=null, $conditions=null)
    {
        try {
            $result = $this->http->get('project/projects', [
                'query' => [
                    'page' => $page,
                    'clientId' => $this->clientId,
                    'conditions' => $conditions,
                    'pageSize' => 1000
                ],
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($result->getBody()->getContents());
    }

    public function getProject($id, $fields=null)
    {
        try {
            $result = $this->http->get('project/projects/' . $id, [
                'query' => [
                    'clientId' => $this->clientId,
                    'fields' => $fields
                ],
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($result->getBody()->getContents());
    }

    public function getSystemDepartments($page=null, $conditions=null)
    {
        try {
            $result = $this->http->get('system/departments', [
                'query' => [
                    'page' => $page,
                    'clientId' => $this->clientId,
                    'conditions' => $conditions,
                ],
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($result->getBody()->getContents());
    }

    public function getSystemDepartment($id, $fields=null)
    {
        try {
            $result = $this->http->get('system/departments/' . $id, [
                'query' => [
                    'clientId' => $this->clientId,
                    'fields' => $fields
                ],
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($result->getBody()->getContents());
    }

    public function getCategories($page=null, $conditions=null, $orderBy=null)
    {
        try {
            $result = $this->http->get('procurement/categories', [
                'query' => [
                    'page' => $page,
                    'clientId' => $this->clientId,
                    'conditions' => $conditions,
                    'orderBy' => $orderBy
                ],
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($result->getBody()->getContents());
    }

    public function getCategory($id)
    {
        try {
            $result = $this->http->get("procurement/categories/{$id}?clientId={$this->clientId}");
        } catch (GuzzleException $e) {
            return null;
        }
        return json_decode($result->getBody()->getContents());
    }

    public function updateCategory(\stdClass $category)
    {

        $result = $this->http->put("procurement/categories/{$category->id}?clientId={$this->clientId}", [
            'json' => $category,
        ]);
        return json_decode($result->getBody()->getContents());
    }

    public function getSubcategories($page=null, $conditions=null, $orderBy=null)
    {
        try {
            $result = $this->http->get('procurement/subcategories', [
                'query' => [
                    'page' => $page,
                    'clientId' => $this->clientId,
                    'conditions' => $conditions,
                    'orderBy' => $orderBy
                ],
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($result->getBody()->getContents());
    }

    public function getSubcategory($id)
    {
        try {
            $result = $this->http->get("procurement/subcategories/{$id}?clientId={$this->clientId}");
        } catch (GuzzleException $e) {
            return null;
        }
        return json_decode($result->getBody()->getContents());
    }

    public function updateSubcategory(\stdClass $category)
    {
        try {
            $result = $this->http->put("procurement/subcategories/{$category->id}?clientId={$this->clientId}", [
                'json' => $category,
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($result->getBody()->getContents());
    }

    public function getCatalogItem($id)
    {
        try {
            $result = $this->http->get('procurement/catalog/' . $id, [
                'query' => [
                    'clientId' => $this->clientId
                ],
            ]);
        } catch (GuzzleException $e) {
            return new \stdClass();
        }
        return json_decode($result->getBody()->getContents());
    }

    public function addBarcode(int $catalogItemId, array $barcodes)
    {
        $catalogItem = $this->getCatalogItem($catalogItemId);

        $customFields = collect($catalogItem->customFields);

        $barcode = $customFields->where('caption', 'Barcodes')->first();

        $customFields = $customFields->where('caption', '!=', 'Barcodes');

        if (isset($barcode->value))
            $values = json_decode($barcode->value);
        else
            $values = [];

        $values = array_unique(array_merge($values, $barcodes));

        $barcode->value = json_encode($values);

        $customFields->push($barcode);

        $catalogItem->customFields = $customFields->sortBy('id')->values()->toArray();


        $this->http->put( "procurement/catalog/{$catalogItemId}?clientId=" . $this->clientId, [
            'json' => $catalogItem
        ]);

        if (!Str::contains($catalogItem->identifier, 'used)')) {
            $usedItems = $this->getCatalogItems(null, "identifier like '{$catalogItem->identifier}(*' and identifier contains 'used'", null, null, 100);

            foreach ($usedItems as $usedItem) {
                $this->addBarcode($usedItem->id, $values);
            }
        }

        return $values;
    }

    public function setCatalogItemBigCommerceProductId(\stdClass $catalogItem, $bcProductId)
    {
        try {
            $customFields = collect($catalogItem->customFields);

            $bcProduct = $customFields->where('caption', 'BigCommerce Product ID')->first();

            $customFields = $customFields->where('caption', '!=', 'BigCommerce Product ID');

            $bcProduct->value = $bcProductId;

            $customFields->push($bcProduct);

            $catalogItem->customFields = $customFields->sortBy('id')->values()->toArray();

            $this->updateCatalogItem($catalogItem);
        } catch (GuzzleException $e) {
            return ($e->getResponse()->getBody()->getContents());
        }

        return $catalogItem;
    }

    public function getBigCommerceProductId(\stdClass $catalogItem)
    {
        $bcProduct = collect($catalogItem->customFields)->where('caption', 'BigCommerce Product ID')->first();

        return $bcProduct->value ?? null;

    }

    public function extractBarcodesFromCatalogItem(\stdClass $catalogItem) : array
    {
        $customFields = collect($catalogItem->customFields);

        $barcode = $customFields->where('caption', 'Barcodes')->first();

        if (!isset($barcode->value))
            return [];

        return json_decode($barcode->value);
    }

    public function getAttachments($recordType, $recordId, $page=null, $conditions=null, $fields=null, $pageSize=25)
    {
        try {
            $result = $this->http->get('system/documents', [
                'query' => [
                    'page' => $page,
                    'clientId' => $this->clientId,
                    'conditions' => $conditions,
                    'fields' => $fields,
                    'recordType' => $recordType,
                    'recordId' => $recordId,
                    'pageSize' => $pageSize
                ],
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($result->getBody()->getContents());
    }

    public function downloadAttachment($id)
    {
        try {
            $result = $this->http->get("system/documents/{$id}/download", [
                'query' => [
                    'clientId' => $this->clientId
                ],
            ]);
        } catch (GuzzleException $e) {
            return '';
        }
        return $result->getBody()->getContents();
    }

    public function systemDocumentUploadProduct($file, $recordId, $filename, $privateFlag=true, $readonlyFlag=false, $isAvatar=false)
    {
        $request = $this->http->post( 'system/documents?clientId=' . $this->clientId, [
            'multipart' => [
                [
                    'name'     => 'file',
                    'contents' => $file,
                    'filename' => $filename,
                ],
                [
                    'name' => 'recordType',
                    'contents' => 'ProductSetup'
                ],
                [
                    'name' => 'recordId',
                    'contents' => $recordId
                ],
                [
                    'name' => 'title',
                    'contents' => 'Product Image'
                ],
                [
                    'name' => 'privateFlag',
                    'contents' => $privateFlag ? 1 : 0
                ],
                [
                    'name' => 'readonlyFlag',
                    'contents' => $readonlyFlag ? 1 : 0
                ],
                [
                    'name' => 'isAvatar',
                    'contents' => $isAvatar ? 1 : 0
                ]
            ]
        ]);

        return json_decode($request->getBody()->getContents());
    }

    public function setProjectBigcommerceGroupId(\stdClass $project, int $groupId)
    {
        $customFields = collect($project->customFields);

        $group = $customFields->where('caption', 'BigCommerce Group ID')->first();

        $customFields = $customFields->where('caption', '!=', 'BigCommerce Group ID');

        $group->value = $groupId;

        $customFields->push($group);

        $project->customFields = $customFields->toArray();

        $this->http->put( "project/projects/{$project->id}?clientId=" . $this->clientId, [
            'json' => $project
        ]);

        return $project;
    }

    public function catalogItemAdjust(\stdClass $catalogItem, $qty)
    {
        $adjustmentID = date('m/d/Y') . ' - ' . time();

        $adjustment = json_decode("
        {
            \"id\": 0,
            \"identifier\": \"{$adjustmentID}\",
            \"type\": {
                \"id\": 1,
                \"identifier\": \"Initial Count\",
                \"_info\": {
                    \"type_href\": \"https://api-na.myconnectwise.net/v4_6_release/apis/3.0//procurement/adjustments/types/1\"
                }
            },
            \"reason\": \"Quantity update\",
            \"closedFlag\": true,
            \"adjustmentDetails\": [
                {
                    \"id\": 0,
                    \"catalogItem\": {
                        \"id\": {$catalogItem->id},
                        \"identifier\": \"{$catalogItem->identifier}\",
                        \"_info\": {
                            \"catalog_href\": \"https://api-na.myconnectwise.net/v4_6_release/apis/3.0//procurement/catalog/{$catalogItem->id}\"
                        }
                    },
                    \"description\": \"Updating quantity\",
                    \"unitCost\": {$catalogItem->cost},
                    \"warehouse\": {
                        \"id\": 1,
                        \"name\": \"Warehouse\",
                        \"lockedFlag\": false,
                        \"_info\": {
                            \"warehouse_href\": \"https://api-na.myconnectwise.net/v4_6_release/apis/3.0//procurement/warehouses/1\"
                        }
                    },
                    \"warehouseBin\": {
                        \"id\": 1,
                        \"name\": \"Default Bin\",
                        \"_info\": {
                            \"warehouseBin_href\": \"https://api-na.myconnectwise.net/v4_6_release/apis/3.0//procurement/warehouseBins/1\"
                        }
                    },
                    \"quantityAdjusted\": {$qty},
                    \"adjustment\": {
                        \"id\": 0,
                        \"name\": \"{$adjustmentID}\",
                        \"_info\": {
                            \"adjustment_href\": \"https://api-na.myconnectwise.net/v4_6_release/apis/3.0//procurement/adjustments/0\"
                        }
                    },
                    \"_info\": {
                        \"lastUpdated\": \"2024-12-16T20:41:09Z\",
                        \"updatedBy\": \"Integrator\"
                    }
                }
            ],
            \"_info\": {
                \"lastUpdated\": \"2024-12-16T20:40:19Z\",
                \"updatedBy\": \"Integrator\"
            }
        }
        ");
        try {
            $request = $this->http->post('procurement/adjustments?clientId=' . $this->clientId, [
                'json' => $adjustment
            ]);
        } catch (GuzzleException $e) {
            abort(500, $e->getResponse()->getBody()->getContents());
        }

        return json_decode($request->getBody()->getContents());
    }

    public function createUsedCatalogItem(int $catalogItemId, int $qty)
    {
        $catalogItem = $this->getCatalogItem($catalogItemId);

        $catalogItem->identifier = $catalogItem->identifier . Str::lower("({$qty}{$catalogItem->unitOfMeasure->name}-used)");

        $catalogItem->id = 0;

        $catalogItem->price = round($catalogItem->price/3, 2);
        $catalogItem->cost = round($catalogItem->cost/3, 2);

        $request = $this->http->post('procurement/catalog?clientId=' . $this->clientId, [
            'json' => $catalogItem
        ]);

        $newCatalogItem = json_decode($request->getBody()->getContents());

        $this->catalogItemAdjust($newCatalogItem, $qty);

        $this->addToReport('UsedCatalogItem', $newCatalogItem, 'added');

        return $newCatalogItem;
    }

    public function updateCatalogItem(\stdClass $item)
    {
        $request = $this->http->put( "procurement/catalog/{$item->id}?clientId=" . $this->clientId, [
            'json' => $item
        ]);

        return json_decode($request->getBody()->getContents());
    }

    public function systemDocumentUploadTemp($file, $recordType, $recordId, $title, $privateFlag=true, $readonlyFlag=false, $isAvatar=false)
    {
//        $ext = $file->extension();
//        if (in_array($ext, ['jpg', 'jpeg', 'png', 'avif', 'gif', 'webm'])) {
//            $img = Image::read($file->path());
//            if ($img->width() > 1920 || $img->height() > 1440) {
//                $file = $img->scale(1920, 1440)->encode();
//            }
//        }

//        $filename = md5($title) . '.jpg';

        $request = $this->http->post( 'system/documents?clientId=' . $this->clientId, [
            'multipart' => [
                [
                    'name'     => 'file',
                    'contents' => $file,
                    'filename' => $title,
                ],
                [
                    'name' => 'recordType',
                    'contents' => $recordType
                ],
                [
                    'name' => 'recordId',
                    'contents' => $recordId
                ],
                [
                    'name' => 'title',
                    'contents' => $title
                ],
                [
                    'name' => 'privateFlag',
                    'contents' => $privateFlag ? 1 : 0
                ],
                [
                    'name' => 'readonlyFlag',
                    'contents' => $readonlyFlag ? 1 : 0
                ],
                [
                    'name' => 'isAvatar',
                    'contents' => $isAvatar ? 1 : 0
                ]
            ]
        ]);

        return json_decode($request->getBody()->getContents());
    }

    public function getProductCatalogOnHand($page=null, $conditions=null, $fields=null, $pageSize=25)
    {
        try {
            $result = $this->http->get('procurement/warehouseBins/1/inventoryOnHand', [
                'query' => [
                    'page' => $page,
                    'clientId' => $this->clientId,
                    'conditions' => $conditions,
                    'fields' => $fields,
                    'pageSize' => $pageSize
                ],
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($result->getBody()->getContents());
    }

}
