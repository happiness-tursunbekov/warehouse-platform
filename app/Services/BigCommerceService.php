<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;

class BigCommerceService
{
    private Client $http;
    private Client $httpV2;
    public function __construct()
    {
        $this->http = new Client([
            'headers' => [
                'X-Auth-Token' => config('bc.token')
            ],
            'base_uri' => config('bc.base_uri'),
        ]);

        $this->httpV2 = new Client([
            'headers' => [
                'X-Auth-Token' => config('bc.token'),
                'Accept' => 'application/json'
            ],
            'base_uri' => Str::replace('/v3/', '/v2/', config('bc.base_uri')),
        ]);
    }

    public function getProducts($page=null, $limit=null)
    {
        try {
            $result = $this->http->get('catalog/products', [
                'query' => [
                    'page' => $page,
                    'limit' => $limit,
                    'channel_id:in' => 1
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
            $result = $this->http->get('catalog/products/' . $id);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($result->getBody()->getContents());
    }

    public function getProductImages($id)
    {
        try {
            $result = $this->http->get("catalog/products/{$id}/images");
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($result->getBody()->getContents());
    }

    public function getCategories($page=null, $limit=null, $parent_id=null)
    {
        try {
        $result = $this->http->get('catalog/categories?channel_id:in=1', [
            'query' => [
                'page' => $page,
                'limit' => $limit,
                'parent_id:in' => $parent_id
            ],
        ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($result->getBody()->getContents());
    }

    public function createCategories(array $values)
    {
        $result = $this->http->post('catalog/trees/categories?channel_id:in=1', [
            'json' => $values,
        ]);

        return json_decode($result->getBody()->getContents());
    }

    public function createCustomerGroup(array $attributes)
    {
        $result = $this->httpV2->post('customer_groups', [
            'json' => $attributes,
        ]);

        return json_decode($result->getBody()->getContents());
    }

    public function getCustomerGroups($page=1, $limit=250)
    {
        try {
            $result = $this->httpV2->get('customer_groups', [
                'query' => [
                    'page' => $page,
                    'limit' => $limit
                ]
            ]);
        } catch (\Exception $e) {
            return  [];
        }

        return json_decode($result->getBody()->getContents());
    }

    public function createProduct(string $sku, string $name, string $description, array $categories, float $price, float $cost, string $barcode='', float $weight=1)
    {

            $request = $this->http->post('catalog/products', [
                'json' => [
                    "name" => $name,
                    "description" => $description,
                    "categories" => $categories,
                    "price" => $price,
                    "cost" => $cost,
                    "weight" => $weight,
                    "upc" => $barcode,
                    "sku" => Str::upper($sku),
                    "type" => 'physical'
                ]
            ]);


            $product = json_decode($request->getBody()->getContents());

            $this->http->put('catalog/products/channel-assignments', [
                'json' => [
                    [
                        "product_id" => $product->data->id,
                        "channel_id" => 1
                    ]
                ]
            ]);

        return $product;
    }

    public function uploadProductImage($productId, $file, $filename)
    {
        $this->http->post("catalog/products/{$productId}/images", [
            'multipart' => [
                [
                    'name' => 'image_file',
                    'contents' => $file,
                    'filename' => $filename
                ]
            ]
        ]);
    }

    public function adjust($productId, $qty)
    {

        try {

            $product = $this->getProduct($productId);

            $request = $this->http->put("inventory/adjustments/absolute", [
                'json' => [
                    "reason" => "Initial count",
                    "items" => [
                        [
                            "location_id" => 1,
                            "variant_id" => $product->data->base_variant_id,
                            "quantity" => $qty
                        ]
                    ]
                ]
            ]);
        } catch (GuzzleException $e) {
            print_r(json_decode($e->getResponse()->getBody()->getContents()));
            die();
        }

        return json_decode($request->getBody()->getContents());
    }

    public function updateProduct($productId, array $attributes)
    {
        $this->http->put("catalog/products/{$productId}", [
            'json' => $attributes
        ]);
    }

    public function deleteProduct($productId)
    {
        $this->http->delete("catalog/products/{$productId}");
    }
}
