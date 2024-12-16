<?php

namespace App\Console\Commands;

use App\Services\BigCommerceService;
use App\Services\ConnectWiseService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-categories';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(ConnectWiseService $connectWiseService, BigCommerceService $bigCommerceService)
    {
//        $categories = collect($connectWiseService->getCategories(null, 'inactiveFlag=false'));
//
//        $categoryValues = $categories->map(function ($category) use ($connectWiseService) {
//
//            return [
//                "name" => $category->name,
//                "url" => [
//                    "path" => "/{$category->id}/",
//                    "is_customized" => true
//                ],
//                "parent_id" => 0,
//                "tree_id" => 1,
//                "views" => 0,
//                "sort_order" => 1,
//                "page_title" => $category->name,
//                "layout_file" => "category.html",
//                "is_visible" => true,
//                "search_keywords" => "string",
//                "default_product_sort" => "use_store_settings"
//            ];
//        })->toArray();
//
//        if (count($bigCommerceService->createCategories($categoryValues)) == 0)
//            die('Could\'t create categories on BigCommerce');

//        $bcCategories = collect($bigCommerceService->getCategories()->data);
//
//
//        $result = $bcCategories->map(function ($category) use ($connectWiseService) {
//            $cwCat = $connectWiseService->getCategory(Str::trim($category->custom_url->url, '/'));
//
//            $cwCat->integrationXref = $category->id;
//
//            return $connectWiseService->updateCategory($cwCat);
//        });
//        print_r($result);

//        $categories = collect($connectWiseService->getSubcategories(null, 'inactiveFlag=false'));
//
//        $categoryValues = $categories->map(function ($category) use ($connectWiseService) {
//
//            $parent = $connectWiseService->getCategory($category->category->id);
//
//            return [
//                "name" => $category->name,
//                "url" => [
//                    "path" => "/{$parent->id}/{$category->id}/",
//                    "is_customized" => false
//                ],
//                "parent_id" => (int)Str::numbers($parent->integrationXref),
//                "is_visible" => true,
//            ];
//        })->toArray();

//        $bigCommerceService->createCategories($categoryValues);

//        $parent_ids = collect($bigCommerceService->getCategories()->data)->pluck('id')->join(',');
//
//        $bcCategories = collect($bigCommerceService->getCategories(null, null, $parent_ids)->data);
//
//
//        $result = $bcCategories->map(function ($category) use ($connectWiseService) {
//            $cwCat = $connectWiseService->getSubcategory(explode('/', Str::trim($category->custom_url->url, '/'))[1]);
//
//            $cwCat->integrationXref = $category->id;
//
//            return $connectWiseService->updateSubcategory($cwCat);
//        });
//        print_r($result);
    }
}
