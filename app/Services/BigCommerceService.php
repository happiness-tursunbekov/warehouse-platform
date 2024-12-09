<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class BigCommerceService
{
    private Client $http;
    public function __construct()
    {
        $this->http = new Client([
            'headers' => [
                'X-Auth-Token' => config('bc.token')
            ],
            'base_uri' => config('bc.base_uri'),
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

    public function getCategories($page=null, $limit=null)
    {
        try {
            $result = $this->http->get('catalog/categories', [
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
}
