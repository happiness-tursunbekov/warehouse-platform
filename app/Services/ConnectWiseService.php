<?php

namespace App\Services;

use App\Models\Setting;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Psr\SimpleCache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ConnectWiseService
{
    const ACTION_ADDED = 'added';
    const ACTION_UPDATED = 'updated';
    const ACTION_DELETED = 'deleted';

    const DEFAULT_WAREHOUSE = 1;
    const AZAD_MAY_WAREHOUSE = 2;

    const DEFAULT_WAREHOUSE_DEFAULT_BIN = 1;
    const AZAD_MAY_WAREHOUSE_DEFAULT_BIN = 31;

    const RECORD_TYPE_PRODUCT_SETUP = 'ProductSetup';

    const AZAD_MAY_ID = 19945;

    const LOCATION_HOUSTON = 11;

    private Client $http;
    private string $clientId;
    private string $systemIO;

    private Cin7Service $cin7Service;
    private BigCommerceService $bigCommerceService;

    public function __construct()
    {
        $this->clientId = config('cw.client_id');

        // Create a handler stack
        $stack = HandlerStack::create();

        // Define the retry middleware
        $retryMiddleware = Middleware::retry(
            function ($retries, $request, $response, $exception) {
                // Limit the number of retries to 5
                if ($retries >= 3) {
                    return false;
                }

                // Retry on server errors (5xx HTTP status codes)
                if ($response && $response->getStatusCode() >= 500) {
                    return true;
                }

                // Retry on connection exceptions
                if ($exception instanceof RequestException && $exception->getCode() === 0) {
                    return true;
                }

                return false;
            },
            function ($retries) {
                // Define a delay function (e.g., exponential backoff)
                return (int) pow(2, $retries) * 1000; // Delay in milliseconds
            }
        );

        // Add the retry middleware to the handler stack
        $stack->push($retryMiddleware);

        $this->http = new Client([
            'auth' => [config('cw.company_id') . '+' . config('cw.public_key'), config('cw.private_key')],
            'base_uri' => config('cw.base_uri'),
            'handler' => $stack
        ]);

        $this->systemIO = config('cw.internal_api_uri');

        $this->cin7Service = new Cin7Service();
        $this->bigCommerceService = new BigCommerceService();
    }

    private function internalApiRequest(string $endpointUrl, array $payload, string $payloadClassName, string $payloadProject)
    {
        $actionMessage = [
            "payload" => json_encode($payload),
            "payloadClassName" => $payloadClassName,
            "project" => $payloadProject
        ];

        $query = [
            "actionMessage" => json_encode($actionMessage),
            "clientTimezoneOffset" => "-360",
            "clientTimezoneName" => "Central+Standard+Time",
            "clientId" => $this->clientId
        ];

        $response = $this->http->post(
            "{$this->systemIO}{$endpointUrl}?" . http_build_query($query));

        return json_decode($response->getBody()->getContents());
    }

    private function payloadHandler(array $payload, string $payloadClassName, string $payloadProject)
    {
        $actionMessage = [
            "payload" => json_encode($payload),
            "payloadClassName" => $payloadClassName,
            "project" => $payloadProject
        ];

        $query = [
            "actionMessage" => json_encode($actionMessage),
            "clientTimezoneOffset" => "-360",
            "clientTimezoneName" => "Central+Standard+Time",
            "clientId" => $this->clientId
        ];

        return http_build_query($query);
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

    public function getUserReportByUserId($userId, $type)
    {
        $reportType = "{$userId}-reports";

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

    public function getAllUserReportsByUserId(int $userId)
    {
        $reportType = "{$userId}-reports";

        $reports = cache()->has($reportType) ? cache()->get($reportType) : null;

        if (!$reports) {
            return new \stdClass();
        }

        return $reports;
    }

    public function getCatalogItems($page=null, $conditions=null, $customFieldConditions=null, $fields=null, $pageSize=25)
    {
        try {
            $response = $this->http->get('procurement/catalog', [
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
        return json_decode($response->getBody()->getContents());
    }

    public function getCompany($id)
    {
        $response = $this->http->get("company/companies/{$id}", [
            'query' => [
                'clientId' => $this->clientId,
            ],
        ]);

        return json_decode($response->getBody()->getContents());
    }

    public function getCatalogItemByIdentifier($identifier)
    {
        $response = $this->http->get('procurement/catalog', [
            'query' => [
                'clientId' => $this->clientId,
                'conditions' => "identifier='{$identifier}'"
            ],
        ]);
        return json_decode($response->getBody()->getContents())[0] ?? null;
    }

    public function getCatalogItemsByBarcode(string $barcode, $fields=null, $page=null, $pageSize=25)
    {
        try {
            $response = $this->http->get('procurement/catalog', [
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
        return json_decode($response->getBody()->getContents());
    }

    public function getCatalogItemsQty($conditions=null)
    {
        try {
            $response = $this->http->get('procurement/catalog/count', [
                'query' => [
                    'clientId' => $this->clientId,
                    'conditions' => $conditions,
                ]
            ]);
        } catch (GuzzleException $e) {
            return new \stdClass();
        }
        return json_decode($response->getBody()->getContents());
    }

    public function getCatalogItemOnHand($id, $warehouseBinId=self::DEFAULT_WAREHOUSE_DEFAULT_BIN)
    {
        try {
            $response = $this->http->get("procurement/catalog/{$id}/quantityOnHand", [
                'query' => [
                    'clientId' => $this->clientId,
                    'warehouseBinId' => $warehouseBinId
                ]
            ]);
        } catch (GuzzleException $e) {
            return new \stdClass();
        }
        return json_decode($response->getBody()->getContents());
    }

    public function purchaseOrders($page=null, $conditions=null, $fields=null, $orderBy=null, $pageSize=1000, string $cin7SalesOrderId=null)
    {
        $query = [
            'page' => $page,
            'clientId' => $this->clientId,
            'conditions' => $conditions,
            'fields' => $fields,
            'pageSize' => $pageSize,
            'orderBy' => $orderBy,
            'customFieldConditions' => $cin7SalesOrderId ? "caption='Cin7 SalesOrder ID' and value='{$cin7SalesOrderId}'" : null
        ];

        try {
            $response = $this->http->get('procurement/purchaseorders', [
                'query' => $query,
            ]);
        } catch (\Exception) {
            sleep(2);
            $response = $this->http->get('procurement/purchaseorders', [
                'query' => $query,
            ]);
        }
        return json_decode($response->getBody()->getContents());
    }

    public function purchaseOrder(int $id)
    {
        try {
            $response = $this->http->get("procurement/purchaseorders/{$id}?clientId={$this->clientId}");
        } catch (GuzzleException $e) {
            return new \stdClass();
        }
        return json_decode($response->getBody()->getContents());
    }

    public function getCompanies($page=1, $conditions=null, $fields=null, $pageSize=100)
    {
        $response = $this->http->get("company/companies?clientId={$this->clientId}", [
            'query' => [
                'page' => $page,
                'clientId' => $this->clientId,
                'conditions' => $conditions,
                'pageSize' => $pageSize,
                'fields' => $fields
            ],
        ]);

        return json_decode($response->getBody()->getContents());
    }

    public function purchaseOrderItems($id, $page=null, $conditions=null, $fields=null)
    {
        $po = $this->purchaseOrders(null, 'id=' . $id)[0];

        return array_map(function (\stdClass $item) use ($po) {
            return $this->preparePoItem($po->id, $item, $po);
        }, $this->purchaseOrderItemsOriginal($id, $page, $conditions, $fields));
    }

    public function purchaseOrderItemsOriginal($id, $page=null, $conditions=null, $fields=null, $pageSize=1000)
    {
        $response = $this->http->get("procurement/purchaseorders/{$id}/lineitems", [
            'query' => [
                'page' => $page,
                'clientId' => $this->clientId,
                'conditions' => $conditions,
                'pageSize' => $pageSize,
                'fields' => $fields
            ],
        ]);

        return json_decode($response->getBody()->getContents());
    }

    public function purchaseOrderItem($poId, $itemId)
    {
        try {
            $response = $this->http->get("procurement/purchaseorders/{$poId}/lineitems/{$itemId}?clientId={$this->clientId}");
        } catch (GuzzleException $e) {
            return new \stdClass();
        }

        return json_decode($response->getBody()->getContents());
    }

    public function getOpenPoItems() : Collection
    {
        if (!cache()->has('poItems')) {
            $poItems = $this->cachePos();
        } else {
            $poItems = cache()->get('poItems');
        }

        return $poItems->whereIn('poStatus.id', [1,3])->where('poClosedFlag', false)->where('closedFlag', false);
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
        $item->receivedQuantity = @$poItem->receivedQuantity;
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

        $poItems->push(...$this->purchaseOrderItems($po->id));

        cache()->forever('poItems', $poItems);

        return $poItems;
    }

    /**
     * @throws GuzzleException
     */
    public function purchaseOrderItemReceiveUsingCache(int $itemId, $quantity)
    {
        try {
            $item = $this->getOpenPoItems()->where('id', $itemId)->first();

            $poId = $item->poId;
        } catch (\Exception $e) {
            $this->cachePos();

            throw $e;
        }

        $putItem = $this->purchaseOrderItem($poId, $itemId);

        if ($item->quantity != $quantity) {
            $putItem->receivedStatus = 'PartiallyReceiveCloneRest';
        }

        $response = $this->preparePoItem($poId, $this->purchaseOrderItemReceive($poId, $putItem, $quantity));

        $this->updatePoItems($poId);

        return $response;
    }

    /**
     * @throws GuzzleException
     */
    public function purchaseOrderItemReceive(int $poId, \stdClass $lineItem, $quantity)
    {
        if (@$lineItem->receivedQuantity == $quantity) {
            return $lineItem;
        }

        $lineItem->receivedQuantity = $quantity;
        $lineItem->closedFlag = true;
        $response = $this->http->put("procurement/purchaseorders/{$poId}/lineitems/{$lineItem->id}", [
            'query' => [
                'clientId' => $this->clientId
            ],
            'json' => $lineItem
        ]);

        return json_decode($response->getBody()->getContents());
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

    public function getProducts($page=null, $conditions=null, $pageSize=null, $customFieldConditions=null, $fields=null)
    {
        $response = $this->http->get('procurement/products', [
            'query' => [
                'page' => $page,
                'clientId' => $this->clientId,
                'conditions' => $conditions,
                'pageSize' => $pageSize,
                'customFieldConditions' => $customFieldConditions,
                'fields' => $fields
            ],
        ]);
        return json_decode($response->getBody()->getContents());
    }

    public function getProduct($id)
    {
        $response = $this->http->get("procurement/products/{$id}?clientId={$this->clientId}");
        return json_decode($response->getBody()->getContents());
    }

    public function getProductsByCin7ProductId($id)
    {
        return $this->getProducts(1, null, 25, "caption='Cin7 Product ID' and value='{$id}'");
    }

    public function updateProduct(\stdClass $product)
    {
        $response = $this->http->put("procurement/products/{$product->id}?clientId={$this->clientId}", [
            'json' => $product,
        ]);

        return json_decode($response->getBody()->getContents());
    }

    public function updateProject(\stdClass $project)
    {
        $response = $this->http->put("project/projects/{$project->id}?clientId={$this->clientId}", [
            'json' => $project,
        ]);

        return json_decode($response->getBody()->getContents());
    }

    public function getProductPickingShippingDetails($id, $page=null, $conditions=null)
    {
        try {
            $response = $this->http->get("procurement/products/{$id}/pickingShippingDetails", [
                'query' => [
                    'page' => $page,
                    'clientId' => $this->clientId,
                    'conditions' => $conditions
                ],
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($response->getBody()->getContents());
    }

    public function productPickShipDelete($productId, $shipmentId)
    {
        $this->http->delete("procurement/products/{$productId}/pickingShippingDetails/{$shipmentId}?clientId=" . $this->clientId);
    }

    /**
     * @throws GuzzleException
     */
    public function pickProduct($id, $quantity)
    {
        $pickShip = json_decode('{"warehouseBin":{},"productItem":{}}');
        $pickShip->pickedQuantity = (int)$quantity;
        $pickShip->shippedQuantity = 0;
        $pickShip->id = 0;
        $pickShip->quantity = (int)$quantity;
        $pickShip->warehouseBin->id = 1;
        $pickShip->productItem->id = $id;

        try {
            $this->addOrUpdatePickShip($pickShip);
        } catch (\Exception $e) {
            if (Str::contains($e->getMessage(), 'There are 0 items on hand')) {
                sleep(3);
                $this->addOrUpdatePickShip($pickShip);
            } else {
                throw $e;
            }
        }

        return $pickShip;
    }

    /**
     * @throws GuzzleException
     */
    public function pickAndShipProduct($id, $quantity)
    {
        $pickShip = json_decode('{"warehouseBin":{},"productItem":{}}');
        $pickShip->pickedQuantity = (int)$quantity;
        $pickShip->shippedQuantity = (int)$quantity;
        $pickShip->id = 0;
        $pickShip->quantity = (int)$quantity;
        $pickShip->warehouseBin->id = 1;
        $pickShip->productItem->id = $id;

        return $this->addOrUpdatePickShip($pickShip);
    }

    /**
     * @throws \Exception|GuzzleException
     */
    public function unpickProduct($id, $quantity) : void
    {
        $dynamicQty = $quantity;
        collect($this->getProductPickingShippingDetails($id, null, "lineNumber != 0"))
            ->filter(fn($pickShip) => $pickShip->shippedQuantity < $pickShip->pickedQuantity)
            ->map(function ($pickShip) use (&$dynamicQty) {

                if ($dynamicQty == 0) {
                    return false;
                }

                $unpickAvailableQty = $pickShip->pickedQuantity - $pickShip->shippedQuantity;

                if ($dynamicQty > $unpickAvailableQty) {
                    $pickShip->pickedQuantity -= $unpickAvailableQty;
                    $dynamicQty = $dynamicQty - $unpickAvailableQty;
                } else {
                    $pickShip->pickedQuantity -= $dynamicQty;
                    $dynamicQty = 0;
                }

                return $pickShip;
            })
            ->filter(fn($pickShip) => !!$pickShip)
            ->map(function ($pickShip) use ($dynamicQty) {

                if ($dynamicQty > 0) {
                    throw new \Exception('Unpicking quantity cannot be greater than picked quantity');
                }

                $this->addOrUpdatePickShip($pickShip);
            })
        ;
    }

    /**
     * @throws \Exception|GuzzleException
     */
    public function unshipProduct($id, $quantity) : void
    {
        $dynamicQty = $quantity;
        collect($this->getProductPickingShippingDetails($id, null, "lineNumber != 0"))
            ->filter(fn($pickShip) => $pickShip->shippedQuantity > 0)
            ->map(function ($pickShip) use (&$dynamicQty) {

                if ($dynamicQty == 0) {
                    return false;
                }

                if ($dynamicQty > $pickShip->shippedQuantity) {
                    $dynamicQty = $dynamicQty - $pickShip->shippedQuantity;
                    $pickShip->shippedQuantity = 0;
                } else {
                    $pickShip->shippedQuantity -= $dynamicQty;
                    $dynamicQty = 0;
                }

                return $pickShip;
            })
            ->filter(fn($pickShip) => !!$pickShip)
            ->map(function ($pickShip) use (&$dynamicQty) {

                if ($dynamicQty > 0) {
                    throw new \Exception('Unshipping quantity cannot be greater than shipped quantity');
                }

                $this->addOrUpdatePickShip($pickShip);
            })
        ;
    }

    /**
     * @throws \Exception|GuzzleException
     */
    public function shipProduct($id, $quantity)
    {
        $dynamicQty = $quantity;

        $pickingShippingDetails = $this->getProductPickingShippingDetails($id, null, 'lineNumber != 0');

        $pickShips = collect($pickingShippingDetails)
            ->filter(fn($pickShip) => $pickShip->shippedQuantity < $pickShip->pickedQuantity)
            ->map(function ($pickShip) use (&$dynamicQty) {

                if ($dynamicQty == 0) {
                    return false;
                }

                $shippableQuantity = $pickShip->pickedQuantity - $pickShip->shippedQuantity;

                if ($dynamicQty > $shippableQuantity) {
                    $pickShip->shippedQuantity += $shippableQuantity;
                    $dynamicQty = $dynamicQty - $shippableQuantity;
                } else {
                    $pickShip->shippedQuantity += $dynamicQty;
                    $dynamicQty = 0;
                }

                return $pickShip;
            })
            ->filter(fn($pickShip) => !!$pickShip)
            ->map(function ($pickShip) use ($dynamicQty, $id) {

                if ($dynamicQty > 0) {
                    throw new \Exception('Shipping quantity cannot be greater than picked quantity, productId:' . $id);
                }

                $this->addOrUpdatePickShip($pickShip);

                return $pickShip;
            })
        ;

        if ($dynamicQty == $quantity) {
            throw new \Exception('Shipping quantity cannot be greater than picked quantity, productId:' . $id);
        }

        return $pickShips;
    }

    /**
     * @throws GuzzleException|\Exception
     */
    public function addOrUpdatePickShip(\stdClass $pickShipDetail)
    {
        $payload = [
            "productDetail" => [
                "IV_Product_RecID" => $pickShipDetail->productItem->id,
                "quantity_Picked" => $pickShipDetail->pickedQuantity,
                "quantity_Shipped" => $pickShipDetail->shippedQuantity,
                "warehouse_Bin_RecID" => $pickShipDetail->warehouseBin->id
            ]
        ];

        if ($pickShipDetail->id) {
            $payload['productDetail']['IV_Product_Detail_RecID'] = $pickShipDetail->id;
            $payload['productDetail']['line_Number'] = $pickShipDetail->lineNumber;
        }

        $response = $this->internalApiRequest(
            "actionprocessor/Procurement/SavePickingAndShippingAction.rails",
            $payload,
            "SavePickingAndShippingAction",
            "ProcurementCommon"
        );

        if (!$response->data->isSuccess) {
            throw new \Exception(json_encode($response->data->error));
        }

        return $response;
    }

    public function productPickShip($id, $quantity, $used=false)
    {
        if ($used) {
            $product = $this->getProduct($id);
            $product->catalogItem = $this->createUsedCatalogItem($product->catalogItem->id, $quantity);

            $this->addToReport('CatalogProductUsed', $product, 'unshipped/returned as used');
            return $product;
        }

        $origPickShip = $this->getProductPickingShippingDetails($id)[0];
        $pickShip = clone $origPickShip;
        $pickShip->pickedQuantity = $pickShip->shippedQuantity = $quantity;
        $pickShip->id = 0;
        $pickShip->quantity = $quantity;
        $pickShip->warehouseBin->id = 1;

        $this->pickAndShipProduct($id, $quantity);

        $this->addToReport('ProductShipment', $pickShip, $quantity < 0 ? 'unshipped/returned' : 'shipped');

        return $pickShip;
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
            if ($img->width() > 4032 || $img->height() > 4032) {
                $file = $img->scale(4032, 4032)->encode();
            }
        }

        $filename = md5($file->__toString()) . '.' . $ext;

        $file = method_exists($file, 'getContent') ? $file->getContent() : $file;

        $response = $this->http->post( 'system/documents?clientId=' . $this->clientId, [
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

       return json_decode($response->getBody()->getContents());
    }

    public function getSystemMembers($page=null, $conditions=null)
    {
        try {
            $response = $this->http->get('system/members', [
                'query' => [
                    'page' => $page,
                    'clientId' => $this->clientId,
                    'conditions' => $conditions,
                ],
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($response->getBody()->getContents());
    }

    public function getProjects($page=null, $conditions=null, $fields=null)
    {
        try {
            $response = $this->http->get('project/projects', [
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
        return json_decode($response->getBody()->getContents());
    }

    public function getProject($id, $fields=null)
    {
        try {
            $response = $this->http->get('project/projects/' . $id, [
                'query' => [
                    'clientId' => $this->clientId,
                    'fields' => $fields
                ],
            ]);
        } catch (GuzzleException $e) {
            return null;
        }
        return json_decode($response->getBody()->getContents());
    }

    public function getSystemDepartments($page=null, $conditions=null)
    {
        $response = $this->http->get('system/departments', [
            'query' => [
                'page' => $page,
                'clientId' => $this->clientId,
                'conditions' => $conditions,
            ],
        ]);
        return json_decode($response->getBody()->getContents());
    }

    public function getSystemInfoDepartments($page=null, $conditions=null)
    {
        $response = $this->http->get('system/info/departments', [
            'query' => [
                'page' => $page,
                'clientId' => $this->clientId,
                'conditions' => $conditions,
            ],
        ]);
        return json_decode($response->getBody()->getContents());
    }

    public function updateSystemDepartment(\stdClass|array $department)
    {
        $response = $this->http->put("system/departments/{$department->id}?clientId={$this->clientId}", [
            'json' => $department
        ]);
        return json_decode($response->getBody()->getContents());
    }

    public function getSystemDepartment($id, $fields=null)
    {
        try {
            $response = $this->http->get('system/departments/' . $id, [
                'query' => [
                    'clientId' => $this->clientId,
                    'fields' => $fields
                ],
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($response->getBody()->getContents());
    }

    public function getCategories($page=null, $conditions=null, $orderBy=null, $pageSize=100)
    {
        try {
            $response = $this->http->get('procurement/categories', [
                'query' => [
                    'page' => $page,
                    'clientId' => $this->clientId,
                    'conditions' => $conditions,
                    'orderBy' => $orderBy,
                    'pageSize' => $pageSize
                ],
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($response->getBody()->getContents());
    }

    public function getCategory($id)
    {
        try {
            $response = $this->http->get("procurement/categories/{$id}?clientId={$this->clientId}");
        } catch (GuzzleException $e) {
            return null;
        }
        return json_decode($response->getBody()->getContents());
    }

    public function updateCategory(\stdClass $category)
    {
        $response = $this->http->put("procurement/categories/{$category->id}?clientId={$this->clientId}", [
            'json' => $category,
        ]);
        return json_decode($response->getBody()->getContents());
    }

    public function updateCompany(\stdClass $company)
    {
        $response = $this->http->put("company/companies/{$company->id}?clientId={$this->clientId}", [
            'json' => $company,
        ]);
        return json_decode($response->getBody()->getContents());
    }

    public function updatePhase(\stdClass $phase)
    {
        $response = $this->http->put("project/projects/{$phase->projectId}/phases/{$phase->id}?clientId={$this->clientId}", [
            'json' => $phase,
        ]);
        return json_decode($response->getBody()->getContents());
    }

    public function updateTicket(\stdClass $ticket)
    {
        $url = (@$ticket->project ? "project" : "service");

        $response = $this->http->put($url . "/tickets/{$ticket->id}?clientId={$this->clientId}", [
            'json' => $ticket,
        ]);
        return json_decode($response->getBody()->getContents());
    }

    public function ticket(int $ticketId, bool $isProject=false)
    {
        $url = ($isProject ? "project" : "service");

        $response = $this->http->get($url . "/tickets/{$ticketId}?clientId={$this->clientId}");

        return json_decode($response->getBody()->getContents());
    }

    public function getProjectTickets($page=null, $conditions=null, $fields=null, $orderBy=null)
    {
        $response = $this->http->get('project/tickets', [
            'query' => [
                'page' => $page,
                'clientId' => $this->clientId,
                'conditions' => $conditions,
                'orderBy' => $orderBy,
                'fields' => $fields
            ],
        ]);
        return json_decode($response->getBody()->getContents());
    }

    public function getProjectTicket($id)
    {
        $response = $this->http->get("project/tickets/{$id}", [
            'query' => [
                'clientId' => $this->clientId
            ],
        ]);
        return json_decode($response->getBody()->getContents());
    }

    public function getServiceTickets($page=null, $conditions=null, $fields=null, $orderBy=null)
    {
        try {
            $response = $this->http->get('service/tickets', [
                'query' => [
                    'page' => $page,
                    'clientId' => $this->clientId,
                    'conditions' => $conditions,
                    'orderBy' => $orderBy,
                    'fields' => $fields,
                    'pageSize' => 1000
                ],
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($response->getBody()->getContents());
    }

    public function getServiceTicket($id)
    {
        $response = $this->http->get("service/tickets/{$id}", [
            'query' => [
                'clientId' => $this->clientId
            ],
        ]);
        return json_decode($response->getBody()->getContents());
    }

    public function getSubcategories($page=null, $conditions=null, $orderBy=null)
    {
        try {
            $response = $this->http->get('procurement/subcategories', [
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
        return json_decode($response->getBody()->getContents());
    }

    public function getSubcategory($id)
    {
        try {
            $response = $this->http->get("procurement/subcategories/{$id}?clientId={$this->clientId}");
        } catch (GuzzleException $e) {
            return null;
        }
        return json_decode($response->getBody()->getContents());
    }

    public function updateSubcategory(\stdClass $category)
    {
        try {
            $response = $this->http->put("procurement/subcategories/{$category->id}?clientId={$this->clientId}", [
                'json' => $category,
            ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($response->getBody()->getContents());
    }

    public function getCatalogItem($id)
    {
        try {
            $response = $this->http->get('procurement/catalog/' . $id, [
                'query' => [
                    'clientId' => $this->clientId
                ],
            ]);
        } catch (GuzzleException $e) {
            return new \stdClass();
        }
        return json_decode($response->getBody()->getContents());
    }

    public function addBarcode(int $catalogItemId, array $newBarcodes)
    {
        $catalogItem = $this->getCatalogItem($catalogItemId);

        $barcodes = $this->extractBarcodesFromCatalogItem($catalogItem);

        $values = array_unique(array_merge($barcodes, $newBarcodes));

        $barcodeValue = json_encode($values);

        $catalogItem = $this->setCustomFieldValue($catalogItem, 'Barcodes', $barcodeValue);

        $this->updateCatalogItem($catalogItem);

        if (!Str::contains($catalogItem->unitOfMeasure->name, 'Used cable')) {
            $usedItems = $this->getCatalogItems(null, "identifier like '{$catalogItem->identifier}(*' and unitOfMeasure/name contains 'Used cable'", null, null, 100);

            foreach ($usedItems as $usedItem) {

                $usedItem = $this->setCustomFieldValue($usedItem, 'Barcodes', $barcodeValue);

                $this->updateCatalogItem($usedItem);
            }
        }

        return $values;
    }

    public function extractCin7SalesOrderId(\stdClass $purchaseOrder)
    {
        return $this->extractCustomFieldValueByName($purchaseOrder, 'Cin7 SalesOrder ID');
    }

    public function extractCin7ProductId(\stdClass $catalogItem)
    {
        return $this->extractCustomFieldValueByName($catalogItem, 'Cin7 Product ID');
    }

    public function extractCin7ProductFamilyId(\stdClass $catalogItem)
    {
        return $this->extractCustomFieldValueByName($catalogItem, 'Cin7 Product Family ID');
    }

    public function updateCatalogItemCin7ProductId(\stdClass $catalogItem, $productId)
    {
        $catalogItem = $this->setCustomFieldValue($catalogItem, 'Cin7 Product ID', $productId);

        $this->updateCatalogItem($catalogItem);

        return $catalogItem;
    }

    public function updatePurchaseOrderCin7SalesOrderId(\stdClass $purchaseOrder, $salesOrderId)
    {
        $purchaseOrder = $this->setCustomFieldValue($purchaseOrder, 'Cin7 SalesOrder ID', $salesOrderId);

        $this->updatePurchaseOrder($purchaseOrder);

        return $purchaseOrder;
    }

    public function updateCatalogItemCin7ProductFamilyId(\stdClass $catalogItem, $productFamilyId)
    {
        $catalogItem = $this->setCustomFieldValue($catalogItem, 'Cin7 Product Family ID', $productFamilyId);

        $this->updateCatalogItem($catalogItem);

        return $catalogItem;
    }

    public function extractBarcodesFromCatalogItem(\stdClass $catalogItem) : array
    {
        $barcodes = $this->extractCustomFieldValueByName($catalogItem, 'Barcodes');

        if (!$barcodes)
            return [];

        return json_decode($barcodes);
    }

    public function getAttachments($recordType, $recordId, $page=null, $conditions=null, $fields=null, $pageSize=25)
    {
        try {
            $response = $this->http->get('system/documents', [
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
        return json_decode($response->getBody()->getContents());
    }

    public function downloadAttachment($id)
    {
        try {
            $response = $this->http->get("system/documents/{$id}/download", [
                'query' => [
                    'clientId' => $this->clientId
                ],
            ]);
        } catch (GuzzleException $e) {
            return '';
        }
        return $this->fileResponse($response->getBody()->getContents());
    }

    public function downloadAllAttachments($recordType, $recordId)
    {
        return array_map(function ($attachment) {
            return $this->downloadAttachment($attachment->id);
        }, $this->getAttachments($recordType, $recordId));
    }

    public function systemDocumentUploadProduct($file, $recordId, $filename, $privateFlag=true, $readonlyFlag=false, $isAvatar=false)
    {
        $response = $this->http->post( 'system/documents?clientId=' . $this->clientId, [
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

        return json_decode($response->getBody()->getContents());
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

    public function catalogItemAdjustBulk(Collection $adjustmentDetails, string $reason="Quantity update") {
        $adjustmentID = date('m/d/Y') . ' - ' . time();

        $json = json_decode("
        {
            \"id\": 0,
            \"identifier\": \"{$adjustmentID}\",
            \"type\": {
                \"id\": 1
            },
            \"reason\": \"{$reason}\",
            \"closedFlag\": true
        }
        ");

        $json->adjustmentDetails = $adjustmentDetails->values()->toArray();

        try {
            $response = $this->http->post('procurement/adjustments?clientId=' . $this->clientId, [
                'json' => $json
            ]);
        } catch (GuzzleException $e) {
            abort(500, $e->getResponse()->getBody()->getContents());
        }

        return json_decode($response->getBody()->getContents());
    }

    public function convertCatalogItemToAdjustmentDetail(\stdClass $catalogItem, $qty, $warehouseId=self::DEFAULT_WAREHOUSE)
    {
        $defaultBinId = $warehouseId == self::DEFAULT_WAREHOUSE ? self::DEFAULT_WAREHOUSE_DEFAULT_BIN : self::AZAD_MAY_WAREHOUSE_DEFAULT_BIN;

        return json_decode("
            {
                \"id\": 0,
                \"catalogItem\": {
                    \"id\": {$catalogItem->id}
                },
                \"description\": \"Updating quantity\",
                \"unitCost\": {$catalogItem->cost},
                \"warehouse\": {
                    \"id\": {$warehouseId}
                },
                \"warehouseBin\": {
                    \"id\": {$defaultBinId}
                },
                \"quantityAdjusted\": {$qty}
            }
        ");
    }

    public function catalogItemAdjust(\stdClass $catalogItem, $qty, $warehouseId=self::DEFAULT_WAREHOUSE)
    {
        $adjustmentDetail = $this->convertCatalogItemToAdjustmentDetail($catalogItem, $qty, $warehouseId);

        return $this->catalogItemAdjustBulk(collect([$adjustmentDetail]));
    }

    public function createUsedCatalogItem(int $catalogItemId, int $qty)
    {
        $catalogItem = $this->getCatalogItem($catalogItemId);

        $uom = Str::replace(' ', '', Str::lower($catalogItem->unitOfMeasure->name));

        if (Str::contains($uom, 'usedcable')) {
            $identifierArr = explode('(', $catalogItem->identifier);
            $catalogItem->identifier = $identifierArr[0];

            $oldLength = (int)Str::numbers($identifierArr[1]);

            $catalogItem->price = round($catalogItem->price / $oldLength, 2);
            $catalogItem->cost = round($catalogItem->cost / $oldLength, 2);
        } else {
            $num = (int)Str::numbers($uom);

            if ($num) {
                $catalogItem->price = round($catalogItem->price / $num / 3, 2);
                $catalogItem->cost = round($catalogItem->cost / $num / 3, 2);
            } else {
                $catalogItem->price = round($catalogItem->price / 3, 2);
                $catalogItem->cost = round($catalogItem->cost / 3, 2);
            }
        }

        if (Str::contains($uom, 'ft)') || Str::contains($uom, 'usedcable')) {
            $catalogItem->identifier = $catalogItem->identifier . Str::lower("({$qty}ft)");
            $catalogItem->unitOfMeasure = [
                'id' => 22,
                'name' => 'Box (Used cable)'
            ];

            $catalogItem->price *= $qty;
            $catalogItem->cost *= $qty;
        } else {
            $catalogItem->identifier = $catalogItem->identifier . "-RF";

            $catalogItem->unitOfMeasure = [
                'id' => 1,
                'name' => 'Pcs'
            ];
        }

        $attachments = collect($this->getAttachments(self::RECORD_TYPE_PRODUCT_SETUP, $catalogItem->id));

        $catalogItem->id = 0;

        $response = $this->http->post('procurement/catalog?clientId=' . $this->clientId, [
            'json' => $catalogItem
        ]);

        $catalogItem = json_decode($response->getBody()->getContents());

        $attachments->map(function ($attachment) use ($catalogItem) {
            $file = $this->downloadAttachment($attachment->id)->getFile()->getContent();

            $this->systemDocumentUploadProduct($file, $catalogItem->id, $attachment->title, true, true);
        });

        return $catalogItem;
    }

    public function updateCatalogItem(\stdClass $item)
    {
        $response = $this->http->put( "procurement/catalog/{$item->id}?clientId=" . $this->clientId, [
            'json' => $item
        ]);

        return json_decode($response->getBody()->getContents());
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

        $response = $this->http->post( 'system/documents?clientId=' . $this->clientId, [
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

        return json_decode($response->getBody()->getContents());
    }

    public function getProductCatalogOnHand($page=null, $conditions=null, $fields=null, $pageSize=25, $warehouseBinId=self::AZAD_MAY_WAREHOUSE_DEFAULT_BIN)
    {
        try {
            $response = $this->http->get("procurement/warehouseBins/{$warehouseBinId}/inventoryOnHand", [
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
        return json_decode($response->getBody()->getContents());
    }

    public function getPoReport($poId)
    {
        try {
            $response = $this->http->get("{$this->systemIO}reports/reportingservices/ReportPdfView.rails?reportLink=%2fbinyod%2fProcurement%2fPurchaseOrder_Button&reportOnly=true&rp=recordid%3D{$poId}%26Language%3Den-US%26&clientId={$this->clientId}");
        } catch (GuzzleException $e) {
            return '';
        }

        return $this->fileResponse($response->getBody()->getContents());
    }

    private function fileResponse($body): BinaryFileResponse
    {
        $temp = tempnam(sys_get_temp_dir(), 'TMP_');
        file_put_contents($temp, $body);
        return response()->file($temp)->deleteFileAfterSend();
    }

    /**
     * @throws GuzzleException|\Exception
     */
    public function getPurchaseOrderItemTicketInfo($poId, $poItemId)
    {
        $payload = [
            "requestAllCount" => false,
            "usePagination" => false,
            "multilineType" => "SalesMultiline",
            "activePage" => 1,
            "maxResult" => 25,
            "culture" => "en_US",
            "multilineName" => "purchaseordersalesandserviceinformationlist",
            "sortDirection" => "",
            "sortField" => "",
            "multilineUserParams" => [
                [
                    "paramKey" => "Purchase_Header_RecID",
                    "paramValue" => $poId
                ],
                [
                    "paramKey" => "Purchase_Detail_RecID",
                    "paramValue" => $poItemId
                ]
            ],
            "columns" => [
                "item_id",
                "product_description",
                "serial_number",
                "quantity",
                "unit_cost",
                "sr_service_recid",
                "order_header_recid",
                "summary",
                "project_id",
                "invoice_number",
                "company_name",
                "opportunity_name",
                "minimum_stock_flag"
            ]
        ];

        $response = $this->internalApiRequest(
            'actionprocessor/System/GetMultilineDataAction_purchaseordersalesandserviceinformationlist.rails',
            $payload,
            "GetMultilineDataAction",
            "SystemCommon"
        );

        if (!$response->success || !$response->data->isSuccess) {
            throw new \Exception(json_encode($response->error ?: $response->data->error));
        }

        return array_map(function ($item) {
            return json_decode($item->row);
        }, $response->data->action->multilineRows);
    }

    /**
     * @throws GuzzleException|\Exception
     */
    public function getProductPoItems(int $productId)
    {
        $payload = [
            "requestAllCount" => true,
            "usePagination" => true,
            "multilineType" => "ProcurementMultiline",
            "activePage" => 1,
            "maxResult" => 25,
            "culture" => "en_US",
            "multilineName" => "ProductPurchaseOrderList",
            "sortDirection" => "",
            "sortField" => "",
            "multilineUserParams" => [
                [
                    "paramKey" => "iv_product_recid",
                    "paramValue" => $productId
                ]
            ],
            "columns" => [
                "po_number",
                "vendor_name",
                "po_date",
                "po_status",
                "product_status",
                "expected_ship_date",
                "ship_date",
                "expected_date_of_arrival",
                "tracking_number",
                "received_qty"
            ]
        ];

        $response = $this->internalApiRequest(
            'actionprocessor/System/GetMultilineDataAction_ProductPurchaseOrderList.rails',
            $payload,
            "GetMultilineDataAction",
            "SystemCommon"
        );

        if (!$response->success || !$response->data->isSuccess) {
            throw new \Exception(json_encode($response->error ?: $response->data->error));
        }

        return array_map(function ($item) {
            return json_decode($item->row);
        }, $response->data->action->multilineRows);
    }

    public function unitOfMeasures()
    {
        $response = $this->http->get("procurement/unitOfMeasures", [
            'query' => [
                'clientId' => $this->clientId,
                'conditions' => 'inactiveFlag=false'
            ],
        ]);

        return json_decode($response->getBody()->getContents());
    }

    public function unitOfMeasure(int $id)
    {
        $response = $this->http->get("procurement/unitOfMeasures/{$id}", [
            'query' => [
                'clientId' => $this->clientId
            ],
        ]);

        return json_decode($response->getBody()->getContents());
    }

    public function updateUnitOfMeasure(\stdClass $unitOfMeasure)
    {
        $response = $this->http->put("procurement/unitOfMeasures/{$unitOfMeasure->id}?clientId={$this->clientId}", [
            'json' => $unitOfMeasure,
        ]);

        return json_decode($response->getBody()->getContents());
    }

    public function publishProductFamilyOnCin7($catalogItemId, $catalogItem=null, $onBigCommerceAsWell=false)
    {
        $catalogItem = $catalogItem ?: $this->getCatalogItem($catalogItemId);

        $notAllowedCategories = Setting::getBySlug(Setting::NOT_ALLOWED_CATEGORIES);

        if ($notAllowedCategories && in_array($catalogItem->category->id, $notAllowedCategories->value) || $catalogItem->inactiveFlag) {
            return null;
        }

        $productFamilyId = $this->extractCin7ProductFamilyId($catalogItem);

        $productFamily = $productFamilyId ? $this->cin7Service->productFamily($productFamilyId) : null;

        if (!$productFamily) {
            $productFamily = $this->cin7Service->createProductFamily(
                $this->generateProductFamilySku($catalogItem->identifier),
                $this->generateProductName($catalogItem->description, $catalogItem->identifier),
                $catalogItem->category->name,
                $catalogItem->unitOfMeasure->name,
                $catalogItem->customerDescription
            );
        }

        if ($productFamilyId != $productFamily->ID) {
            $this->updateCatalogItemCin7ProductFamilyId($catalogItem, $productFamily->ID);

            if (Str::contains($productFamily->Name, Cin7Service::PRODUCT_FAMILY_INACTIVE)) {
                $this->cin7Service->updateProductFamily([
                    'ID' => $productFamily->ID,
                    'Name' => Str::replace(Cin7Service::PRODUCT_FAMILY_INACTIVE, '', $productFamily->Name)
                ]);
            }
        }

        if ($onBigCommerceAsWell) {
            $this->publishProductOnBigCommerce($catalogItemId, $catalogItem);
        }

        defer(fn() => $this->syncCatalogItemAttachmentsWithCin7($catalogItem->id, $productFamily->ID, $onBigCommerceAsWell, $catalogItem));

        return $productFamily;
    }

    public function syncCatalogItemAttachmentsWithCin7($catalogItemId, $cin7ProductFamilyId, $toBigCommerceAsWell=false, $catalogItem=null, $isProductFamily=true) : bool
    {
        $attachments = collect($this->getAttachments(ConnectWiseService::RECORD_TYPE_PRODUCT_SETUP, $catalogItemId));

        if (!$attachments->count()) {
            return false;
        }

        $cin7Attachments = collect($isProductFamily ? $this->cin7Service->productFamilyAttachments($cin7ProductFamilyId) : $this->cin7Service->productAttachments($cin7ProductFamilyId));

        $bigCommerceProduct = null;
        $bigCommerceAttachments = null;

        if ($toBigCommerceAsWell) {
            $catalogItem = $catalogItem ?: $this->getCatalogItem($catalogItemId);

            $bigCommerceProduct = $this->bigCommerceService->getProductBySku($isProductFamily ? $this->generateProductFamilySku($catalogItem->identifier) : $catalogItem->identifier);

            if ($bigCommerceProduct && ($bigCommerceAttachments = collect($this->bigCommerceService->getProductImages($bigCommerceProduct->id)->data))->count() > 0) {

                $bigCommerceAttachments
                    ->filter(
                        fn($bigCommerceAttachment) => !$attachments
                            ->filter(
                                fn($attachment) => Str::contains($bigCommerceAttachment->image_file, pathinfo($attachment->fileName, PATHINFO_FILENAME))
                            )
                            ->count()
                    )
                    ->map(fn($bigCommerceAttachment) => $this->bigCommerceService->deleteProductImage($bigCommerceProduct->id, $bigCommerceAttachment->id));

            }
        }

        $cin7Attachments->filter(fn($cin7Attachment) => !$attachments->where('fileName', $cin7Attachment->FileName)->count())
            ->map(fn($cin7Attachment) => $isProductFamily ? $this->cin7Service->deleteProductFamilyAttachment($cin7Attachment->ID) : $this->cin7Service->deleteProductAttachment($cin7Attachment->ID));

        $attachments->map(function ($attachment, $index) use ($cin7Attachments, $isProductFamily, $bigCommerceAttachments, $bigCommerceProduct, $toBigCommerceAsWell, $cin7ProductFamilyId) {
                $file = $this->downloadAttachment($attachment->id)->getFile()->getContent();

                if (!$cin7Attachments->where('FileName', $attachment->fileName)->count()) {
                    if ($isProductFamily) {
                        $this->cin7Service->uploadProductFamilyAttachment(
                            $cin7ProductFamilyId,
                            $attachment->fileName,
                            base64_encode($file),
                            $index == 0
                        );
                    } else {
                        $this->cin7Service->uploadProductAttachment(
                            $cin7ProductFamilyId,
                            $attachment->fileName,
                            base64_encode($file),
                            $index == 0
                        );
                    }
                }

                if (
                    $toBigCommerceAsWell
                    && $bigCommerceProduct
                    && $bigCommerceAttachments->filter(fn($bigCommerceAttachment) => Str::contains($bigCommerceAttachment->image_file, pathinfo($attachment->fileName, PATHINFO_FILENAME)))->count() == 0
                ) {
                    $this->bigCommerceService->uploadProductImage(
                        $bigCommerceProduct->id,
                        $file,
                        $attachment->fileName,
                        !$index && $bigCommerceAttachments->count() == 0);
                }
            });

        return true;
    }

    public function publishProductOnCin7(\stdClass $product, $quantity, $onBigCommerceAsWell=false, $cin7AdjustmentId=null)
    {
        $catalogItem = $this->getCatalogItem($product->catalogItem->id);

        $productFamily = $this->publishProductFamilyOnCin7($catalogItem->id, $catalogItem, $onBigCommerceAsWell);

        if (!$productFamily) {
            return null;
        }

        $cin7ProductSku = $this->generateProductSku(
            $productFamily->SKU,
            $product->project->id ?? null,
            $product->ticket->id ?? null,
            $product->company->id ?? null
        );

        $cin7Product = $this->cin7Service->productBySku($cin7ProductSku);

        if (!$cin7Product) {

            $ticketName = !@$product->project
                ? $this->generateServiceTicketName($product->company->id, $product->ticket->id, $product->ticket->summary)
                : (@$product->ticket ? $this->generateProjectTicketName($product->project->id, $product->ticket->id, $product->ticket->summary, $product->phase->id ?? null) : null);

            $projectOrCompanyName = @$product->project
                ? $this->generateProjectName($product->project->id, $product->project->name, $product->company->name)
                : $this->generateCompanyName($product->company->id, $product->company->name);

            $cin7Product = $this->cin7Service->generateFamilyProduct(
                $productFamily->ID,
                $cin7ProductSku,
                $projectOrCompanyName,
                $this->generatePhaseName($product->project->id, $product->phase->id ?? null),
                $ticketName,
                $productFamily
            );
        }

        if ($cin7Product->Status == Cin7Service::PRODUCT_STATUS_DEPRECATED) {
            $this->cin7Service->updateProduct([
                'ID' => $cin7Product->ID,
                'Status' => Cin7Service::PRODUCT_STATUS_ACTIVE
            ]);
        }

        $adjustment = $this->cin7Service->stockAdd($cin7Product->ID, $quantity, adjustmentId: $cin7AdjustmentId);

        if ($onBigCommerceAsWell) {
            $this->publishVariantOnBigCommerce($product, $quantity, $catalogItem);
        }

        return $adjustment;
    }

    public function publishProductOnBigCommerce($catalogItemId, $catalogItem=null)
    {
        $catalogItem = $catalogItem ?: $this->getCatalogItem($catalogItemId);

        $cin7ProductFamilySku = $this->generateProductFamilySku($catalogItem->identifier);

        $bigCommerceProduct = $this->bigCommerceService->getProductBySku($cin7ProductFamilySku);

        if (!$bigCommerceProduct) {
            $bigCommerceCategory = $this->bigCommerceService->getCategoryByNameOrCreate($catalogItem->category->name);

            $bigCommerceProduct = $this->bigCommerceService->createProduct(
                $this->generateProductFamilySku($catalogItem->identifier),
                $catalogItem->description,
                $catalogItem->customerDescription,
                [$bigCommerceCategory->category_id],
                0,
                0
            );
        }

        if (!$bigCommerceProduct->is_visible) {
            $this->bigCommerceService->updateProduct($bigCommerceProduct->id, [
                'is_visible' => true
            ]);
        }

        return $bigCommerceProduct;
    }

    public function publishVariantOnBigCommerce(\stdClass $product, $quantity, \stdClass $catalogItem=null)
    {
        $catalogItem = $catalogItem ?: $this->getCatalogItem($product->catalogItem->id);

        $bigCommerceProduct = $this->publishProductOnBigCommerce($catalogItem->id, $catalogItem);

        $cin7ProductSku = $this->generateProductSku(
            $bigCommerceProduct->sku,
            $product->project->id ?? null,
            $product->ticket->id ?? null,
            $product->company->id ?? null
        );

        $bigCommerceProductVariant = $this->bigCommerceService->getProductVariantBySku($bigCommerceProduct->id, $cin7ProductSku);

        if (!$bigCommerceProductVariant) {
            $bigCommerceProductVariant = $this->bigCommerceService->createProductVariantProject(
                $bigCommerceProduct->id,
                $cin7ProductSku,
                @$product->project ? $this->generateProjectName($product->project->id, $product->project->name, $product->company->name) : null,
                @$product->phase ? $this->generatePhaseName($product->project->id, $product->phase->id) : null,
                @$product->project && @$product->ticket
                    ? $this->generateProjectTicketName($product->project->id, $product->ticket->id, $product->ticket->summary, $product->phase->id ?? null)
                    : null,
                !@$product->project ? $this->generateCompanyName($product->company->id, $product->company->id) : null,
                !@$product->project ? $this->generateServiceTicketName($product->company->id, $product->ticket->id, $product->ticket->summary) : null,
            );
        }

        $this->bigCommerceService->adjustVariant($bigCommerceProductVariant->id, $quantity);
    }

    public function stockTakeOnBigCommerce($variantSku, $quantity)
    {
        $bigCommerceProduct = $this->bigCommerceService->getProductBySku($this->generateProductFamilySku(Str::before($variantSku, '-PROJECT')));

        if (!$bigCommerceProduct) {
            return false;
        }

        $bigCommerceProductVariant = $this->bigCommerceService->getProductVariantBySku($bigCommerceProduct->id, $variantSku);

        $this->bigCommerceService->adjustVariant($bigCommerceProductVariant->id, $quantity * -1);

        return true;
    }

    public function generateProductSku($cin7ProductFamilySku, $projectId, $ticketId, $companyId=null)
    {
        return $cin7ProductFamilySku . ($projectId ? "-{$projectId}" : ($companyId ? "-C-{$companyId}" : "")) . ($ticketId ? "-T-{$ticketId}" : "");
    }

    public function generateProductFamilySku($catalogItemIdentifier)
    {
        return Str::upper($catalogItemIdentifier) . '-PROJECT';
    }

    public function generateProjectName($projectId, $projectName, $companyName=null)
    {
        $companyName = $companyName ?: $this->getProject($projectId)->company->name;

        return "#{$projectId} - {$projectName} ({$companyName})";
    }

    public function generateCompanyName($companyId, $companyName)
    {
        return "#{$companyId} - {$companyName}";
    }

    public function generateProductName($description, $identifier)
    {
        if (Str::endsWith($identifier, '-RF')) {
            return "[REFURBISHED] {$description}";
        }

        if (Str::endsWith($identifier, 'ft)')) {
            $length = '(' . array_reverse(explode('(', $identifier))[0];

            return "[USED] {$description} {$length}";
        }

        return $description;
    }

    public function generatePhaseName($projectId, $phaseId, \stdClass $phase=null)
    {
        $phase = $phase ?: ($phaseId ? $this->getProjectPhase($projectId, $phaseId) : null);

        return $phaseId ? ("#{$projectId}: #{$phase->id} - {$phase->description}" . (@$phase->parentPhase ? ": {$phase->parentPhase->name}" : "")) : null;
    }

    public function generateProjectTicketName($projectId, $ticketId, $ticketSummary, $phaseId=null)
    {
        return "#{$projectId}: " . ($phaseId ? "#{$phaseId}: " : "") . "#{$ticketId} - {$ticketSummary}";
    }

    public function generateServiceTicketName($companyId, $ticketId, $ticketSummary)
    {
        return "#{$companyId}: #{$ticketId} - {$ticketSummary}";
    }

    public function getProductsByTicketInfo(\stdClass $ticket)
    {
        $conditions = "catalogItem/identifier='{$ticket->Item_ID}' and cancelledFlag=false";

        if ($ticket->SR_Service_RecID) {
            $conditions .= " and ticket/id={$ticket->SR_Service_RecID}";
        } elseif ($ticket->PM_Project_RecID) {
            $conditions .= " and project/id={$ticket->PM_Project_RecID}";
        } else {
            $conditions .= " and salesOrder/id={$ticket->Order_Header_RecID}";
        }

        return $this->getProducts(null, $conditions, 1000);
    }

    public function getProductsBy($identifier, $ticketId, $projectId, $salesOrderId=null)
    {
        $conditions = "catalogItem/identifier='{$identifier}' and cancelledFlag=false";

        if ($ticketId) {
            $conditions .= " and ticket/id={$ticketId}";
        } elseif ($projectId) {
            $conditions .= " and project/id={$projectId}";
        } else {
            $conditions .= " and salesOrder/id={$salesOrderId}";
        }

        return $this->getProducts(null, $conditions, 1000);
    }

    public function getProjectPhase($projectId, $phaseId)
    {
        $response = $this->http->get("project/projects/{$projectId}/phases/{$phaseId}", [
            'query' => [
                'clientId' => $this->clientId
            ],
        ]);

        return json_decode($response->getBody()->getContents());
    }

    public function getProjectPhases($projectId, $fields=null)
    {
        $response = $this->http->get("project/projects/{$projectId}/phases", [
            'query' => [
                'clientId' => $this->clientId,
                'pageSize' => 1000,
                'fields' => $fields
            ],
        ]);

        return json_decode($response->getBody()->getContents());
    }

    public function extractCustomFieldValueByName(\stdClass $record, $fieldName)
    {
        $field = collect($record->customFields)->where('caption', $fieldName)->first();

        return $field->value ?? null;
    }

    public function extractBigCommerceOptionId(\stdClass $record)
    {
        return $this->extractCustomFieldValueByName($record, 'BigCommerce Option ID');
    }

    public function extractBigCommerceModifierId(\stdClass $record)
    {
        return $this->extractCustomFieldValueByName($record, 'BigCommerce Modifier ID');
    }

    public function setBigCommerceModifierId(\stdClass $record, string|int $value)
    {
        return $this->setCustomFieldValue($record, 'BigCommerce Modifier ID', $value);
    }

    public function setBigCommerceOptionId(\stdClass $record, string $value)
    {
        return $this->setCustomFieldValue($record, 'BigCommerce Option ID', $value);
    }

    public function setCustomFieldValue(\stdClass $record, string $fieldCaption, string $value)
    {
        $customFields = collect($record->customFields);

        $customField = $customFields->where('caption', $fieldCaption)->first();

        $customFields = $customFields->where('caption', '!=', $fieldCaption);

        $customField->value = $value;

        $customFields->push($customField);

        $record->customFields = $customFields->sortBy('id')->values()->toArray();

        return $record;
    }

    public function cloneProduct(
        \stdClass $product,
        $ticketId=null,
        $projectId=null,
        $phaseId=null,
        $companyId=null,
        $opportunityId=null,
        $salesOrderId=null,
        $quantity=1
    )
    {
        if (@$product->project) {
            unset($product->project);
        }
        if (@$product->ticket) {
            unset($product->ticket);
        }
        if (@$product->phase) {
            unset($product->phase);
        }
        if (@$product->company) {
            unset($product->company);
        }
        if (@$product->opportunity) {
            unset($product->opportunity);
        }
        if (@$product->salesOrder) {
            unset($product->salesOrder);
        }
        if (@$product->invoice) {
            unset($product->invoice);
        }

        $product->id = 0;
        $product->quantity = $quantity;

        if ($projectId) {
            $product->project = ['id' => $projectId];
        }

        if ($ticketId) {
            $product->ticket = ['id' => $ticketId];
        }

        if ($companyId) {
            $product->company = ['id' => $companyId];
        }

        if ($phaseId) {
            $product->phase = ['id' => $phaseId];
        }

        if ($opportunityId) {
            $product->opportunity = ['id' => $opportunityId];
        }

        if ($salesOrderId) {
            $product->salesOrder = ['id' => $salesOrderId];
        }

        try {
            $response = $this->createProductWithJson($product);
        } catch (GuzzleException $e) {
            $errContent = $e->getResponse()->getBody()->getContents();

            if (Str::contains($errContent, 'This opportunity is closed and cannot be edited')) {
                // Using internal api to create product

                return $this->createProductViaInternalApi(
                    $product->catalogItem->id,
                    $product->description,
                    $product->customerDescription,
                    $project->id ?? '',
                    $ticket->id ?? '',
                    $companyId,
                    $product->price,
                    $product->cost
                );
            }

            throw new \Exception($errContent);
        }

        return $response;
    }

    /**
     * @throws GuzzleException
     */
    public function createProductWithJson(array|\stdClass $json)
    {
        $response = $this->http->post("procurement/products?clientId={$this->clientId}", [
            'json' => $json
        ]);

        return json_decode($response->getBody()->getContents());
    }

    public function createProductViaInternalApi(
        int $catalogItemId,
        string $catalogItemDescription,
        string $catalogItemCustomerDescription,
        int $projectId=null,
        int $ticketId=null,
        $companyId=null,
        float $price=0,
        float $cost=0,
    )
    {
        $payload = [
            "model" => [
                "taxable_Flag" => true,
                "warehouse" => [
                    "id" => 1,
                    "name" => "Warehouse"
                ],
                "warehouseBin" => [
                    "id" => 1,
                    "name" => "Default+Bin"
                ],
                "billable_Options_RecID" => 1,
                "billing_Unit_RecID" => 11,
                "IV_Item_RecID" => $catalogItemId,
                "owner_Level_RecID" => 11,
                "PM_Project_RecID" => $projectId ?: '',
                "SR_Service_RecID" => $ticketId ?: '',
                "order_Header_RecID" => '', // SalesOrder not handled
                "warehouse_Bin_RecID" => 1,
                "warehouse_RecID" => 1,
                "discount_Amount" => 0,
                "list_Price" => $price,
                "quantity" => 1,
                "unit_Cost" => $cost,
                "unit_Price" => $price,
                "description" => $catalogItemCustomerDescription,
                "IV_Price_Method_ID" => "",
                "short_Description" => $catalogItemDescription,
                "purchase_Date" => time()
            ],
            "companyRecId" => $companyId ?: '',
            "productType" => $projectId ? "Project" : ($ticketId ? "Service" : "SalesOrder"),
            "userDefinedFieldValues" => []
        ];

        $response = $this->internalApiRequest(
            'actionprocessor/Procurement/AddProductsToPurchaseOrderAction.rails',
            $payload,
            'SaveProductDetailAction',
            'ProcurementCommon'
        );

        if (!$response->data->isSuccess) {
            throw new \Exception(json_encode($response->data->error));
        }

        return $this->getProduct($response->data->action->recId);
    }

    public function createProduct(
        \stdClass $catalogItem,
        \stdClass $ticket=null,
        \stdClass $project=null,
        \stdClass $phase=null,
        \stdClass $company=null,
        \stdClass $opportunity=null,
        $price=0,
        $cost=0,
        $quantity=1,
        $billable="Billable"
    )
    {
        $json = [
            "id" => 0,
            "catalogItem" => [
                "id" => $catalogItem->id,
                "identifier" => $catalogItem->identifier
            ],
            "description" => $catalogItem->customerDescription,
            "quantity" => $quantity,
            "price" => $price,
            "cost" => $cost,
            "billableOption" => $billable,
            "locationId" => 11,
            "location" => [
                "id" => 11,
                "name" => "Houston"
            ],
            "businessUnitId" => 2,
            "businessUnit" => [
                "id" => 2,
                "name" => "Sales"
            ],
            "taxableFlag" => true,
            "dropshipFlag" => false,
            "specialOrderFlag" => false,
            "phaseProductFlag" => false,
            "cancelledFlag" => false,
            "customerDescription" => $catalogItem->identifier,
            "internalNotes" => "",
            "productSuppliedFlag" => false,
            "subContractorAmountLimit" => 0,
            "ticket" => [
                "id" => $ticket->id ?? 0,
                "summary" => $ticket->summary ?? 'string'
            ],
            "project" => [
                "id" => $project->id ?? 0,
                "name" => $project->name ?? 'string'
            ],
            "phase" => [
                "id" => $phase->id ?? 0,
                "name" => $phase->name ?? 'string'
            ],
            "opportunity" => [
                "id" => $opportunity->id ?? 0,
                "name" => $opportunity->name ?? 0
            ],
            "warehouseId" => 1,
            "warehouseIdObject" => [
                "id" => 1,
                "name" => "Warehouse",
                "lockedFlag" => false
            ],
            "warehouseBinId" => 1,
            "warehouseBinIdObject" => [
                "id" => 1,
                "name" => "Default Bin"
            ],
            "calculatedPriceFlag" => false,
            "calculatedCostFlag" => false,
            "warehouse" => "Warehouse",
            "warehouseBin" => "Default Bin",
            "taxCode" => [
                "id" => 1,
                "name" => "Exempt"
            ],
            "company" => [
                "id" => $company->id,
                "identifier" => $company->identifier,
                "name" => $company->name
            ],
            "needToPurchaseFlag" => false,
            "minimumStockFlag" => false,
            "poApprovedFlag" => true
        ];

        try {
            $response = $this->createProductWithJson($json);
        } catch (GuzzleException $e) {
            $errContent = $e->getResponse()->getBody()->getContents();

            if (Str::contains($errContent, 'This opportunity is closed and cannot be edited')) {
                // Using internal api to create product

                return $this->createProductViaInternalApi(
                    $catalogItem->id,
                    $catalogItem->description,
                    $catalogItem->customerDescription,
                    $project->id ?? 0,
                    $ticket->id ?? 0,
                    $company->id,
                    $price,
                    $cost
                );
            }

            throw new \Exception($errContent);
        }

        return $response;
    }

    public function createProductComponent(int $bundleId, int $catalogItemId, int $quantity, float $price, float $cost)
    {
        $json = [
            "id" => 0,
            "quantity" => $quantity,
            "catalogItem" => [
                "id" => $catalogItemId
            ],
            "hidePriceFlag" => false,
            "hideItemIdentifierFlag" => false,
            "hideDescriptionFlag" => false,
            "hideQuantityFlag" => false,
            "hideExtendedPriceFlag" => false,
            "price" => $price,
            "cost" => $price
        ];

        $response = $this->http->post("procurement/products/{$bundleId}/components?clientId={$this->clientId}", [
            'json' => $json
        ]);

        return json_decode($response->getBody()->getContents());
    }

    public function createCatalogItem($identifier, $description, \stdClass $category, $cost, $price, string $uomName="Pcs", $productClass="Inventory", $type="1. Hardware", $customerDescription=null)
    {
        $json = [
            "id" => 0,
            "identifier" => $identifier,
            "description" => $description,
            "inactiveFlag" => false,
            "subcategory" => [
                "id" => 0,
                "name" => "Others"
            ],
            "type" => [
                "id" => 0,
                "name" => $type ?: "1. Hardware"
            ],
            "productClass" => $productClass ?: "Inventory",
            "serializedFlag" => false,
            "serializedCostFlag" => false,
            "phaseProductFlag" => false,
            "unitOfMeasure" => [
                "id" => 0,
                "name" => $uomName
            ],
            "minStockLevel" => 0,
            "price" => $price,
            "cost" => $cost,
            "taxableFlag" => true,
            "dropShipFlag" => false,
            "specialOrderFlag" => false,
            "customerDescription" => $customerDescription,
            "recurringFlag" => false,
            "recurringOneTimeFlag" => false,
            "calculatedPriceFlag" => false,
            "calculatedCostFlag" => false,
            "category" => [
                "id" => @$category->id ?: 0,
                "name" => $category->name
            ],
            "markupFlag" => false,
            "autoUpdateUnitCostFlag" => false,
            "autoUpdateUnitPriceFlag" => false
        ];

        $response = $this->http->post("procurement/catalog?clientId={$this->clientId}", [
            'json' => $json
        ]);

        return json_decode($response->getBody()->getContents());
    }

    public function getOpportunity($id)
    {
        $response = $this->http->get("sales/opportunities/{$id}?clientId={$this->clientId}");

        return json_decode($response->getBody()->getContents());
    }

    public function getInvoice($id)
    {
        $response = $this->http->get("finance/invoices/{$id}?clientId={$this->clientId}");

        return json_decode($response->getBody()->getContents());
    }

    public function createAzadMayPO(Collection $bcOrderItems, int $departmentId, string $cin7SalesOrderId, &$createdPO=null)
    {
        $products = $bcOrderItems->map(function ($orderProduct) {

            $options = collect($orderProduct->product_options);

            $projectId = $options->where('display_name', 'Project')->first()->value;
            $phaseId = $options->where('display_name', 'Phase')->first()->value;
            $projectTicketId = $options->where('display_name', 'Project Ticket')->first()->value;
            $companyId = $options->where('display_name', 'Company')->first()->value;
            $serviceTicketId = $options->where('display_name', 'Service Ticket')->first()->value;
            $bundleId = $options->where('display_name', 'Bundle')->first()->value;

            $project = $projectId ? $this->getProject($projectId) : null;
            $phase = $phaseId ? $this->getProjectPhase($projectId, $phaseId) : null;
            $company = $companyId ? $this->getCompany($companyId) : null;
            $bundle = $bundleId ? $this->getProduct($bundleId) : null;
            $ticket = $projectTicketId ? $this->getProjectTicket($projectTicketId)
                : ($serviceTicketId ? $this->getServiceTicket($serviceTicketId) : null);
            $cost = $orderProduct->base_price;
            $quantity = $orderProduct->quantity;

            $conditions = "";

            if ($project) {
                $conditions .= " and project/id={$project->id}";
            }

            if ($phase) {
                $conditions .= " and phase/id={$phase->id}";
            }

            if ($company) {
                $conditions .= " and company/id={$company->id}";
            }

            if ($ticket) {
                $conditions .= " and ticket/id={$ticket->id}";
            }

            $billed = (@$bundle->invoice && $this->getInvoice($bundle->invoice->id)->status->isClosed)
                || (($cwProducts = collect($this->getProducts(1, "cancelledFlag=false{$conditions}"))) && $cwProducts->filter(fn($cwProduct) => !@$cwProduct->invoice)->count() == 0);

            $catalogItem = $this->getCatalogItemByIdentifier($orderProduct->sku);

            if (!$catalogItem) {
                $product = $this->bigCommerceService->getProduct($orderProduct->product_id);

                $cin7Product = $this->cin7Service->productBySku($orderProduct->sku);

                $bgCategories = $this->bigCommerceService->getCategories(1, 100, categoryIdIn: implode(',', $product->categories));

                $category = $this->getCategories(1, 'name in ("' . collect($bgCategories)->pluck('name')->join('","') . '")')[0] ?? null;

                if (!$category) {
                    $category = $this->getCategories(1, 'name contains "Default Category"')[0];
                }

                $catalogItem = $this->createCatalogItem(
                    $product->sku,
                    $product->name,
                    $category,
                    $cost,
                    $cost,
                    $cin7Product->UOM,
                    $product->type == 'physical' ? 'Inventory' : 'Non-Inventory',
                    $product->type == 'physical' ? '1. Hardware' : '8. Other Charge',
                    (Str::numbers($category->name[0]) ? "{$category->name} - ": '') . $product->name
                );

            }

            if ($bundle && !$billed) {
                return $this->getProduct($this->createProductComponent($bundle->id, $catalogItem->id, $quantity, $cost, $cost)->productItem->id);
            }

            return $this->createProduct(
                $catalogItem,
                $ticket,
                $project,
                $phase,
                $company ?: $project->company,
                @$ticket->opportunity ?? @$project->opportunity,
                $cost,
                $cost,
                $quantity,
                $billed ? 'DoNotBill' : 'Billable'
            );
        });

        $purchaseOrder = $this->createPurchaseOrder(self::AZAD_MAY_ID, $departmentId);

        $this->updatePurchaseOrderCin7SalesOrderId($purchaseOrder, $cin7SalesOrderId);

        $projects = $products->filter(fn($product) => !!@$product->project)->unique('project.id');
        $serviceTickets = $products->filter(fn($product) => !@$product->project)->unique('ticket.id');

        $purchaseOrder->poNumber = $purchaseOrder->poNumber
            .= ($projects->count() > 0 ? ("-PROJECT-#" . $projects->pluck('project.id')->join('-#')) : "")
            . ($serviceTickets->count() > 0 ? ("-SERVICE-TICKET-#" . $serviceTickets->pluck('ticket.id')->join('-#')) : "")
        ;

        $this->updatePurchaseOrder($purchaseOrder);

        $this->addProductsToPurchaseOrder($purchaseOrder->id, $products);

        // Waiting 2 seconds to allow ConnectWise to process purchase order
        sleep(2);

        collect($this->purchaseOrderItemsOriginal($purchaseOrder->id))->map(function ($poItem) use ($purchaseOrder) {
            $this->purchaseOrderItemReceive($purchaseOrder->id, $poItem, $poItem->quantity);
        });


        $createdPO = $purchaseOrder;

        return $products;
    }

    public function addProductsToPurchaseOrder(int $purchaseOrderId, Collection $products) : array
    {
        return $products->map(function ($product) use ($purchaseOrderId) {

            $payload = [
                "purchaseHeaderRecID" => $purchaseOrderId,
                "demandProductList" => [[
                    "warehouseRecID" => 1,
                    "warehouseBinRecID" => 1,
                    "dropShipFlag" => false,
                    "specialOrderFlag" => false,
                    "currentCost" => $product->cost,
                    "customerAddressRecID" => 0,
                    "customerContactRecID" => 0,
                    "customerRecID" => 0,
                    "ivItemRecID" => $product->catalogItem->id,
                    "ivProductRecID" => $product->id,
                    "ivUomRecID" => $product->unitOfMeasure->id,
                    "ownerLevelRecID" => self::LOCATION_HOUSTON,
                    "purchasingQuantity" => $product->quantity,
                    "toOrderQuantity" => $product->quantity,
                    "description" => $product->customerDescription,
                    "internalNotes" => "",
                    "itemDescription" => $product->description,
                    "vendorSku" => ""
                ]]

            ];

            $response = $this->internalApiRequest(
                'actionprocessor/Procurement/AddProductsToPurchaseOrderAction.rails',
                $payload,
                'AddProductsToPurchaseOrderAction',
                'ProcurementCommon'
            );

            if (!$response->data->isSuccess) {
                throw new \Exception(json_encode($response->data->error));
            }

            return $response;
        })->toArray();
    }

    public function createPurchaseOrderFromProductsForSingleProjectOrServiceTicket(Collection $products, int $vendorId)
    {
        $product = $products->first();

        $payload = [
            "purchasingData" => [
                "vendorRecID" => $vendorId,
                "warehouseRecID" => 1,
                "demandProductList" => [
                    $products->map(function (\stdClass $product) {
                        return [
                            "warehouseBinRecID" => 1,
                            "warehouseRecID" => 1,
                            "dropShipFlag" => false,
                            "specialOrderFlag" => false,
                            "currentCost" => $product->cost,
                            "ivItemRecID" => $product->catalogItem->id,
                            "ivProductRecID" => $product->id,
                            "ivUomRecID" => 1,
                            "purchasingQuantity" => $product->quantity,
                            "toOrderQuantity" => $product->quantity,
                            "description" => $product->description,
                            "internalNotes" => "",
                            "itemDescription" => $product->description,
                            "vendorSku" => "",
                        ];
                    }),
                ],
            ]
        ];

        if (@$product->project) {
            $payload["fromProjectRecID"] = $product->project->id; // Project ID
        } else {
            $payload["fromSrServiceRecID"] = $product->ticket->id; // Ticket ID
        }

        $response = $this->http->post("{$this->systemIO}actionprocessor/Procurement/CreatePurchaseOrderWithProductsAction.rails?" . $this->payloadHandler($payload, "CreatePurchaseOrderWithProductsAction", "ProcurementCommon"));

        return $response->getBody()->getContents();
    }

    public function createPurchaseOrder(int $vendorId, int $departmentId)
    {

        $json = [
            'id' => 0,
            "businessUnitId" => $departmentId,
            "vendorCompany" => [
                "id" => $vendorId,
                "name" => ""
            ],
            "warehouse" => [
                "id" => 1,
                "name" => ""
            ],
            "locationId" => self::LOCATION_HOUSTON,
            "terms" => [
                "id" => 3
            ],
            "status" => [
                "id" => 1
            ]
        ];

        $response = $this->http->post("procurement/purchaseorders?clientId={$this->clientId}", [
            'json' => $json
        ]);

        return json_decode($response->getBody()->getContents());
    }

    public function updatePurchaseOrder(\stdClass $purchaseOrder)
    {
        $response = $this->http->put("procurement/purchaseorders/{$purchaseOrder->id}?clientId={$this->clientId}", [
            'json' => $purchaseOrder
        ]);

        return json_decode($response->getBody()->getContents());
    }

    /**
     * @param int $poId
     * @param \stdClass $poItem
     * @param $callback - params: $product, $quantity
     * @return false|void
     * @throws GuzzleException
     */
    public function pickOrShipPurchaseOrderItem(int $poId, \stdClass $poItem, $pick=true, $ship=false, $callback=null)
    {
        if (!$ship && !$pick) {
            return false;
        }

        $tickets = collect($this->getPurchaseOrderItemTicketInfo($poId, $poItem->id));

        if ($tickets->count() == 0) {
            return false;
        }

        $quantity = $poItem->quantity;

        $tickets->map(function ($ticket) use ($callback, $poId, $ship, $pick, &$quantity) {

            $products = collect($this->getProductsByTicketInfo($ticket));

            $ticketQuantity = min($ticket->Quantity, $quantity);

            $quantity -= $ticket->Quantity;

            return $products->map(function ($product) use ($pick, $ship, &$ticketQuantity, $poId, $ticket) {

                if ($ticketQuantity < 1) {
                    return false;
                }

                $productPoItems = collect($this->getProductPoItems($product->id))->where('ID', $poId);

                if ($productPoItems->count() == 0) {
                    return false;
                }

                $productPickAndShips = collect($this->getProductPickingShippingDetails($product->id));

                $pickedQuantity = $productPickAndShips->pluck('pickedQuantity')->sum();
                $shippedQuantity = $productPickAndShips->pluck('shippedQuantity')->sum();

                if ($pickedQuantity > 0 && $pickedQuantity == $shippedQuantity) {
                    return false;
                }

                $pickOrShipAvailableQuantity = $ship && !$pick ? $pickedQuantity - $shippedQuantity : min($product->quantity, $ticket->Quantity) - $pickedQuantity;

                if ($pickOrShipAvailableQuantity < 1) {
                    return false;
                }

                $result = [
                    'product' => $product,
                    'quantity' => min($ticketQuantity, $pickOrShipAvailableQuantity)
                ];

                $ticketQuantity = $ticketQuantity <= $pickOrShipAvailableQuantity ? 0 : $ticketQuantity - $pickOrShipAvailableQuantity;

                return $result;
            })
                ->filter(fn($results) => !!$results)
                ->map(function (array $result) use ($pick, $callback, $ship) {
                    if ($pick && $ship) {
                        $this->pickAndShipProduct($result['product']->id, $result['quantity']);
                    } elseif ($pick) {
                        $this->pickProduct($result['product']->id, $result['quantity']);
                    } else {
                        $this->shipProduct($result['product']->id, $result['quantity']);
                    }

                    if ($callback) {
                        $callback($result['product'], $result['quantity']);
                    }

                    return $result['product'];
                });
        });
    }

    public function deleteProduct($id)
    {
        $response = $this->http->delete("procurement/products/{$id}?clientId={$this->clientId}");
        return json_decode($response->getBody()->getContents());
    }

    public function stockTakeFromCin7ByProjectProductId(int $productId, int $quantity, bool $onBigCommerceAsWell=false, \stdClass $product=null)
    {
        $product = $product ?: $this->getProduct($productId);

        $sku = $this->generateProductSku(
            $this->generateProductFamilySku($product->catalogItem->identifier),
            $product->project->id ?? null,
            $product->ticket->id ?? null,
            $product->company->id
        );

        $cin7Product = $this->cin7Service->productBySku($sku);

        if ($cin7Product) {
            $this->cin7Service->stockAdd($cin7Product->ID, -1 * $quantity);
        }

        if ($onBigCommerceAsWell) {
            $this->stockTakeOnBigCommerce(
                $sku,
                $quantity
            );
        }

        return $product;
    }
}
