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
                'X-Auth-Token' => config('bc.token')
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
                    'limit' => $limit
                ],
            ]);
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
//        try {
            $result = $this->http->get('catalog/categories?channel_id:in=1', [
                'query' => [
                    'page' => $page,
                    'limit' => $limit,
                    'parent_id:in' => $parent_id
                ],
            ]);
//        } catch (GuzzleException $e) {
//            return [];
//        }
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

    public function getCustomerGroups()
    {
//        try {
            $result = $this->httpV2->get('customer_groups');
//        } catch (\Exception $e) {
//            return  [];
//        }

        return json_decode($result->getBody()->getContents());
    }
}
