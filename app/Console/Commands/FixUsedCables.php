<?php

namespace App\Console\Commands;

use App\Services\BigCommerceService;
use App\Services\Cin7Service;
use App\Services\ConnectWiseService;
use GuzzleHttp\Exception\GuzzleException;
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

    }
}

//$cwItem = $connectWiseService->getCatalogItems(1, "identifier='SF300-48PP-RF'")[0];
//
//dd($cin7Service->createProduct(
//    $cwItem->identifier,
//    $cwItem->description,
//    $cwItem->category->name,
//    $cwItem->unitOfMeasure->name,
//    $cwItem->customerDescription,
//    $cwItem->price,
//    null,
//    null
//)->Products[0]);

//        $f = $cin7Service->productFamilies()->ProductFamilies;
//        sleep(1);
//        collect($f)->map(function ($pf) use ($cin7Service) {
//            if (count($pf->Products) > 1) {
//                return false;
//            }
//
//            $p = $pf->Products[0];
//
//            $product = $cin7Service->product($p->ID)->Products[0];
//            sleep(1);
//
//            $newProduct = $cin7Service->cloneProduct($product, $product->SKU . "-test")->Products[0];
//            sleep(1);
//
//            $cin7Service->updateProductFamily([
//                'ID' => $pf->ID,
//                'Products' => [[
//                    'ID' => $newProduct->ID,
//                    'Option1' => 'Test'
//                ]]
//            ]);
//            sleep(1);
//        });

//$page = 1;
//while (true) {
//    $length = collect($connectWiseService->getCatalogItems($page, 'inactiveFlag=false', null, null,1000))
//        ->map(function ($item) use ($cin7Service, $connectWiseService) {
//
//            if ($item->id < 1964 || in_array($item->category->id, [31, 32, 29, 16, 34, 33, 13, 30, 28, 3])) {
//                return false;
//            }
//
//            $i = 0;
//            while (true) {
//                try {
//                    $productFamily = $cin7Service->createProductFamily(
//                        $item->identifier . '-PROJECT',
//                        Str::replace('	', '', Str::trim($item->description)) . ($i ? " [{$i}]" : ""),
//                        $item->category->name,
//                        $item->unitOfMeasure->name,
//                        Str::replace('	', '', Str::trim($item->customerDescription))
//                    );
//
//                    sleep(1);
//
//                    break;
//                } catch (GuzzleException $e) {
//                    if (Str::contains($e->getMessage(), "'Name' already exists")) {
//                        $i++;
//                        continue;
//                    } elseif (Str::contains($e->getMessage(), "was not found reference book") || Str::contains($e->getMessage(), "'SKU' already exists") || Str::contains($e->getMessage(), "Category not found") || Str::contains($e->getMessage(), "than 45 characters")) {
//                        return false;
//                    }
//
//                    echo $item->identifier . "\n";
//
//                    throw $e;
//                }
//            }
//
//            collect($connectWiseService->getAttachments('ProductSetup', $item->id))->map(function ($image, $index) use ($cin7Service, $productFamily, $connectWiseService) {
//                $cin7Service->uploadProductFamilyAttachment($productFamily->ID, $image->fileName, base64_encode($connectWiseService->downloadAttachment($image->id)->getFile()->getContent()), $index == 0);
//                sleep(1);
//            });
//
//            try {
//                $connectWiseService->updateCatalogItemCin7ProductFamilyId($item, $productFamily->ID);
//            } catch (\Exception $e) {
//                echo $item->identifier . ": {$productFamily->ID}\n";
//                return false;
//            }
//
//            echo $item->identifier . "\n";
//        })->count();
//
//    if ($length < 1000) {
//        break;
//    }
//
//    $page++;
//}

//collect($bigCommerceService->getProducts(1, 250)->data)->map(function ($product) use ($bigCommerceService) {
//    if (in_array($product->id, [4640, 4638, 4637]) || $product->id < 4624) {
//        return false;
//    }
//
//    $image_url = $bigCommerceService->getProductVariants($product->id)->data[0]->image_url;
//
//    if (!$image_url) {
//        return false;
//    }
//
//    $bigCommerceService->uploadProductImageUrl($product->id, $image_url);
//});

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
