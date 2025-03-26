<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;

class BigCommerceService
{
    const NO_PROJECT_LABEL = 'No project';
    const NO_PHASE_LABEL = 'No phase';
    const NO_TICKET_LABEL = 'No ticket';
    const NO_COMPANY_LABEL = 'No company';

    const PRODUCT_OPTION_PROJECT = 'Project';
    const PRODUCT_OPTION_COMPANY = 'Company';
    const PRODUCT_OPTION_PHASE = 'Phase';
    const PRODUCT_OPTION_PROJECT_TICKET = 'Project Ticket';
    const PRODUCT_OPTION_SERVICE_TICKET = 'Service Ticket';
    const PRODUCT_OPTION_BUNDLE = 'Bundle';

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

    public function createProductVariantProject(
        $productId,
        $variantSku,
        $optionProjectLabel=null,
        $optionPhaseLabel=null,
        $optionProjectTicketLabel=null,
        $optionCompanyLabel=null,
        $optionServiceTicketLabel=null
    )
    {
        $productOptions = $this->getProductOptions($productId);

        $optionProject = $this->getProductOptionOrModifierByName($productOptions, self::PRODUCT_OPTION_PROJECT);
        $optionPhase = $this->getProductOptionOrModifierByName($productOptions, self::PRODUCT_OPTION_PHASE);
        $optionProjectTicket = $this->getProductOptionOrModifierByName($productOptions, self::PRODUCT_OPTION_PROJECT_TICKET);
        $optionCompany = $this->getProductOptionOrModifierByName($productOptions, self::PRODUCT_OPTION_COMPANY);
        $optionServiceTicket = $this->getProductOptionOrModifierByName($productOptions, self::PRODUCT_OPTION_SERVICE_TICKET);

        $optionProjectValue = $this->getOrCreateProductOptionValueByLabel($optionProject, $optionProjectLabel ?: self::NO_PROJECT_LABEL);
        $optionPhaseValue = $this->getOrCreateProductOptionValueByLabel($optionPhase, $optionPhaseLabel ?: self::NO_PHASE_LABEL);
        $optionProjectTicketValue = $this->getOrCreateProductOptionValueByLabel($optionProjectTicket, $optionProjectTicketLabel ?: self::NO_TICKET_LABEL);
        $optionCompanyValue = $this->getOrCreateProductOptionValueByLabel($optionCompany, $optionCompanyLabel ?: self::NO_COMPANY_LABEL);
        $optionServiceTicketValue = $this->getOrCreateProductOptionValueByLabel($optionServiceTicket, $optionServiceTicketLabel ?: self::NO_TICKET_LABEL);

        $request = $this->http->post("catalog/products/{$productId}/variants", [
            'json' => [
                "sku" => $variantSku,
                "option_values" => [
                    [
                        "option_id" => $optionProject->id,
                        "id" => $optionProjectValue->id
                    ],
                    [
                        "option_id" => $optionPhase->id,
                        "id" => $optionPhaseValue->id
                    ],
                    [
                        "option_id" => $optionProjectTicket->id,
                        "id" => $optionProjectTicketValue->id
                    ],
                    [
                        "option_id" => $optionCompany->id,
                        "id" => $optionCompanyValue->id
                    ],
                    [
                        "option_id" => $optionServiceTicket->id,
                        "id" => $optionServiceTicketValue->id
                    ]
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

    public function getProductModifiers($productId, $page=null, $limit=null)
    {
        try {
            $result = $this->http->get("catalog/products/{$productId}/modifiers", [
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

    public function getProductOptionOrModifierByName(array $productOptions, string $name)
    {
        return collect($productOptions)->filter(fn($value) => $value->display_name == $name)->first();
    }

    public function getProductOptionValueOrModifierValueByLabel(\stdClass $optionOrModifier, string $label)
    {
        return collect($optionOrModifier->option_values)->filter(fn($value) => $value->label == $label)->first();
    }

    public function getOrCreateProductOptionValueByLabel(\stdClass $option, string $label)
    {
        $value = $this->getProductOptionValueOrModifierValueByLabel($option, $label);

        if (!$value) {
            $value = $this->createProductOptionValue($option->product_id, $option->id, $label);
        }
        return $value;
    }

    public function updateProductOption($productId, \stdClass $option)
    {
        $result = $this->http->put("catalog/products/{$productId}/options/{$option->id}", [
            'json' => $option,
        ]);
        return json_decode($result->getBody()->getContents());
    }

    public function updateProductModifier($productId, \stdClass|array $modifier)
    {
        $result = $this->http->put("catalog/products/{$productId}/modifiers/{$modifier->id}", [
            'json' => $modifier,
        ]);
        return json_decode($result->getBody()->getContents());
    }

    public function getProduct($id)
    {
        $result = $this->http->get('catalog/products/' . $id);

        return json_decode($result->getBody()->getContents())->data;
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

    public function getCategories($page=null, $limit=null, $parent_id=null, $name=null, string|int $categoryIdIn=null)
    {
        try {
        $result = $this->http->get('catalog/trees/categories?channel_id:in=1', [
            'query' => [
                'page' => $page,
                'limit' => $limit,
                'parent_id:in' => $parent_id,
                'category_id:in' => $categoryIdIn,
                'name' => $name
            ],
        ]);
        } catch (GuzzleException $e) {
            return [];
        }
        return json_decode($result->getBody()->getContents())->data ?? [];
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
                'parent_id' => 0
            ]
        ])->data[0];
    }

    public function createCategories(array $values)
    {
        try {
            $result = $this->http->post('catalog/trees/categories?channel_id:in=1', [
                'json' => $values,
            ]);
        } catch (\Exception $e) {
            dd($e->getResponse()->getBody()->getContents());
        }

        return json_decode($result->getBody()->getContents());
    }

    public function createCustomerGroup(string $name)
    {
        $result = $this->httpV2->post('customer_groups', [
            'json' => [
                'name' => $name,
                'is_default' => false,
                'is_group_for_guests' => false
            ],
        ]);

        return json_decode($result->getBody()->getContents());
    }

    public function getCustomerGroups($page=1, $limit=250, string $nameLike=null)
    {
        $result = $this->httpV2->get('customer_groups', [
            'query' => [
                'page' => $page,
                'limit' => $limit,
                'name:like' => $nameLike
            ]
        ]);

        return json_decode($result->getBody()->getContents());
    }

    public function getCustomerGroup($id)
    {
        $result = $this->httpV2->get("customer_groups/{$id}");

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

        $this->createProductOption($product->id, self::PRODUCT_OPTION_PROJECT, self::NO_PROJECT_LABEL);
        $this->createProductOption($product->id, self::PRODUCT_OPTION_PHASE, self::NO_PHASE_LABEL);
        $this->createProductOption($product->id, self::PRODUCT_OPTION_PROJECT_TICKET, self::NO_TICKET_LABEL);
        $this->createProductOption($product->id, self::PRODUCT_OPTION_COMPANY, self::NO_COMPANY_LABEL, true);
        $this->createProductOption($product->id, self::PRODUCT_OPTION_SERVICE_TICKET, self::NO_TICKET_LABEL, true);

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

    public function createProductOption($productId, $display_name, $initial_value_label, $isInitialValueDefault=false, $type="dropdown")
    {
        $request = $this->http->post("catalog/products/{$productId}/options", [
            'json' => [
                "type" => $type,
                "display_name" => $display_name,
                "option_values" => [
                    [
                        'label' => $initial_value_label,
                        'is_default' => $isInitialValueDefault
                    ]
                ]
            ]
        ]);

        return json_decode($request->getBody()->getContents())->data;
    }

    public function createProductOptionValue($productId, $optionId, $label, $isDefault=false)
    {
        $request = $this->http->post("catalog/products/{$productId}/options/{$optionId}/values", [
            'json' => [
                'is_default' => $isDefault,
                'label' => $label,
                'sort_order' => 0
            ]
        ]);

        return json_decode($request->getBody()->getContents())->data;
    }

    public function createProductModifier($productId, $display_name, $type="numbers_only_text", $initial_value_label=null, $sort_order=0)
    {
        $json = [
            "type" => $type,
            "display_name" => $display_name,
            "required" => false,
            "sort_order" => $sort_order
        ];

        if ($initial_value_label) {
            $json['option_values'] = [
                [
                    'label' => $initial_value_label
                ]
            ];
        }

        $request = $this->http->post("catalog/products/{$productId}/modifiers", [
            'json' => $json
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

    public function addProductSharedModifier($productId, $sharedModifier)
    {
        $optionValues = [new \stdClass()];

        $optionValues[0]->id = 0;

        $request = $this->http->post("catalog/products/{$productId}/modifiers", [
            'json' => [
                "type" => $sharedModifier->type,
                "display_name" => $sharedModifier->name,
                "shared_option_id" => $sharedModifier->id,
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
                        "variant_id" => $product->base_variant_id,
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

    public function setProductChannelsBulk(array $productIdsChannelIds)
    {
        $this->http->put("catalog/products/channel-assignments", [
            'json' => $productIdsChannelIds
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

    public function setProductCategoriesBulk(array $productIdsCategoryIds)
    {
        $this->http->put("catalog/products/category-assignments", [
            'json' => $productIdsCategoryIds
        ]);
    }

    public function getOrder($id)
    {
        try {
            $response = $this->httpV2->get("orders/{$id}");
        } catch (\Exception) {
            return null;
        }

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
                    "label" => $value
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
        $valueItem = $this->updateSharedModifierValues($modifier->id, [$value])[0];

        $cachedSharedModifier = cache()->get('bc-cachedSharedModifier-' . $modifier->name);

        if ($cachedSharedModifier) {
            $cachedSharedModifier->values = array_filter($cachedSharedModifier->values, fn($val) => $val->id != $value->id);
            $cachedSharedModifier->values[] = $valueItem;

            cache()->put('bc-cachedSharedModifier-' . $cachedSharedModifier->name, $cachedSharedModifier, now()->addMinute());
        }

        return $valueItem;
    }

    public function updateSharedModifierValues(int $modifierId, array $values)
    {
        $request = $this->http->put("catalog/shared-modifiers/{$modifierId}/values", [
            'json' => $values
        ]);

        return json_decode($request->getBody()->getContents())->data;
    }

    public function addSharedModifierValue(\stdClass $modifier, string $valueLabel)
    {
        $valueItem = $this->addSharedModifierValues($modifier->id, [$valueLabel])[0];

        $cachedSharedModifier = cache()->get('bc-cachedSharedModifier-' . $modifier->name);

        if ($cachedSharedModifier) {
            $cachedSharedModifier->values[] = $valueItem;

            cache()->put('bc-cachedSharedModifier-' . $cachedSharedModifier->name, $cachedSharedModifier, now()->addMinute());
        }

        return $valueItem;
    }

    public function addSharedModifierValues(int $modifierId, array $valueLabels) : array
    {
        $request = $this->http->post("catalog/shared-modifiers/{$modifierId}/values", [
            'json' => array_map(function ($label, $index) {
                return [
                    'label' => $label,
                    'sort_order' => $index+1
                ];
            }, $valueLabels, array_keys($valueLabels))
        ]);

        return json_decode($request->getBody()->getContents())->data;
    }

    public function removeSharedModifierValue(int $modifierId, int $valueId)
    {
        $this->http->delete("catalog/shared-modifiers/{$modifierId}/values?id:in={$valueId}");
    }

    public function removeSharedModifierValues(int $modifierId, array $valueIds)
    {
        $valuesStr = implode(',', $valueIds);

        $this->http->delete("catalog/shared-modifiers/{$modifierId}/values?id:in={$valuesStr}");
    }

    public function removeSharedOptionValue(int $optionId, int $valueId)
    {
        $this->http->delete("catalog/shared-product-options/{$optionId}/values?id:in={$valueId}");
    }

    public function getCustomers(array $query)
    {
        $response = $this->http->get("customers", [
            'query' => $query
        ]);

        return json_decode($response->getBody()->getContents());
    }

    public function getCustomer($id)
    {
        return $this->getCustomers(['id:in' => $id])->data[0] ?? null;
    }
}
