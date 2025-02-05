<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;

class Cin7Service
{
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

    public function product($id)
    {
        try {
            $result = $this->http->get('product', [
                'query' => [
                    'ID' => $id
                ],
            ]);
        } catch (GuzzleException $e) {
            return new \stdClass();
        }
        return json_decode($result->getBody()->getContents());
    }

    public function productFamilies($page=1, $limit=100)
    {
        try {
            $result = $this->http->get('productFamily', [
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

    public function updateProduct(\stdClass $product)
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

    public function createUnitOfMeasures($name)
    {
        $result = $this->http->post('ref/unit', [
            'json' => [
                'Name' => $name
            ],
        ]);

        return json_decode($result->getBody()->getContents());
    }

    public function createProductFamily($sku, $name, $categoryName, $uomName, $customerDescription, $price, $products=[], $option2=null)
    {

        $result = $this->http->post('productFamily', [
            'json' => [
                "SKU" => $sku,
                "Name" => $name,
                "Category" => $categoryName,
                "DefaultLocation" => "Azad May Inventory",
                "UOM" => $uomName,
                "ShortDescription" => $name,
                "Description" => $customerDescription,
                "Option1Name" => "Project",
                "Option2Name" => $option2,
                "CostingMethod" => "FIFO",
                "PriceTier1" => $price,
                "Products" => $products
            ],
        ]);
        return json_decode($result->getBody()->getContents());
    }

    public function createProduct($sku, $name, $categoryName, $uomName, $description, $price, $weight, $barcode)
    {

        $result = $this->http->post('product', [
            'json' => [
                "SKU" => $sku,
                "Name" => $name,
                "Category" => $categoryName,
                "DefaultLocation" => "Azad May Inventory",
                "UOM" => $uomName,
                "ShortDescription" => $name,
                "Description" => $description,
                "CostingMethod" => "FIFO",
                "PriceTier1" => $price,
                "Type" => "Stock",
                "WeightUnits" => "lb",
                "Weight" => $weight,
                "Barcode" => $barcode,
                "Status" => "Active"
            ],
        ]);
        return json_decode($result->getBody()->getContents());
    }

    public function cloneProduct($product, $newSKU)
    {
        $product = clone $product;

        $product->SKU = $newSKU;

        $result = $this->http->post('product', [
            'json' => $product,
        ]);
        return json_decode($result->getBody()->getContents());
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

    public function stockAdjust($lines=[])
    {
        $result = $this->http->post('stockadjustment', [
            'json' => [
                "EffectiveDate" => date('Y-m-d'),
                "StocktakeNumber" => "ST-00001",
                "Status" => "COMPLETED",
                "Account" => "2550",
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
}
