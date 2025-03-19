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

//        $connectWiseService->addProductsToPurchaseOrder(1014, collect($connectWiseService->getProducts(1, 'id in (18533, 18534, 18535, 18536, 18537, 18538, 18539, 18540, 18541, 18542)', 1000)));



//        $onHands = $connectWiseService->getProductCatalogOnHand(1, 'onHand > 0', null, 1000, ConnectWiseService::DEFAULT_WAREHOUSE_DEFAULT_BIN);
//
//        dd($onHands);

//        $products = collect($bigCommerceService->getProducts(3, 250)->data);
//
//        $channels = [];
//        $categories = [];
//
//        collect($connectWiseService->getProductCatalogOnHand(1, 'onHand > 0', null, 1000))->map(function ($onHand) use ($bigCommerceService, $products, &$channels, &$categories) {
//
//            $product = $products->where('sku', $onHand->catalogItem->identifier)->first();
//
//            if ($product) {
//                $channels[] = [
//                    'channel_id' => 1,
//                    'product_id' => $product->id
//                ];
//
//                $categories[] = [
//                    'category_id' => 332,
//                    'product_id' => $product->id
//                ];
//            }
//        });
//
//        $bigCommerceService->setProductChannelsBulk($channels);
//        $bigCommerceService->setProductCategoriesBulk($categories);


//        $catalogItem = $connectWiseService->getCatalogItemByIdentifier('TX-J2');
//
//        $cin7Product = $cin7Service->productBySku($catalogItem->identifier);
//
//        if (!$cin7Product) {
//            $cin7Product = $cin7Service->createProduct(
//                $catalogItem->identifier,
//                $catalogItem->description,
//                $catalogItem->category->name,
//                $catalogItem->unitOfMeasure->name,
//                $catalogItem->customerDescription,
//                $catalogItem->cost * 0.9 * 1.07
//            );
//        }
//
//        $cin7Service->stockAdjust($cin7Product->ID, 1, cost: $catalogItem->cost * 0.9);

//        $lines = cache()->get('lines') ?: collect();
//
//        $newLines = collect();
//
//        $stock = $cin7Service->getStockAdjustment('52652423-70cb-446c-bc7c-768cae1f783d');
//        array_map(function ($line) use ($connectWiseService, $cin7Service, &$newLines, $lines) {
//
//            if ($lines->where('ProductID', $line->ProductID)->first()) {
//                return false;
//            }
//
//            $catalogItem = $connectWiseService->getCatalogItemByIdentifier($line->SKU);
//
//            $cin7OnHand = $cin7Service->productAvailability($line->ProductID);
//
//            if (!$cin7OnHand) {
//                return false;
//            }
//
//            $newLines->push([
//                "ProductID" => $line->ProductID,
//                "SKU" => $line->SKU,
//                "Quantity" => $cin7OnHand->OnHand,
//                "UnitCost" => $catalogItem->cost * 0.9,
//                "Location" => Cin7Service::INVENTORY_AZAD_MAY
//            ]);
//
//            return $line;
//        }, $stock->NewStockLines);
//
//        cache()->put('new-lines', $newLines);

//        $lines->map(function ($line) use ($cin7Service, &$newLines) {
//
//            unset($line['SKU'])
//
//            return $line;
//        });


//        $take = $lines->map(function ($line) {
//
//            $line['Quantity'] = 0;
//
//            return $line;
//        });
//
//        $cin7Service->stockAdjustBulk($lines->values()->toArray());
//        $cin7Service->stockAdjustBulk($lines->values()->toArray());


//
//        $costs->map(function ($cost) use ($connectWiseService) {
//            $catalogItem = $connectWiseService->getCatalogItemByIdentifier($cost->productId);
//
//            $catalogItem->cost = $cost->cost;
//
//            $connectWiseService->updateCatalogItem($catalogItem);
//        });

//        $onHands = cache()->get('onHands') ?: collect();
//
//        $adjustment = $cin7Service->getStockAdjustment('52652423-70cb-446c-bc7c-768cae1f783d');
//
//        dd($adjustment);

//        $onHands->map(function ($onHand) use ($cin7Service, $connectWiseService) {
//
//            $catalogItem = $connectWiseService->getCatalogItem($onHand->catalogItem->id);
//
//            $product = $cin7Service->productBySku($catalogItem->identifier);
//            sleep(1);
//
//            if (!$product) {
//                return false;
//            }
//
//            $price = $catalogItem->cost * 0.9 * 1.07;
//
//            $cin7Service->updateProduct([
//                'ID' => $product->ID,
//                'PriceTier1' => $price
//            ]);
//            sleep(1);
//
//            echo "$catalogItem->identifier\n";
//        });

//        $qty = cache()->get('quantities')->filter(fn($line) => $line['SKU'] != '00301349');

//        $lines = cache()->get('lines');
//
//        $take = $lines->map(function ($line) {
//
//            $line['Quantity'] = 0;
//
//            return $line;
//        });
//
//        $cin7Service->stockAdjustBulk($take->values()->toArray());
//        $cin7Service->stockAdjustBulk($lines->values()->toArray());

//        $qty->map(function ($line) use (&$lines, $cin7Service) {
//            $product = $cin7Service->productBySku($line['SKU']);
//            sleep(1);
//            if (!$product) {
//                return false;
//            }
//
//            $line['ProductID'] = $product->ID;
//
//            unset($line['SKU']);
//
//            $lines->push($line);
//        });
//
//        cache()->put('lines', $lines);

//        $lines = $qty->map(function ($line) {
//
//            $line['Quantity'] = 0;
//
//            return $line;
//        });
//
//        $cin7Service->stockAdjustBulk($lines->values()->toArray());
//        $cin7Service->stockAdjustBulk($qty->values()->toArray());

//        $productPrices = collect();
//
//        $stock = $cin7Service->getStockAdjustment('52652423-70cb-446c-bc7c-768cae1f783d');
//
//        $stock->Status = 'COMPLETED';
//        $stock->Lines = array_map(function ($line) use ($connectWiseService, $cin7Service, &$productPrices) {
//
//            $catalogItem = $connectWiseService->getCatalogItemByIdentifier($line->SKU);
//
//            try {
//                $line->UnitCost = $catalogItem->cost * 0.9;
//            } catch (\Exception) {
//                abort(500, $line->SKU);
//            }
//
//            $productPrices->push([
//                'ID' => $line->ProductID,
//                'PriceTier1' => $line->UnitCost * 1.07
//            ]);
//
//            return $line;
//        }, $stock->NewStockLines);
//
//        cache()->put('productPrices', $productPrices);
//
//        unset($stock->NewStockLines);
//
//        $cin7Service->updateStockAdjustment($stock);

//        $catalogItemIdentifiers = [
//            'Wensilon 8x1-1/2', //
//            '2X2VG-HDTR61', //
//            '2X2VG-HDTR62', //
//            'V12H804001', //
//            'SMS2B',
//            'SMART1500LCD', //
//            'NYC-633', //
//            '0023630', //
//            '0023830', //
//            '12101-701',
//            'B08HZJ627G', //
//            'B08HZJ627G-RF', //
//            'EMT400', //
//            'M-46-v',
//            'M-46-FW', //
//            'V-9022A-2', //
//            'VIP78', //
//            'B0072JVT02', //
//            'SFP-10G-SR-S', //
//            '12101-701', //
//            '3312617902', //
//            'D-CIJ3', //
//            'LTB762', //
//            'V-1246', //
//            '6P4P24-BL-P-BER-AP-NS', //
//            '129454', //
//            'X003Y8Y5ST' //
//        ];
//
//        $lines = collect($connectWiseService->getCatalogItems(1, 'identifier in ("' . implode('","', $catalogItemIdentifiers) . '")', pageSize: 1000))
//            ->map(function ($catalogItem) use ($cin7Service, $connectWiseService) {
//
//                $cin7Product = $cin7Service->productBySku($catalogItem->identifier);
//
//                if (!$cin7Product) {
//                    $cin7Product = $cin7Service->createProduct(
//                        $catalogItem->identifier,
//                        $catalogItem->description,
//                        $catalogItem->category->name,
//                        $catalogItem->unitOfMeasure->name,
//                        $catalogItem->customerDescription,
//                        $catalogItem->cost * 0.9 * 1.07
//                    );
//                }
//
//                $onHand = $connectWiseService->getCatalogItemOnHand($catalogItem->id)->count;
//
//                return [
//                    "ProductID" => $cin7Product->ID,
//                    "Quantity" => $onHand,
//                    "UnitCost" => $catalogItem->cost * 0.9,
//                    "Location" => Cin7Service::INVENTORY_AZAD_MAY
//                ];
//            })->toArray();
//
//        $cin7Service->stockAdjustBulk($lines);

//        $stock = $cin7Service->getStockAdjustment('e73df7c8-a050-4669-b3c5-c8fb0bfa7d19');
//
//        $stock->Status = 'COMPLETED';
//        $stock->Lines = $stock->NewStockLines;
//
//        unset($stock->NewStockLines);
//
//        $cin7Service->updateStockAdjustment($stock);


//        $mergedQtyArr1 = cache()->get('mergedQtyArr1');
//
//        dd($mergedQtyArr1->where('ProductID', 'ec05505c-e2b8-4ec3-a018-912f6ea9563d'));

//        $mergedQtyArr1 = cache()->get('mergedQtyArr1');

//        $mergedQtyArr2 = $mergedQtyArr2->map(function ($line) use ($cin7Service) {
//
//            $cin7Product = $cin7Service->productBySku($line['SKU']);
//            sleep(1);
//            if ($cin7Product) {
//                $line['ProductID'] = $cin7Product->ID;
//                unset($line['SKU']);
//            }
//
//            return $line;
//        });
//
//        cache()->put('mergedQtyArr2', $mergedQtyArr2);

//        dd($mergedQtyArr1);
//
//        $cin7Service->stockAdjustBulk($mergedQtyArr2->whereNotNull('ProductID')->values());



//        $mergedQtyArr1 = collect();
//        $mergedQtyArr2 = collect();
//
//        cache()->get('cin7adjustment')->where('SKU', '!=', 'CP-8845-K9=.')->where('UnitCost', '>', 0)->map(function ($line) use ($cin7Service, &$mergedQtyArr1, &$mergedQtyArr2) {
//            $stock = $cin7Service->productAvailabilityBySku($line['SKU']);
//
//            sleep(1);
//
//            if ($stock && $stock->OnHand > 0) {
//                $mergedQtyArr1->push($line);
//            } else {
//                $mergedQtyArr2->push($line);
//            }
//        });
//
//        cache()->put('mergedQtyArr1', $mergedQtyArr1);
//        cache()->put('mergedQtyArr2', $mergedQtyArr2);

//        $cin7Service->stockAdjustBulk($cin7adjustment->where('UnitCost', '>', 0)->filter(fn($line) => $line)->values());

//        collect($connectWiseService->getProductCatalogOnHand(1, 'onHand > 0', pageSize: 1000))->map(function ($onHand) use ($connectWiseService, &$cin7adjustment) {
//
//            $catalogItem = $connectWiseService->getCatalogItem($onHand->catalogItem->id);
//
//            $cost = $catalogItem->cost * 0.9;
//
//            $cin7adjustment->push([
//                "SKU" => $catalogItem->identifier,
//                "Quantity" => $onHand->onHand,
//                "UnitCost" => $cost,
//                "Location" => Cin7Service::INVENTORY_AZAD_MAY
//            ]);
//        });
//
//        cache()->put('cin7adjustment', $cin7adjustment);


    }
}

//$ship = $connectWiseService->getProductPickingShippingDetails(13890)[0];
//
//$ship->pickedQuantity = 0;
//$ship->shippedQuantity = 0;
//
//dd($connectWiseService->addOrUpdatePickShip($ship));

//        $mergedQtyArr = cache()->get('mergedQtyArr');
//        $mergedQtyArr1 = cache()->get('mergedQtyArr1');
//        $mergedQtyArr2 = cache()->get('mergedQtyArr2')->where('ProductID', '!=', 'bad80f11-b3eb-4f55-9e4c-4e0ce88b8cdd')->where('UnitCost' , '>', 0) ?: collect();
//
//        $cin7Service->stockAdjustBulk($mergedQtyArr2->values());

//$page = 1;
//
//while (true) {
//    $products = collect($connectWiseService->getProducts($page, 'cancelledFlag=false', 1000));
//
//    $products->map(function ($product) use ($connectWiseService) {
//        $pickingShippingDetails = collect($connectWiseService->getProductPickingShippingDetails($product->id, 1, 'lineNumber!=0'));
//
//        $picked = $pickingShippingDetails->pluck('pickedQuantity')->sum();
//        $shipped = $pickingShippingDetails->pluck('shippedQuantity')->sum();
//
//        if ($picked != $shipped) {
//
//            $connectWiseService->shipProduct($product->id, $picked-$shipped);
//
//            echo "{$product->id}:{$picked}:{$shipped}\n";
//        }
//    });
//
//    if ($products->count() < 1000) {
//        break;
//    }
//
//    $page++;
//}

//collect($bigCommerceService->getProducts(1, 250)->data)->map(function ($product) use ($bigCommerceService) {
//    collect($bigCommerceService->getProductModifiers($product->id))
//        ->map(function ($modifier, $index) use ($product, $bigCommerceService) {
//            try {
//                $bigCommerceService->updateProductModifier($product->id, [
//                    'id' => $modifier->id,
//                    'sort_order' => $index,
//                    'shared_option_id' => $modifier->shared_option_id ?? null
//                ]);
//            } catch (\Exception $e) {
//                if (Str::contains($e->getMessage(), '429 Too')) {
//                    sleep(5);
//
//                    $bigCommerceService->updateProductModifier($product->id, [
//                        'id' => $modifier->id,
//                        'sort_order' => $index
//                    ]);
//                } else {
//                    throw $e;
//                }
//            }
//        });
//});

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
