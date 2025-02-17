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

    public function getProducts($page=null, $limit=null, $sku=null)
    {
        try {
            $result = $this->http->get('catalog/products', [
                'query' => [
                    'page' => $page,
                    'limit' => $limit,
                    'sku' => $sku
                ],
            ]);
        } catch (GuzzleException $e) {
            return new \stdClass();
        }
        return json_decode($result->getBody()->getContents());
    }

    public function getProductVariants($id, $page=null, $limit=null, $sku=null)
    {
        try {
            $result = $this->http->get("catalog/products/{$id}/variants", [
                'query' => [
                    'page' => $page,
                    'limit' => $limit,
                    'sku' => $sku
                ],
            ]);
        } catch (GuzzleException $e) {
            return new \stdClass();
        }
        return json_decode($result->getBody()->getContents());
    }

    public function getProductVariantBySku($id, $sku)
    {
        return $this->getProductVariants($id, 1, 1, $sku)->data[0] ?? null;
    }

    public function createProductVariantProject($productId, $variantSku, $optionLabel)
    {
        $optionProject = $this->getProductOptionProject($productId);

        if (!$optionProject) {
            $optionProject = $this->createProductOptionProject($productId, [
                [
                    "label" => $optionLabel
                ]
            ]);
        }

        $request = $this->http->post("catalog/products/{$productId}/variants", [
            'json' => [
                "sku" => $variantSku,
                "option_values" => [
                    [
                        "option_id" => $optionProject->id,
                        "id" => $optionProject->option_values[0]->id
                    ]
                ]
            ]
        ]);

        return json_decode($request->getBody()->getContents())->data;
    }

    public function getProductOptions($productId, $page=null, $limit=null)
    {
        try {
            $result = $this->http->get("catalog/products/{$productId}/options", [
                'query' => [
                    'page' => $page,
                    'limit' => $limit
                ],
            ]);
        } catch (GuzzleException $e) {
            return new \stdClass();
        }
        return json_decode($result->getBody()->getContents());
    }

    public function updateProductOptions($productId, \stdClass $option)
    {
        $result = $this->http->put("catalog/products/{$productId}/options/{$option->id}", [
            'json' => $option,
        ]);
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

    public function getProductBySku($sku)
    {
        return $this->getProducts(1, 1, $sku)->data[0] ?? null;
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

    public function getCategories($page=null, $limit=null, $parent_id=null, $name=null)
    {
        try {
        $result = $this->http->get('catalog/categories?channel_id:in=1', [
            'query' => [
                'page' => $page,
                'limit' => $limit,
                'parent_id:in' => $parent_id,
                'name' => $name
            ],
        ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($result->getBody()->getContents());
    }

    public function getCategoryByName($name)
    {
        return $this->getCategories(1, 1, name: $name)->data[0] ?? null;
    }

    public function getCategoryByNameOrCreate($name)
    {
        $category = $this->getCategoryByName($name);

        if ($category) {
            return $category;
        }

        return $this->createCategories([
            [
                'name' => $name,
                'tree_id' => 1
            ]
        ])->data[0];
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

    public function createProduct(string $sku, string $name, string $description, array $categories, float $price, float $cost, string $barcode='', $isProject=true)
    {
        $request = $this->http->post('catalog/products', [
            'json' => [
                "name" => $name,
                "description" => $description,
                "categories" => $categories,
                "price" => $price,
                "cost" => $cost,
                "upc" => $barcode,
                "sku" => Str::upper($sku),
                "type" => 'physical',
                "inventory_tracking" => 'variant',
                'weight' => 0
            ]
        ]);

        $product = json_decode($request->getBody()->getContents())->data;

        $this->http->put('catalog/products/channel-assignments', [
            'json' => [
                [
                    "product_id" => $product->id,
                    "channel_id" => 1
                ]
            ]
        ]);

        return $product;
    }

    public function getProductOptionProject($productId)
    {
        return array_filter($this->getProductOptions($productId)->data, function ($option) {
            return Str::replace(' ', '', Str::lower($option->display_name)) == 'project&phase';
        })[0] ?? null;
    }

    public function createProductOption($productId, $display_name, array $option_values, $type="dropdown")
    {
        $request = $this->http->post("catalog/products/{$productId}/options", [
            'json' => [
                "type" => $type,
                "display_name" => $display_name,
                "option_values" => $option_values
            ]
        ]);

        return json_decode($request->getBody()->getContents())->data;
    }

    public function createProductOptionProject($productId, array $option_values)
    {
        return $this->createProductOption($productId, 'Project & Phase', $option_values);
    }

    public function uploadProductImage($productId, $file, $filename, $default=false)
    {
        $this->http->post("catalog/products/{$productId}/images", [
            'multipart' => [
                [
                    'name' => 'image_file',
                    'contents' => $file,
                    'filename' => $filename,
                    'is_thumbnail' => $default
                ]
            ]
        ]);
    }

    public function uploadProductImageUrl($productId, $url)
    {
        $this->http->post("catalog/products/{$productId}/images", [
            'json' => [
                'image_url' => $url,
                "is_thumbnail" => true
            ]
        ]);
    }

    public function updateProductImage(\stdClass $image)
    {
        $this->http->put("catalog/products/{$image->product_id}/images/{$image->id}", [
            'json' => $image
        ]);
    }

    public function adjustVariant($variantId, $qty)
    {
        $request = $this->http->put("inventory/adjustments/absolute", [
            'json' => [
                "reason" => "Initial count",
                "items" => [
                    [
                        "location_id" => 1,
                        "variant_id" => $variantId,
                        "quantity" => $qty
                    ]
                ]
            ]
        ]);

        return json_decode($request->getBody()->getContents());
    }

    public function adjust($productId, $qty)
    {
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

    public function setProductChannels($productId, array $channelIds)
    {
        $this->http->put("catalog/products/channel-assignments", [
            'json' => array_map(function ($channelId) use ($productId) {
                return [
                    'channel_id' => $channelId,
                    'product_id' => $productId
                ];
            }, $channelIds)
        ]);
    }

    public function deleteProductChannels($productId, $channelId)
    {
        $this->http->delete("catalog/products/channel-assignments", [
            'query' => [
                    'channel_id:in' => $channelId,
                    'product_id:in' => $productId
                ]
        ]);
    }

    public function setProductCategories($productId, array $categoryIds)
    {
        $this->http->put("catalog/products/category-assignments", [
            'json' => array_map(function ($categoryId) use ($productId) {
                return [
                    'category_id' => $categoryId,
                    'product_id' => $productId
                ];
            }, $categoryIds)
        ]);
    }
}
