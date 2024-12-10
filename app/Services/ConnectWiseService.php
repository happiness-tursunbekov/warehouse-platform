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
            $item->poId = $po->id;
            $item->poNumber = $po->poNumber;
            return $item;
        }, json_decode($result->getBody()->getContents()));
    }

    public function getOpenPoItems() : Collection
    {
        if (cache()->has('openPoItems')) {
            return cache()->get('openPoItems');
        }

        $pos = $this->purchaseOrders(null, 'status/id in (1,3) and closedFlag = false', 'id,poNumber');

        $items = new Collection();

        foreach ($pos as $po) {
            $items->push(...$this->getOpenPoItemsByPoId($po->id));
        }
        $items = $items->unique('id');

        cache()->forever('openPoItems', $items);

        return $items;
    }

    private function setOpenPoItems($items) : bool
    {
        return cache()->forever('openPoItems', $items);
    }

    /**
     * @throws GuzzleException
     */
    public function purchaseOrderItemReceive(\stdClass $item, $quantity)
    {
        $poId = $item->poId;
        $poNumber = $item->poNumber;

        if ($item->quantity != $quantity) {
            $item->receivedStatus = 'PartiallyReceiveCloneRest';
        }

        $items = $this->getOpenPoItems();

        $putItem = json_decode(json_encode($item));
        unset($putItem->poId);
        unset($putItem->poNumber);
        $putItem->receivedQuantity = $quantity;
        $putItem->closedFlag = true;
        $result = $this->http->put("procurement/purchaseorders/{$poId}/lineitems/{$item->id}", [
            'query' => [
                'clientId' => $this->clientId
            ],
            'json' => $putItem
        ]);

        $items = $items->where('poId', '!=', $poId);
        $items->push(...$this->getOpenPoItemsByPoId($poId));


        $this->setOpenPoItems($items);

        $result = json_decode($result->getBody()->getContents());

        $result->poId = $poId;
        $result->poNumber = $poNumber;

        return $result;
    }

    public function getOpenPoItemsByPoId($id)
    {
        return $this->purchaseOrderItems($id, null, 'closedFlag = false and canceledFlag = false');
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

    public function cachePos()
    {
        $poItems = new Collection();
        $pos = $this->purchaseOrders(null, null, 'id,poNumber', 'id desc');

        foreach ($pos as $po) {
            $poItems->push(...array_map(function ($poItem) use ($po) {
                $item = new \stdClass();
                $item->id = $poItem->id;
                $item->description = $poItem->description;
                $item->quantity = $poItem->quantity;
                $item->dateReceived = @$poItem->dateReceived;
                $item->receivedStatus = $poItem->receivedStatus;
                $item->canceledFlag = $poItem->canceledFlag;
                $item->closedFlag = $poItem->closedFlag;
                $item->productId = $poItem->product->id;
                $item->productIdentifier = $poItem->product->identifier;
                $item->poId = $po->id;
                $item->poNumber = $po->poNumber;
                return $item;
            }, $this->purchaseOrderItems($po->id)));
        }

        cache()->forever('poItems', $poItems);

        return $poItems;
    }

    public function getProducts($page=null, $conditions=null)
    {
        try {
            $result = $this->http->get('procurement/products', [
                'query' => [
                    'page' => $page,
                    'clientId' => $this->clientId,
                    'conditions' => $conditions,
                    'fields' => 'id,catalogItem,project,phase,quantity,description,company,poApprovedFlag'
                ],
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($result->getBody()->getContents());
    }

    /**
     * @throws GuzzleException
     * @throws \Exception
     */
    public function systemDocumentUpload(UploadedFile $file, $recordType, $recordId, $title, $privateFlag=true, $readonlyFlag=false, $isAvatar=false)
    {
        $ext = $file->extension();
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'avif', 'gif', 'webm'])) {
            $img = Image::read($file->path());
            if ($img->width() > 1920 || $img->height() > 1440) {
                $file = $img->scale(1920, 1440)->encode();
            }
        }

        $filename = md5($file->__toString()) . '.' . $ext;

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

        $values = array_merge($values, $barcodes);

        $barcode->value = json_encode($values);

        $customFields->push($barcode);

        $catalogItem->customFields = $customFields;

        $this->http->put( "procurement/catalog/{$catalogItemId}?clientId=" . $this->clientId, [
            'json' => $catalogItem
        ]);

        return $values;
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
}
