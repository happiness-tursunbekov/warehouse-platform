<?php

namespace App\Console\Commands;

use App\Services\BigCommerceService;
use App\Services\Cin7Service;
use App\Services\ConnectWiseService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class FixUsedCables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-used-cables';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(Cin7Service $cin7Service, ConnectWiseService $connectWiseService, BigCommerceService $bigCommerceService)
    {
//        dd($cin7Service->purchaseOrder());
    }
}

//        collect($bigCommerceService->getProducts(1, 250)->data)->map(function ($product) use ($bigCommerceService) {
//            $value = $bigCommerceService->getProductOptions($product->id)->data[0];
//
//            $value->option_values[0]->is_default = true;
//
//            $bigCommerceService->updateProductOptions($product->id, $value);
//        });

//$f = $cin7Service->productFamilies()->ProductFamilies;
//sleep(1);
//collect($f)->map(function ($pf) use ($cin7Service) {
//    if (count($pf->Products) > 1) {
//        return false;
//    }
//
//    $p = $pf->Products[0];
//
//    $product = $cin7Service->product($p->ID)->Products[0];
//    sleep(1);
//
//    $newProduct = $cin7Service->cloneProduct($product, $product->SKU . "-test")->Products[0];
//    sleep(1);
//
//    $cin7Service->updateProductFamily([
//        'ID' => $pf->ID,
//        'Products' => [[
//            'ID' => $newProduct->ID,
//            'Option1' => 'Test'
//        ]]
//    ]);
//    sleep(1);
//});


//$cacheProducts = collect(cache()->get('bc-products'));
//
//collect($bigCommerceService->getProducts(1, 250)->data)->map(function ($product) use ($bigCommerceService, $cacheProducts) {
//    $product->sku = Str::replace('~', '', $product->sku);
//    $cacheProduct = $cacheProducts->where('sku', $product->sku)->first();
//    if (!$cacheProduct) {
//        $cacheProduct = $cacheProducts->filter(function ($item) use ($product) {
//            return false !== stripos($item->sku, $product->sku);
//        })->first();
//        if (!$cacheProduct) {
//            $cacheProduct = $cacheProducts->filter(function ($item) use ($product) {
//                return false !== stripos($item->sku, 'D6UP');
//            })->first();
//        }
//    }
//
//    if ($cacheProduct) {
//        $bigCommerceService->setProductCategories($product->id, $cacheProduct->categories);
//    }
//});


//$f = $cin7Service->productFamilies()->ProductFamilies;
//
//sleep(1);
//
//collect($f)->map(function ($pf) use ($cin7Service) {
//    collect($pf->Products)->map(function ($p) use ($cin7Service, $pf) {
//        $product = $cin7Service->product($p->ID)->Products[0];
//        sleep(1);
//
//        $product->Category = $pf->Category;
//
//        $cin7Service->updateProduct($product);
//        sleep(1);
//    });
//});


//        $products = [];
//        $item = $bigCommerceService->getProduct(4157)->data;
//
//        $cwItem = $connectWiseService->getCatalogItems(1, "identifier='STXC6-CCA-WP'")[0];
//
//        $product = $cin7Service->createProduct(
//            $item->sku,
//            $item->name,
//            $cwItem->category->name,
//            $cwItem->unitOfMeasure->name,
//            $item->description,
//            $item->price,
//            $item->weight,
//            $item->upc ?: null
//        )->Products[0];
//
//        collect($bigCommerceService->getProductImages($item->id)->data)->map(function ($image) use ($product, $cin7Service) {
//            $ext = explode('.', $image->image_file);
//
//            $cin7Service->uploadProductAttachment(
//                $product->ID,
//                time() . $image->id . '.' . $ext[count($ext) - 1],
//                base64_encode(file_get_contents($image->url_zoom))
//            );
//        });

//        $cin7Service->stockAdjust([
//            [
//                "ProductID" => $product->ID,
//                "SKU" => $product->SKU,
//                "ProductName" => $product->Name,
//                "Quantity" => $item->inventory_level,
//                "UnitCost" => $item->price,
//                "Location" => "Azad May Inventory"
//            ]
//        ]);

//        $connectWiseService->updateCatalogItemCin7ProductId($cwItem, $product->ID);


//        $products[] = [
//            "ID" => $product->ID,
//            "SKU" => $product->SKU,
//            "Name" => $product->Name,
//            "Option1" => 'Azad May',
//            "Option2" => 'White'
//        ];
//
//        $item = $bigCommerceService->getProduct(1158)->data;
//
//        $cwItem = $connectWiseService->getCatalogItems(1, "identifier='STXC6-CCA-BP'")[0];
//
//        $product = $cin7Service->createProduct(
//            $item->sku,
//            $item->name,
//            $cwItem->category->name,
//            $cwItem->unitOfMeasure->name,
//            $item->description,
//            $item->price,
//            $item->weight,
//            $item->upc ?: null
//        )->Products[0];
//
//        collect($bigCommerceService->getProductImages($item->id)->data)->map(function ($image) use ($product, $cin7Service) {
//            $ext = explode('.', $image->image_file);
//
//            $cin7Service->uploadProductAttachment(
//                $product->ID,
//                time() . $image->id . '.' . $ext[count($ext) - 1],
//                base64_encode(file_get_contents($image->url_zoom))
//            );
//        });

//        $cin7Service->stockAdjust([
//            [
//                "ProductID" => $product->ID,
//                "SKU" => $product->SKU,
//                "ProductName" => $product->Name,
//                "Quantity" => $item->inventory_level,
//                "UnitCost" => $item->price,
//                "Location" => "Azad May Inventory"
//            ]
//        ]);

//        $connectWiseService->updateCatalogItemCin7ProductId($cwItem, $product->ID);
//
//        $products[] = [
//            "ID" => $product->ID,
//            "SKU" => $product->SKU,
//            "Name" => $product->Name,
//            "Option1" => 'Azad May',
//            "Option2" => 'Blue'
//        ];
//
//        $cin7Service->createProductFamily(
//            'STXC6-CCA',
//            $item->name,
//            $cwItem->category->name,
//            $cwItem->unitOfMeasure->name,
//            $item->description,
//            $item->price,
//            $products,
//            "Color"
//        );



//        $page = 1;
//        collect($connectWiseService->getCatalogItems($page, 'inactiveFlag=false and identifier="V-9022A-2"', null, null,1000))
//            ->map(function ($item) use ($cin7Service, $connectWiseService) {
//                $productFamily = $cin7Service->createProductFamily(
//                    $item->identifier,
//                    $item->description,
//                    $item->category->name,
//                    $item->unitOfMeasure->name,
//                    $item->customerDescription
//                );
//
//                $connectWiseService->updateCatalogItemCin7ProductFamilyId($item, $productFamily->ProductFamilies[0]->ID);
//            });

//        $adjustmentItems = collect();
//        $page = 1;
//        collect($bigCommerceService->getProducts($page, 250)->data)
//            ->map(function ($item) use ($cin7Service, $connectWiseService, $bigCommerceService, $adjustmentItems) {
//
//                $swIdentifier = Str::replace('*', '', Str::replace('STX', '', Str::replace('STX-', '', $item->sku)));
//
//                try {
//                    $cwItem = $connectWiseService->getCatalogItems(1, "identifier='{$swIdentifier}'")[0];
//                } catch (\Exception $e) {
//                    echo $item->sku . " - error\n";
//                    return false;
//                }
//
//                echo $item->sku . " - passed\n";
//
//                $variants = collect($bigCommerceService->getProductVariants($item->id, 1, 250)->data);
//
//                $products = $variants->map(function ($variant) use ($connectWiseService, $adjustmentItems, $item, $cwItem, $cin7Service, $bigCommerceService) {
//                    $name = $item->name;
//
//                    if (count($variant->option_values) > 0) {
//                        $name .= ', ' . $variant->option_values[0]->label;
//                    }
//
//                    $product = $cin7Service->createProduct(
//                        $variant->sku,
//                        $name,
//                        $cwItem->category->name,
//                        $cwItem->unitOfMeasure->name,
//                        $item->description,
//                        $variant->price,
//                        $variant->weight,
//                        $variant->upc ?: null
//                    )->Products[0];
//
//                    collect($bigCommerceService->getProductImages($item->id)->data)->map(function ($image) use ($product, $cin7Service) {
//                        $ext = explode('.', $image->image_file);
//
//                        $cin7Service->uploadProductAttachment(
//                            $product->ID,
//                            time() . $image->id . '.' . $ext[count($ext) - 1],
//                            base64_encode(file_get_contents($image->url_zoom))
//                        );
//                    });
//
//                    $adjustmentItems->push([
//                        "ProductID" => $product->ID,
//                        "SKU" => $product->SKU,
//                        "ProductName" => $product->Name,
//                        "Quantity" => $variant->inventory_level,
//                        "UnitCost" => $variant->price,
//                        "Location" => "Azad May Inventory"
//                    ]);
//
//                    cache()->put('adjustment-items', $adjustmentItems);
//
//                    $connectWiseService->updateCatalogItemCin7ProductId($cwItem, $product->ID);
//
//                    return [
//                        "ID" => $product->ID,
//                        "SKU" => $product->SKU,
//                        "Name" => $product->Name,
//                        "Option1" => 'Azad May',
//                        "Option2" => count($variant->option_values) > 0 ? $variant->option_values[0]->label : null
//                    ];
//                })->toArray();
//
//                $cin7Service->createProductFamily(
//                    $swIdentifier,
//                    $item->name,
//                    $cwItem->category->name,
//                    $cwItem->unitOfMeasure->name,
//                    $item->description,
//                    $item->price,
//                    $products,
//                    $variants->count() > 1 ? "Color" : null
//                );
//            });


//        /** @var Collection $adjustmentItems */
//        $adjustmentItems = cache()->get('adjustment-items');
//
//        $cin7Service->stockAdjust($adjustmentItems->toArray());
