<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;

class Cin7Service
{
    const INVENTORY_AZAD_MAY = 'Azad May Inventory';
    const INVENTORY_BINYOD = 'Binyod Inventory';

    const PRODUCT_STATUS_ACTIVE = 'Active';
    const PRODUCT_STATUS_DEPRECATED = 'Deprecated';

    /* On Cin7 Core product family doesn't have status field and no possibility to delete via API.
     * So, this constant will be added to the beginning of the Name field value
    */
    const PRODUCT_FAMILY_INACTIVE = '[INACTIVE]';

    public bool $handleLimitation = false;

    private Client $http;
    public function __construct()
    {
        // Create a handler stack
        $stack = HandlerStack::create();

        if ($this->handleLimitation) {
            // Define the retry middleware
            $retryMiddleware = Middleware::retry(
                function ($retries, $request, $response, $exception) {
                    // Limit the number of retries to 5
                    if ($retries >= 5) {
                        return false;
                    }

                    // Retry on api limitation
                    if ($response && $response->getStatusCode() === 503 && Str::contains((string) $response->getBody(), 'You have reached 60 calls per 60 seconds API limit')) {
                        return true;
                    }

                    // Retry on connection exceptions
                    if ($exception instanceof RequestException && $exception->getCode() === 0) {
                        return true;
                    }

                    return false;
                },
                function () {
                    // Define a delay function (e.g., exponential backoff)
                    return 60000; // Delay in milliseconds
                }
            );

            // Add the retry middleware to the handler stack
            $stack->push($retryMiddleware);
        }

        $this->http = new Client([
            'headers' => [
                'api-auth-accountid' => config('cin7.account_id'),
                'api-auth-applicationkey' => config('cin7.api_key')
            ],
            'base_uri' => config('cin7.base_uri'),
            'handler' => $stack
        ]);
    }

    public function products($page=1, $limit=100)
    {
        try {
            $result = $this->http->get('product', [
                'query' => [
                    'Page' => $page,
                    'Limit' => $limit,
                ],
            ]);
        } catch (GuzzleException $e) {
            return new \stdClass();
        }
        return json_decode($result->getBody()->getContents());
    }

    public function productBySku($sku)
    {
        $result = $this->http->get('product', [
            'query' => [
                'Page' => 1,
                'Limit' => 1,
                'sku' => $sku
            ],
        ]);

        $products = collect(json_decode($result->getBody()->getContents())->Products);

        return $products->filter(fn($product) => Str::lower($product->SKU) == Str::lower($sku))->first();
    }

    public function product($id)
    {
        $result = $this->http->get('product', [
            'query' => [
                'ID' => $id
            ],
        ]);
        return json_decode($result->getBody()->getContents())->Products[0];
    }

    public function productFamilies($page=1, $limit=100, $sku=null)
    {
        $result = $this->http->get('productFamily', [
            'query' => [
                'Page' => $page,
                'Limit' => $limit,
                'SKU' => $sku
            ],
        ]);

        return json_decode($result->getBody()->getContents());
    }

    public function productFamily($id)
    {
        $result = $this->http->get('productFamily', [
            'query' => [
                'ID' => $id
            ],
        ]);

        return json_decode($result->getBody()->getContents())->ProductFamilies[0] ?? null;
    }

    public function productFamilyBySku($sku)
    {
        return $this->productFamilies(1, 1, $sku)->ProductFamilies[0] ?? null;
    }

    /**
     * @param \stdClass|array $product
     * @return \stdClass|null
     */
    public function updateProduct($product)
    {
        $result = $this->http->put('product', [
            'json' => $product,
        ]);
        return json_decode($result->getBody()->getContents());
    }

    public function updateProductFamily($productFamily)
    {
        $result = $this->http->put('productFamily', [
            'json' => $productFamily,
        ]);
        return json_decode($result->getBody()->getContents());
    }

    public function createCategory($name)
    {
        $result = $this->http->post('ref/category', [
            'json' => [
                'Name' => $name
            ],
        ]);

        return json_decode($result->getBody()->getContents());
    }

    public function createUnitOfMeasure($name)
    {
        $result = $this->http->post('ref/unit', [
            'json' => [
                'Name' => $name
            ],
        ]);

        return json_decode($result->getBody()->getContents());
    }

    /**
     * @throws GuzzleException
     */
    public function createProductFamily($sku, $name, $categoryName, $uomName, $customerDescription, $price=0, $products=[], $defaultLocation=self::INVENTORY_AZAD_MAY)
    {
        // Handling duplicated names
        $i = 0;
        while (true) {
            try {
                $result = $this->http->post('productFamily', [
                    'json' => [
                        "SKU" => $sku,
                        "Name" => $name . ($i ? "[{$i}]" : ""),
                        "Category" => $categoryName,
                        "DefaultLocation" => $defaultLocation,
                        "UOM" => $uomName,
                        "ShortDescription" => $name,
                        "Description" => $customerDescription,
                        "Option1Name" => "Project/Company",
                        "Option2Name" => "Phase",
                        "Option3Name" => "Ticket",
                        "CostingMethod" => "FIFO",
                        "PriceTier1" => $price,
                        "Products" => $products
                    ],
                ]);

                break;
            } catch (GuzzleException $e) {

                $i++;

                $errBody = $e->getResponse()->getBody()->getContents();

                if (Str::contains($errBody, "Specified attribute 'SKU' already exists")) {
                    return $this->productFamilyBySku($sku);
                }

                if (!Str::contains($errBody, "Product family with specified 'Name' already exists")) {
                    throw $e;
                }
            }
        }

        return json_decode($result->getBody()->getContents())->ProductFamilies[0];
    }

    public function createProduct($sku, $name, $categoryName, $uomName, $description, $price, $weight=null, $barcode=null, $defaultLocation=self::INVENTORY_AZAD_MAY, $price2=null)
    {

        $result = $this->http->post('product', [
            'json' => [
                "SKU" => $sku,
                "Name" => $name,
                "Category" => $categoryName,
                "DefaultLocation" => $defaultLocation,
                "UOM" => $uomName,
                "ShortDescription" => $name,
                "Description" => $description,
                "CostingMethod" => "FIFO",
                "PriceTier1" => $price,
                "PriceTier2" => $price2,
                "Type" => "Stock",
                "WeightUnits" => "lb",
                "Weight" => $weight,
                "Barcode" => $barcode,
                "Status" => "Active"
            ],
        ]);
        return json_decode($result->getBody()->getContents())->Products[0];
    }

    public function cloneProduct($product, $newSKU)
    {
        $product = clone $product;

        $product->SKU = $newSKU;

        $result = $this->http->post('product', [
            'json' => $product,
        ]);

        $newProduct = json_decode($result->getBody()->getContents())->Products[0];

        $attachments = $this->productAttachments($product->ID);

        array_map(function ($attachment) use ($newProduct) {
            $this->uploadProductAttachment($newProduct->ID, $attachment->FileName, base64_encode(file_get_contents($attachment->DownloadUrl)));
        }, $attachments);

        return $newProduct;
    }

    public function productAttachments($productId)
    {
        $result = $this->http->get('product/attachments', [
            'query' => [
                'ProductID' => $productId
            ]
        ]);

        return json_decode($result->getBody()->getContents());
    }

    public function productFamilyAttachments($productFamilyId)
    {
        $result = $this->http->get('productFamily/attachments', [
            'query' => [
                'FamilyID' => $productFamilyId
            ]
        ]);

        return json_decode($result->getBody()->getContents());
    }

    public function deleteProductFamilyAttachment($attachmentId)
    {
        $this->http->delete('productFamily/attachments', [
            'json' => [
                'ID' => $attachmentId
            ]
        ]);

        return true;
    }

    public function deleteProductAttachment($attachmentId)
    {
        $this->http->delete('product/attachments', [
            'json' => [
                'ID' => $attachmentId
            ]
        ]);

        return true;
    }


    public function unitOfMeasures($page=1, $limit=100)
    {
        try {
            $result = $this->http->get('ref/unit', [
                'query' => [
                    'Page' => $page,
                    'Limit' => $limit,
                ],
            ]);
        } catch (GuzzleException $e) {
            return new \stdClass();
        }
        return json_decode($result->getBody()->getContents());
    }

    public function unitOfMeasure($uomScheduleXref)
    {
        $unitOfMeasures = collect($this->unitOfMeasures()->UnitList);

        return $unitOfMeasures->filter(function ($q) use ($uomScheduleXref) {
            return Str::startsWith($q->ID, $uomScheduleXref);
        })->first();
    }

    public function uploadProductAttachment($id, $fileName, $base64, $isDefault=false)
    {
        $result = $this->http->post('product/attachments', [
            'json' => [
                "ProductID" => $id,
                "FileName" => $fileName,
                "Content" => $base64,
                "IsDefault" => $isDefault
            ]
        ]);
        return json_decode($result->getBody()->getContents());
    }

    public function uploadProductFamilyAttachment($id, $fileName, $base64, $isDefault=false)
    {
        $result = $this->http->post('productFamily/attachments', [
            'json' => [
                "FamilyID" => $id,
                "FileName" => $fileName,
                "Content" => $base64,
                "IsDefault" => $isDefault
            ]
        ]);
        return json_decode($result->getBody()->getContents());
    }

    public function productAvailabilities($productId=null, $page=1, $limit=100)
    {
        $result = $this->http->get('ref/productavailability', [
            'query' => [
                'Page' => $page,
                'Limit' => $limit,
                'ID' => $productId
            ],
        ]);

        return json_decode($result->getBody()->getContents());
    }

    public function productAvailabilityBySku($sku)
    {
        $result = $this->http->get('ref/productavailability', [
            'query' => [
                'Page' => 1,
                'Limit' => 1,
                'Sku' => $sku
            ],
        ]);

        return json_decode($result->getBody()->getContents())->ProductAvailabilityList[0] ?? null;
    }

    public function undoStockAdjustment($id)
    {
        $this->http->delete('stockadjustment', [
            'json' => [
                "ID" => $id
            ]
        ]);
    }

    public function updateStockAdjustment(\stdClass $stockAdjustment)
    {
        $this->http->put('stockadjustment', [
            'json' => $stockAdjustment
        ]);
    }

    public function getStockAdjustment($id)
    {
        $response = $this->http->get('stockadjustment', [
            'query' => [
                "TaskID" => $id
            ]
        ]);

        return json_decode($response->getBody()->getContents());
    }

    public function stockAdjust($productId, $quantity, $inventory=self::INVENTORY_AZAD_MAY, $cost=0.0001, $adjustmentId=null)
    {
        $json = [
            "EffectiveDate" => date("Y-m-d"),
            "Status" => "COMPLETED",
            "Account" => "255",
            "Reference" => "",
            "Lines" => [
                [
                    "ProductID" => $productId,
                    "Quantity" => $quantity,
                    "UnitCost" => $cost,
                    "Location" => $inventory
                ]
            ]
        ];

        if ($adjustmentId) {
            $json['TaskID'] = $adjustmentId;

            $result = $this->http->put('stockadjustment', [
                'json' => $json
            ]);
        } else {
            $result = $this->http->post('stockadjustment', [
                'json' => $json
            ]);
        }

        //

        return json_decode($result->getBody()->getContents());
    }

    public function productAvailability($productId)
    {
        return $this->productAvailabilities($productId)->ProductAvailabilityList[0] ?? null;
    }

    public function stockAdd($productId, $quantity, $inventory=self::INVENTORY_AZAD_MAY, $cost=0.0001, $adjustmentId=null)
    {
        $available = $this->productAvailability($productId);

        $quantity += ($available && $available->OnHand ? $available->OnHand : 0);

        return $this->stockAdjust($productId, $quantity, $inventory, $cost, $adjustmentId);
    }

    public function convertProductToAdjustmentLine($productId, $quantity, $inventory=self::INVENTORY_AZAD_MAY, $cost=0.0001)
    {
        return [
            "ProductID" => $productId,
            "Quantity" => $quantity,
            "UnitCost" => $cost,
            "Location" => $inventory
        ];
    }

    public function stockAdjustBulk($lines)
    {
        $result = $this->http->post('stockadjustment', [
            'json' => [
                "EffectiveDate" => date('Y-m-d'),
                "StocktakeNumber" => "ST-00001",
                "Status" => "COMPLETED",
                "Account" => "255",
                "Reference" => "",
                "Lines" => $lines
            ]
        ]);
    }

    public function purchaseList($page=1, $limit=100)
    {
        try {
            $result = $this->http->get('purchaseList', [
                'query' => [
                    'Page' => $page,
                    'Limit' => $limit,
                ],
            ]);
        } catch (GuzzleException $e) {
            return new \stdClass();
        }
        return json_decode($result->getBody()->getContents());
    }

    public function purchaseOrder($purchaseId)
    {
        try {
            $result = $this->http->get('purchase/order', [
                'query' => [
                    'TaskID' => $purchaseId
                ],
            ]);
        } catch (GuzzleException $e) {
            return new \stdClass();
        }
        return json_decode($result->getBody()->getContents());
    }

    public function generateFamilyProduct($familyId, $newProductSKU, $newProductProjectOrCompanyName, $newProductPhaseName=null, $newProductTicketName=null, \stdClass $productFamily=null)
    {
        $pf = $productFamily ?: $this->productFamily($familyId);

        $product = $this->productBySku($newProductSKU);

        if ($product) {
            return $product;
        }

        $product = $this->createProduct(
            $newProductSKU,
            $pf->Name,
            $pf->Category,
            $pf->UOM,
            $pf->Description,
            $pf->PriceTier1 ?: 0
        );

        $this->updateProductFamily([
            'ID' => $pf->ID,
            'Products' => [
                [
                    'ID' => $product->ID,
                    'SKU' => $product->SKU,
                    'Option1' => $newProductProjectOrCompanyName,
                    'Option2' => $newProductPhaseName,
                    'Option3' => $newProductTicketName
                ]
            ]
        ]);

        return $product;
    }

    public function salesOrder($saleId)
    {
        $result = $this->http->get('sale/order', [
            'query' => [
                'SaleID' => $saleId
            ],
        ]);

        return json_decode($result->getBody()->getContents());
    }

    public function sale($saleId)
    {
        $result = $this->http->get('sale', [
            'query' => [
                'ID' => $saleId
            ],
        ]);

        return json_decode($result->getBody()->getContents());
    }

    public function createSale($customerName, $customerReference=null)
    {
        $result = $this->http->post('sale', [
            'json' => [
                'Customer' => $customerName,
                'Location' => self::INVENTORY_AZAD_MAY,
                'CustomerReference' => $customerReference,
                'SkipQuote' => true,
                'Carrier' => 'Pick up'
            ]
        ]);

        return json_decode($result->getBody()->getContents());
    }

    public function updateSale(array|\stdClass $sale)
    {
        $result = $this->http->put('sale', [
            'json' => $sale
        ]);

        return json_decode($result->getBody()->getContents());
    }

    public function webhooks()
    {
        $result = $this->http->get('webhooks');

        return json_decode($result->getBody()->getContents());
    }

    public function createSalesQuote($saleId, array $purchaseOrderItems, string $memo=null)
    {
        try {
            $result = $this->http->post('sale/quote', [
                'json' => [
                    'SaleID' => $saleId,
                    'Memo' => $memo,
                    'Status' => 'AUTHORISED',
                    'CombineAdditionalCharges' => false,
                    'Lines' => array_map(function (\stdClass $poItem) {

                        $product = $this->productBySku($poItem->product->identifier);

                        return [
                            'ProductID' => $product->ID,
                            'Quantity' => $poItem->quantity,
                            'TaxRule' => 'Tax Exempt',
                            'Price' => $product->PriceTier1,
                            'Total' => round($product->PriceTier1 * $poItem->quantity, 2)
                        ];
                    }, $purchaseOrderItems)
                ],
            ]);
        } catch (GuzzleException $e) {
            dd($e->getResponse()->getBody()->getContents());
        }

        return json_decode($result->getBody()->getContents());
    }

    public function createSalesOrder($saleId, array $purchaseOrderItems, string $memo=null, bool $autoship=false)
    {
        try {
            $result = $this->http->post('sale/order', [
                'json' => [
                    'SaleID' => $saleId,
                    'Memo' => $memo,
                    'Status' => 'AUTHORISED',
                    'CombineAdditionalCharges' => false,
                    'AutoPickPackShipMode' => $autoship ? "AUTOPICKPACKSHIP" : "AUTOPICK",
                    'Lines' => array_map(function (\stdClass $poItem) {

                        $product = $this->productBySku($poItem->product->identifier);

                        return [
                            'ProductID' => $product->ID,
                            'Quantity' => $poItem->quantity,
                            'TaxRule' => 'Tax Exempt',
                            'Price' => $product->PriceTier1,
                            'Total' => round($product->PriceTier1 * $poItem->quantity, 2)
                        ];
                    }, $purchaseOrderItems)
                ],
            ]);
        } catch (GuzzleException $e) {
            dd($e->getResponse()->getBody()->getContents());
        }

        return json_decode($result->getBody()->getContents());
    }

    public function customer($name)
    {
        $result = $this->http->get('customer', [
            'query' => [
                'Name' => $name
            ]
        ]);

        return json_decode($result->getBody()->getContents())->CustomerList[0] ?? null;
    }

    public function createCustomer($name)
    {
        $result = $this->http->post('customer', [
            'json' => [
                'Name' => $name,
                'Status' => 'Active',
                'Currency' => 'USD',
                'PaymentTerm' => '15 days',
                'AccountReceivable' => '120',
                'RevenueAccount' => '400',
                'TaxRule' => 'Tax Exempt'
            ]
        ]);

        return json_decode($result->getBody()->getContents())->CustomerList[0] ?? null;
    }

    public function convertProductToPurchaseOrderLine($cwProduct, $quantity, $isCatalogItem=false, $doNotCharge=false)
    {
        $sku = $isCatalogItem ? $cwProduct->identifier : $cwProduct->catalogItem->identifier;

        $product = $this->productBySku($sku);

        if (!$product) {

            $connectWiseService = new ConnectWiseService();

            $catalogItem = $isCatalogItem ? $cwProduct : $connectWiseService->getCatalogItem($cwProduct->catalogItem->id);

            $product = $this->createProduct(
                $sku,
                $connectWiseService->generateProductName($catalogItem->description, $catalogItem->identifier),
                $catalogItem->category->name,
                $catalogItem->unitOfMeasure->name,
                $catalogItem->customerDescription,
                $cwProduct->cost
            );

            $connectWiseService->syncCatalogItemAttachmentsWithCin7($catalogItem->id, $product->ID, isProductFamily: false);
        }

        $cost = $doNotCharge ? 0.01 : $cwProduct->cost * 0.93;

        return [
            'ProductID' => $product->ID,
            'Name' => $product->Name,
            'Quantity' => $quantity,
            'Price' => $cost,
            'TaxRule' => 'Tax Exempt',
            'Total' => round($cost * $quantity, 2),
            'Received' => true
        ];
    }

    public function createPurchaseOrder(array $lineProducts, $supplierId, $memo='Purchase Order')
    {
        $purchase = $this->createPurchase($supplierId);

        $result = $this->http->post('purchase/order', [
            'json' => [
                'Memo' => $memo,
                'Status' => 'AUTHORISED',
                'Lines' => $lineProducts,
                'TaskID' => $purchase->ID,
                'CombineAdditionalCharges' => false
            ]
        ]);

        return json_decode($result->getBody()->getContents());
    }

    public function createPurchase($supplierId)
    {
        $result = $this->http->post('advanced-purchase', [
            'json' => [
                'SupplierID' => $supplierId,
                'Approach' => 'STOCK',
                'Location' => self::INVENTORY_AZAD_MAY,
                'TaxRule' => 'Tax Exempt'
            ]
        ]);

        return json_decode($result->getBody()->getContents());
    }

    public function receivePurchaseOrderItems($purchaseOrderId, array $lineProducts)
    {
        $result = $this->http->post('purchase/stock', [
            'json' => [
                'TaskID' => $purchaseOrderId,
                'Lines' => $lineProducts,
                'Status' => 'AUTHORISED'
            ]
        ]);

        return json_decode($result->getBody()->getContents());
    }

    public function suppliers()
    {
        $result = $this->http->get('supplier');

        return json_decode($result->getBody()->getContents());
    }
}
