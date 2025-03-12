<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;

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

    private Client $http;
    public function __construct()
    {
        $this->http = new Client([
            'headers' => [
                'api-auth-accountid' => config('cin7.account_id'),
                'api-auth-applicationkey' => config('cin7.api_key')
            ],
            'base_uri' => config('cin7.base_uri'),
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
        try {
            $result = $this->http->get('product', [
                'query' => [
                    'Page' => 1,
                    'Limit' => 1,
                    'sku' => $sku
                ],
            ]);
        } catch (GuzzleException $e) {
            return new \stdClass();
        }
        return json_decode($result->getBody()->getContents())->Products[0] ?? null;
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
        try {
            $result = $this->http->get('productFamily', [
                'query' => [
                    'Page' => $page,
                    'Limit' => $limit,
                    'Sku' => $sku
                ],
            ]);
        } catch (GuzzleException $e) {
            return new \stdClass();
        }
        return json_decode($result->getBody()->getContents());
    }

    public function productFamily($id)
    {
        try {
            $result = $this->http->get('productFamily', [
                'query' => [
                    'ID' => $id
                ],
            ]);
        } catch (GuzzleException $e) {
            return new \stdClass();
        }
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

    public function uploadProductAttachment($id, $fileName, $base64)
    {

        $result = $this->http->post('product/attachments', [
            'json' => [
                "ProductID" => $id,
                "FileName" => $fileName,
                "Content" => $base64
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

    public function undoStockAdjustment($id)
    {
        $this->http->delete('stockadjustment', [
            'json' => [
                "ID" => $id
            ]
        ]);
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

        $quantity += $available && $available->onHand ? $available->onHand : 0;

        return $this->stockAdjust($productId, $quantity, $inventory, $cost, $adjustmentId);
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

    public function saleOrder($saleId)
    {
        $result = $this->http->get('sale/order', [
            'query' => [
                'SaleID' => $saleId
            ],
        ]);

        return json_decode($result->getBody()->getContents());
    }
}
