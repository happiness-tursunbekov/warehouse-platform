<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;

class BigCommerceService
{
    const NO_PHASE_LABEL = 'No phase';

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

    public function createProductVariantProject($productId, $variantSku, $optionProjectLabel, $optionPhaseLabel=null)
    {
        $optionPhaseLabel = $optionPhaseLabel ?: self::NO_PHASE_LABEL;

        $productOptions = $this->getProductOptions($productId);

        $optionProject = $this->getProductOptionOrModifierProject($productOptions);
        $optionPhase = $this->getProductOptionOrModifierPhase($productOptions);

        $request = $this->http->post("catalog/products/{$productId}/variants", [
            'json' => [
                "sku" => $variantSku,
                "option_values" => [
                    [
                        "option_id" => $optionProject->id,
                        "id" => $this->getSharedValueByTitle($optionProject, $optionProjectLabel)->id
                    ],
                    [
                        "option_id" => $optionPhase->id,
                        "id" => $this->getSharedValueByTitle($optionPhase, $optionPhaseLabel)->id
                    ],
                ]
            ]
        ]);

        return json_decode($request->getBody()->getContents())->data;
    }

    public function getSharedValueByTitle(\stdClass $sharedOptionOrModifier, string $title)
    {
        $values = $sharedOptionOrModifier->values ?? $sharedOptionOrModifier->option_values;
        return collect($values)->filter(fn($value) => Str::contains(Str::lower($value->label), Str::lower($title)))->first();
    }

    public function getSharedValueById(\stdClass $sharedOptionOrModifier, int $valueId)
    {
        $values = $sharedOptionOrModifier->values ?? $sharedOptionOrModifier->option_values;
        return collect($values)->filter(fn($value) => $value->id == $valueId)->first();
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
        return json_decode($result->getBody()->getContents())->data;
    }

    public function getProductOptionOrModifierProject(array $productOptions)
    {
        return collect($productOptions)->filter(fn($value) => $value->name == 'Project')->first();
    }

    public function getProductOptionOrModifierPhase(array $productOptions)
    {
        return collect($productOptions)->filter(fn($value) => $value->name == 'Phase')->first();
    }

    public function updateProductOption($productId, \stdClass $option)
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

    public function createProduct(string $sku, string $name, string $description, array $categories, float $price, float $cost, string $barcode='')
    {
        $i = 0;
        while (true) {
            try {
                $request = $this->http->post('catalog/products', [
                    'json' => [
                        "name" => $name . ($i ? "[{$i}]" : ""),
                        "description" => $description,
                        "categories" => $categories,
                        "price" => $price,
                        "cost" => $cost,
                        "upc" => $barcode,
                        "sku" => $sku,
                        "type" => 'physical',
                        "inventory_tracking" => 'variant',
                        'weight' => 0
                    ]
                ]);

                break;
            } catch (GuzzleException $e) {

                if (!Str::contains($e->getMessage(), 'The product name is a duplicate')) {
                    throw $e;
                }

                $i++;
            }
        }

        $product = json_decode($request->getBody()->getContents())->data;

        // Adding shared values to the product
        $this->addProductSharedOption($product->id, $this->getSharedOptionProject());
        $this->addProductSharedOption($product->id, $this->getSharedOptionPhase());

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

    public function addProductSharedOption($productId, $sharedOption)
    {
        $optionValues = [new \stdClass()];

        $optionValues[0]->id = 0;

        $request = $this->http->post("catalog/products/{$productId}/options", [
            'json' => [
                "type" => $sharedOption->type,
                "display_name" => $sharedOption->name,
                "shared_option_id" => $sharedOption->id,
                "required" => false,
                "config" => new \stdClass(),
                "option_values" => $optionValues
            ]
        ]);

        return json_decode($request->getBody()->getContents())->data;
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

    public function deleteProductImage($productId, $imageId)
    {
        $this->http->delete("catalog/products/{$productId}/images/{$imageId}");
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
        $request = $this->http->post("inventory/adjustments/relative", [
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

    public function getOrder($id)
    {
        $response = $this->httpV2->get("orders/{$id}");

        return json_decode($response->getBody()->getContents());
    }

    public function getOrderProducts($id)
    {
        $response = $this->httpV2->get("orders/{$id}/products");

        return json_decode($response->getBody()->getContents());
    }

    public function getSharedModifierByName($name)
    {
        $cachedSharedModifier = cache()->get('bc-cachedSharedModifier-' . $name);

        if ($cachedSharedModifier) {
            return $cachedSharedModifier;
        }

        $request = $this->http->get("catalog/shared-modifiers", [
            'query' => [
                'name' => $name
            ]
        ]);

        $sharedModifier = json_decode($request->getBody()->getContents())->data[0];

        cache()->put('bc-cachedSharedModifier-' . $name, $sharedModifier, now()->addMinute());

        return $sharedModifier;
    }

    public function getSharedOptionByName($name)
    {
        $cachedSharedOption = cache()->get('bc-cachedSharedOption-' . $name);

        if ($cachedSharedOption) {
            return $cachedSharedOption;
        }

        $request = $this->http->get("catalog/shared-product-options", [
            'query' => [
                'name' => $name
            ]
        ]);

        $sharedOption = json_decode($request->getBody()->getContents())->data[0];

        cache()->put('bc-cachedSharedOption-' . $name, $sharedOption, now()->addMinute());

        return $sharedOption;
    }

    public function getSharedModifierProject()
    {
        return $this->getSharedModifierByName('Project');
    }

    public function getSharedOptionProject()
    {
        return $this->getSharedOptionByName('Project');
    }

    public function getSharedModifierCompany()
    {
        return $this->getSharedModifierByName('Company');
    }

    public function getSharedOptionCompany()
    {
        return $this->getSharedOptionByName('Company');
    }

    public function getSharedModifierPhase()
    {
        return $this->getSharedModifierByName('Phase');
    }

    public function getSharedOptionPhase()
    {
        return $this->getSharedOptionByName('Phase');
    }

    public function getSharedModifierServiceTicket()
    {
        return $this->getSharedModifierByName('Service Ticket');
    }

    public function getSharedOptionServiceTicket()
    {
        return $this->getSharedOptionByName('Service Ticket');
    }

    public function getSharedModifierProjectTicket()
    {
        return $this->getSharedModifierByName('Project Ticket');
    }

    public function getSharedOptionProjectTicket()
    {
        return $this->getSharedOptionByName('Project Ticket');
    }

    public function addSharedModifierValueIfNotExists(\stdClass $modifier, string $value)
    {
        $sharedModifierValue = $this->getSharedValueByTitle($modifier, $value);

        if (!$sharedModifierValue) {
            $sharedModifierValue = $this->addSharedModifierValue($modifier, $value);
        }

        return $sharedModifierValue;
    }

    public function addSharedOptionValueIfNotExists(\stdClass $option, string $value)
    {
        $sharedOptionValue = $this->getSharedValueByTitle($option, $value);

        if (!$sharedOptionValue) {
            $sharedOptionValue = $this->addSharedOptionValue($option, $value);
        }

        return $sharedOptionValue;
    }

    public function addSharedOptionValue(\stdClass $option, string $value)
    {
        $request = $this->http->post("catalog/shared-product-options/{$option->id}/values", [
            'json' => [
                [
                    "is_default" => false,
                    "label" => $value,
                    "sort_order" => 0
                ]
            ]
        ]);

        $valueItem = json_decode($request->getBody()->getContents())->data[0];

        $cachedSharedOption = cache()->get('bc-cachedSharedOption-' . $option->name);

        if ($cachedSharedOption) {
            $cachedSharedOption->values[] = $valueItem;

            cache()->put('bc-cachedSharedOption-' . $cachedSharedOption->name, $cachedSharedOption, now()->addMinute());
        }

        return $valueItem;
    }

    public function updateSharedOptionValue(\stdClass $option, \stdClass $value)
    {
        $request = $this->http->put("catalog/shared-product-options/{$option->id}/values", [
            'json' => [$value]
        ]);

        $valueItem = json_decode($request->getBody()->getContents())->data[0];

        $cachedSharedOption = cache()->get('bc-cachedSharedOption-' . $option->name);

        if ($cachedSharedOption) {
            $cachedSharedOption->values = array_filter($cachedSharedOption->values, fn($val) => $val->id != $value->id);
            $cachedSharedOption->values[] = $valueItem;

            cache()->put('bc-cachedSharedOption-' . $cachedSharedOption->name, $cachedSharedOption, now()->addMinute());
        }

        return $valueItem;
    }

    public function updateSharedModifierValue(\stdClass $modifier, \stdClass $value)
    {
        $request = $this->http->put("catalog/shared-modifiers/{$modifier->id}/values", [
            'json' => [$value]
        ]);

        $valueItem = json_decode($request->getBody()->getContents())->data[0];

        $cachedSharedModifier = cache()->get('bc-cachedSharedModifier-' . $modifier->name);

        if ($cachedSharedModifier) {
            $cachedSharedModifier->values = array_filter($cachedSharedModifier->values, fn($val) => $val->id != $value->id);
            $cachedSharedModifier->values[] = $valueItem;

            cache()->put('bc-cachedSharedModifier-' . $cachedSharedModifier->name, $cachedSharedModifier, now()->addMinute());
        }

        return $valueItem;
    }

    public function addSharedModifierValue(\stdClass $modifier, string $value)
    {
        $request = $this->http->post("catalog/shared-modifiers/{$modifier->id}/values", [
            'json' => [
                [
                    "is_default" => false,
                    "label" => $value,
                    "sort_order" => 0
                ]
            ]
        ]);

        $valueItem = json_decode($request->getBody()->getContents())->data[0];

        $cachedSharedModifier = cache()->get('bc-cachedSharedModifier-' . $modifier->name);

        if ($cachedSharedModifier) {
            $cachedSharedModifier->values[] = $valueItem;

            cache()->put('bc-cachedSharedModifier-' . $cachedSharedModifier->name, $cachedSharedModifier, now()->addMinute());
        }

        return $valueItem;
    }

    public function removeSharedModifierValue(int $modifierId, int $valueId)
    {
        $this->http->delete("catalog/shared-modifiers/{$modifierId}/values?id:in={$valueId}");
    }

    public function removeSharedOptionValue(int $optionId, int $valueId)
    {
        $this->http->delete("catalog/shared-product-options/{$optionId}/values?id:in={$valueId}");
    }
}
