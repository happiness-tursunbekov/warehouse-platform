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

        collect($bigCommerceService->getProducts(2, 250)->data)->map(function ($product) use ($connectWiseService, $bigCommerceService) {

            $images = $bigCommerceService->getProductImages($product->id);

            sleep(1);

            if ($images && count($images->data) > 0) {
                return false;
            }

            $catalogItem = $connectWiseService->getCatalogItemByIdentifier($product->sku);

            if (!$catalogItem) {
                return false;
            }

            $attachments = collect($connectWiseService->getAttachments(ConnectWiseService::RECORD_TYPE_PRODUCT_SETUP, $catalogItem->id));

            $c = $attachments->map(function ($attachment, $index) use ($product, $bigCommerceService, $connectWiseService) {

                $file = $connectWiseService->downloadAttachment($attachment->id)->getFile()->getContent();

                $bigCommerceService->uploadProductImage(
                    $product->id,
                    $file,
                    $attachment->fileName,
                    $index == 0);
            })->count();

            if ($c > 0) {
                echo "{$catalogItem->identifier}\n";
            }
        });

//        $catalogItem = $connectWiseService->getCatalogItemByIdentifier('OR-576-110-005');
//
//        $connectWiseService->catalogItemAdjust($catalogItem, 47, ConnectWiseService::AZAD_MAY_WAREHOUSE);
//        $page = 2;
//
//        collect($cin7Service->products($page, 1000)->Products)->map(function ($product) use ($connectWiseService) {
//            if (Str::contains($product->SKU, '-PROJECT')) {
//                return false;
//            }
//
//            $catalogItem = $connectWiseService->getCatalogItemByIdentifier($product->SKU);
//
//            if (!$catalogItem) {
//                return false;
//            }
//
//            echo "{$catalogItem->identifier}\n";
//
//            $status = $connectWiseService->syncCatalogItemAttachmentsWithCin7($catalogItem->id, $product->ID, isProductFamily: false);
//
//            if ($status) {
//                sleep(5);
//            }
//        });

//        $poItem = $connectWiseService->purchaseOrderItem(1012, 3251);
//
//        $connectWiseService->pickOrShipPurchaseOrderItem(1012, $poItem);

//        $lines = cache()->get('lines') ?: collect();
//
//        $cin7Service->createPurchaseOrder($lines->toArray(), '465d215e-a1ca-4b2a-aacf-e65cbb98fff9');
//
//        $list = collect(json_decode('[{ "product": "TSRPW-JB3", "qty": 57 },{ "product": "TSRW-JB3", "qty": 1 },{ "product": "03725", "qty": 49 },{ "product": "OR-403HDJ12", "qty": 95 },{ "product": "TP160", "qty": 21 },{ "product": "DS-160", "qty": 21 },{ "product": "B335-3", "qty": 6 },{ "product": "OR-PHAPJU24", "qty": 16 },{ "product": "OR-404HDJ2", "qty": 12 },{ "product": "4330171116", "qty": 9 },{ "product": "6B-272-2B", "qty": 8 },{ "product": "39702", "qty": 8 },{ "product": "V-1016-W", "qty": 7 },{ "product": "129456", "qty": 6 },{ "product": "129464", "qty": 6 },{ "product": "CJ25-005", "qty": 4 },{ "product": "CJ25-004", "qty": 5 },{ "product": "TRP11-CM", "qty": 4 },{ "product": "DY-FAP-B", "qty": 4 },{ "product": "OR-GB2X12TGB", "qty": 4 },{ "product": "PAC526FCW", "qty": 4 },{ "product": "JP0612B", "qty": 4 },{ "product": "LTK2802", "qty": 4 },{ "product": "B0CH2SP1LR", "qty": 18 },{ "product": "RADT90KIT4EZ", "qty": 3 },{ "product": "P820147HB", "qty": 3 },{ "product": "V-1020C", "qty": 2 },{ "product": "42-3-12", "qty": 2 },{ "product": "40613", "qty": 2 },{ "product": "6B-272-4B", "qty": 2 },{ "product": "45PLA-PRO-001-HDP", "qty": 2 },{ "product": "DTK-4LVLP-CR", "qty": 2 },{ "product": "4479630", "qty": 2 },{ "product": "60-1902-53", "qty": 2 },{ "product": "Corner Kit", "qty": 1 },{ "product": "PROSIXC2W", "qty": 2 },{ "product": "HDMI-50FT", "qty": 2 },{ "product": "QL-956", "qty": 2 },{ "product": "20401966", "qty": 2 },{ "product": "PROSIXGB", "qty": 2 },{ "product": "PROSIXFLOOD", "qty": 1 },{ "product": "PROSIXTEMP", "qty": 1 },{ "product": "PROSIXHEATV", "qty": 1 },{ "product": "V-9830-W", "qty": 1 },{ "product": "FEB1", "qty": 1 },{ "product": "BR1", "qty": 1 },{ "product": "FW12", "qty": 1 },{ "product": "PROA7BAT2", "qty": 1 },{ "product": "ASPG", "qty": 1 },{ "product": "116317", "qty": 1 },{ "product": "115152", "qty": 2 },{ "product": "116332", "qty": 1 },{ "product": "DY-900-FRM-3A", "qty": 2 },{ "product": "UACC-PoE++-10G", "qty": 1 },{ "product": "12848-758", "qty": 1 },{ "product": "68HOU-PPRO-01-CRS", "qty": 1 },{ "product": "BH61-HW", "qty": 1 },{ "product": "CMS006W", "qty": 1 },{ "product": "4461030-OSDP", "qty": 1 },{ "product": "415110-30OSDP", "qty": 1 },{ "product": "CP-7811-WMK=", "qty": 1 }]'));
//
//        $lines = $list->map(function ($item) use ($cin7Service, $connectWiseService) {
//            $catalogItem = $connectWiseService->getCatalogItemByIdentifier($item->product);
//
//            $product = $cin7Service->productBySku($catalogItem->identifier);
//
//            sleep(1);
//
//            $cost = $catalogItem->cost * 0.9;
//
//            if (!$product) {
//                $product = $cin7Service->createProduct(
//                    $catalogItem->identifier,
//                    $connectWiseService->generateProductName($catalogItem->description, $catalogItem->identifier),
//                    $catalogItem->category->name,
//                    $catalogItem->unitOfMeasure->name,
//                    $catalogItem->customerDescription,
//                    $cost * 1.07
//                );
//
//                sleep(1);
//            }
//
//            return [
//                'ProductID' => $product->ID,
//                'Name' => $product->Name,
//                'Quantity' => $item->qty,
//                'Price' => $cost,
//                'TaxRule' => 'Tax Exempt',
//                'Total' => round($cost * $item->qty, 2),
//                'Received' => true
//            ];
//        });
//
//        cache()->put('lines', $lines);

//        $list = collect(json_decode('[{"project": "#59 - 2021-18 (01-223020) SST Sugarland HS (ALL Divisions-FULL)","phase": "#83 - 28 30 00 - Intrusion Detection Systems  (TEAM A)","ticket": "#923 - Procurement","product": "TSRPW-JB3","qty": 7},{"project": "#275 - HPS2023 HSS San Antonio Phase 2 GU Wiseman (NEW)","phase": "#678 - 28 30 - Intrusion Detection - Implementation","ticket": "#3812 - Materials","product": "TSRPW-JB3","qty": 51},{"project": "#59 - 2021-18 (01-223020) SST Sugarland HS (ALL Divisions-FULL)","phase": "#77 - 27 10 00 - Structured Cabling (TEAM A)","ticket": "#899 - Procurement","product": "03725","qty": 49},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#668 - 27 10 - Structured Cabling - Implementation","ticket": "#3788 - Materials","product": "OR-403HDJ12","qty": 45},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#684 - 28 10 - Access Control - Implementation","ticket": "#3824 - Materials","product": "TP160","qty": 21},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#684 - 28 10 - Access Control - Implementation","ticket": "#3824 - Materials","product": "DS-160","qty": 21},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#685 - 28 30 - Intrusion Detection - Implementation","ticket": "#3826 - Materials","product": "B335-3","qty": 6},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#668 - 27 10 - Structured Cabling - Implementation","ticket": "#3788 - Materials","product": "OR-PHAPJU24","qty": 16},{"project": "#56 - CW#56 - Harmony School of Excellence-Katy Phase II, PROJECT AGREEMENT NUMBER: 27-2000","phase": "#86 - 27 10 00 - Structured Cabling (TEAM A)","ticket": "#935 - Procurement - Structured Cabling (TEAM A)","product": "PoE-911","qty": 15},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#668 - 27 10 - Structured Cabling - Implementation","ticket": "#3788 - Materials","product": "OR-404HDJ2","qty": 12},{"project": "#59 - 2021-18 (01-223020) SST Sugarland HS (ALL Divisions-FULL)","phase": "#85 - 27 41 16 - Integrated Audio-Video Systems (TEAM A)","ticket": "#931 - Procurement","product": "4330171116","qty": 9},{"project": "#186 - Form 470 Application Number: 240007096 copper","phase": "0","ticket": "0","product": "6B-272-2B","qty": 8},{"project": "#59 - 2021-18 (01-223020) SST Sugarland HS (ALL Divisions-FULL)","phase": "#80 - 27 40 00 - Audio Visual Systems  (TEAM A)","ticket": "#911 - Procurement","product": "39702","qty": 8},{"project": "#275 - HPS2023 HSS San Antonio Phase 2 GU Wiseman (NEW)","phase": "#676 - 27 50 - Distributed Communications - Implementation","ticket": "#3808 - Materials","product": "V-1016-W","qty": 6},{"project": "#59 - 2021-18 (01-223020) SST Sugarland HS (ALL Divisions-FULL)","phase": "#80 - 27 40 00 - Audio Visual Systems  (TEAM A)","ticket": "#911 - Procurement","product": "V-1016-W","qty": 1},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#694 - Procurement","ticket": "#3816 - Materials","product": "129456","qty": 6},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#694 - Procurement","ticket": "#3816 - Materials","product": "129464","qty": 6},{"project": "#59 - 2021-18 (01-223020) SST Sugarland HS (ALL Divisions-FULL)","phase": "#83 - 28 30 00 - Intrusion Detection Systems  (TEAM A)","ticket": "#923 - Procurement","product": "3041M","qty": 6},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#685 - 28 30 - Intrusion Detection - Implementation","ticket": "#3826 - Materials","product": "CJ25-004","qty": 5},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#668 - 27 10 - Structured Cabling - Implementation","ticket": "#3788 - Materials","product": "TRP11-CM","qty": 4},{"project": "#59 - 2021-18 (01-223020) SST Sugarland HS (ALL Divisions-FULL)","phase": "#77 - 27 10 00 - Structured Cabling (TEAM A)","ticket": "#899 - Procurement","product": "DY-FAP-B","qty": 4},{"project": "#275 - HPS2023 HSS San Antonio Phase 2 GU Wiseman (NEW)","phase": "#670 - 27 10 - Structured Cabling - Implementation","ticket": "#3795 - Materials","product": "OR-GB2X12TGB","qty": 4},{"project": "#275 - HPS2023 HSS San Antonio Phase 2 GU Wiseman (NEW)","phase": "#675 - 27 40 - Audio Visual - Implementation","ticket": "#3806 - Materials","product": "PAC526FCW","qty": 4},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#668 - 27 10 - Structured Cabling - Implementation","ticket": "#3788 - Materials","product": "JP0612B","qty": 4},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#685 - 28 30 - Intrusion Detection - Implementation","ticket": "#3826 - Materials","product": "CJ25-005","qty": 4},{"project": "#310 - CCTV Support Services","phase": "0","ticket": "0","product": "LTCMIP9382W-28MD","qty": 4},{"project": "#276 - Vida Superfruit. Communications, Electronic Safety & Security","phase": "#838 - 28 10 - Access Control","ticket": "#3871 - 28 10 - Access Control - Materials","product": "LTK2802","qty": 4},{"project": "#57 - SST Hill Country High School Contract Job#: 22-03-092","phase": "#74 - 28 23 00 - Electronic Surveillance Systems  (TEAM A)","ticket": "#887 - Procurement - 28 23 00 - Electronic Surveillance Systems  (TEAM A)","product": "B0CH2SP1LR","qty": 3},{"project": "#279 - HPS2024_HSA Bridgeland (NEW)","phase": "#720 - 27 10 - Structured Cabling - Implementation","ticket": "#3903 - Materials","product": "RADT90KIT4EZ","qty": 3},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#668 - 27 10 - Structured Cabling - Implementation","ticket": "#3788 - Materials","product": "P820147HB","qty": 3},{"project": "#275 - HPS2023 HSS San Antonio Phase 2 GU Wiseman (NEW)","phase": "#676 - 27 50 - Distributed Communications - Implementation","ticket": "#3808 - Materials","product": "V-1020C","qty": 3},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#684 - 28 10 - Access Control - Implementation","ticket": "#3824 - Materials","product": "42-3-12","qty": 2},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#682 - 27 40 - Audio Visual - Implementation","ticket": "#3820 - Materials","product": "40613","qty": 2},{"project": "#279 - HPS2024_HSA Bridgeland (NEW)","phase": "#720 - 27 10 - Structured Cabling - Implementation","ticket": "#3903 - Materials","product": "6B-272-4B","qty": 2},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#684 - 28 10 - Access Control - Implementation","ticket": "#3824 - Materials","product": "45PLA-PRO-001-HDP","qty": 2},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#684 - 28 10 - Access Control - Implementation","ticket": "#3824 - Materials","product": "DTK-4LVLP-CR","qty": 2},{"project": "#279 - HPS2024_HSA Bridgeland (NEW)","phase": "#725 - 28 10 - Access Control - Implementation","ticket": "#3914 - Materials","product": "4479630","qty": 2},{"project": "#59 - 2021-18 (01-223020) SST Sugarland HS (ALL Divisions-FULL)","phase": "#85 - 27 41 16 - Integrated Audio-Video Systems (TEAM A)","ticket": "#931 - Procurement","product": "60-1902-53","qty": 2},{"project": "#276 - Vida Superfruit. Communications, Electronic Safety & Security","phase": "#838 - 28 10 - Access Control","ticket": "#3871 - 28 10 - Access Control - Materials","product": "Corner Kit","qty": 1},{"project": "#279 - HPS2024_HSA Bridgeland (NEW)","phase": "#720 - 27 10 - Structured Cabling - Implementation","ticket": "#3903 - Materials","product": "Z5408","qty": 2},{"project": "#276 - Vida Superfruit. Communications, Electronic Safety & Security","phase": "#840 - 28 30 - Intrusion Detection","ticket": "#3877 - 28 30 - Intrusion Detection - Materials","product": "PROSIXC2W","qty": 2},{"project": "#299 - Access Control and Surveillance System Live Stream in the Front Office - Katy Phase II","phase": "0","ticket": "0","product": "HDMI-50FT","qty": 2},{"project": "#295 - New AV Cafeteria System","phase": "0","ticket": "0","product": "QL-956","qty": 2},{"project": "#276 - Vida Superfruit. Communications, Electronic Safety & Security","phase": "#838 - 28 10 - Access Control","ticket": "#3871 - 28 10 - Access Control - Materials","product": "20401966","qty": 2},{"project": "#276 - Vida Superfruit. Communications, Electronic Safety & Security","phase": "#840 - 28 30 - Intrusion Detection","ticket": "#3877 - 28 30 - Intrusion Detection - Materials","product": "PROSIXGB","qty": 2},{"project": "#276 - Vida Superfruit. Communications, Electronic Safety & Security","phase": "#840 - 28 30 - Intrusion Detection","ticket": "#3877 - 28 30 - Intrusion Detection - Materials","product": "PROSIXFLOOD","qty": 1},{"project": "#276 - Vida Superfruit. Communications, Electronic Safety & Security","phase": "#840 - 28 30 - Intrusion Detection","ticket": "#3877 - 28 30 - Intrusion Detection - Materials","product": "PROSIXTEMP","qty": 1},{"project": "#276 - Vida Superfruit. Communications, Electronic Safety & Security","phase": "#840 - 28 30 - Intrusion Detection","ticket": "#3877 - 28 30 - Intrusion Detection - Materials","product": "PROSIXHEATV","qty": 1},{"project": "#276 - Vida Superfruit. Communications, Electronic Safety & Security","phase": "#835 - 27 50 - Distributed Communications","ticket": "#3869 - 27 50 - Distributed Communications - Materials","product": "V-9830-W","qty": 1},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#682 - 27 40 - Audio Visual - Implementation","ticket": "#3820 - Materials","product": "FEB1","qty": 1},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#682 - 27 40 - Audio Visual - Implementation","ticket": "#3820 - Materials","product": "BR1","qty": 1},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#761 - GU REBID HSA City Place PCO#33 RFIs #82 & #83 Additional Cable Tray & Conduit QUOTE #000565 V3","ticket": "#5148 - Materials","product": "FW12","qty": 1},{"project": "#276 - Vida Superfruit. Communications, Electronic Safety & Security","phase": "#840 - 28 30 - Intrusion Detection","ticket": "#3877 - 28 30 - Intrusion Detection - Materials","product": "PROA7BAT2","qty": 1},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#761 - GU REBID HSA City Place PCO#33 RFIs #82 & #83 Additional Cable Tray & Conduit QUOTE #000565 V3","ticket": "#5148 - Materials","product": "ASPG","qty": 1},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#694 - Procurement","ticket": "#3816 - Materials","product": "116317","qty": 1},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#694 - Procurement","ticket": "#3816 - Materials","product": "115152","qty": 1},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#694 - Procurement","ticket": "#3816 - Materials","product": "115150","qty": 1},{"project": "#275 - HPS2023 HSS San Antonio Phase 2 GU Wiseman (NEW)","phase": "#677 - 28 10 - Access Control - Implementation","ticket": "#3810 - Materials","product": "INC-BCNF-300","qty": 1},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#668 - 27 10 - Structured Cabling - Implementation","ticket": "#3788 - Materials","product": "116332","qty": 1},{"project": "#59 - 2021-18 (01-223020) SST Sugarland HS (ALL Divisions-FULL)","phase": "#77 - 27 10 00 - Structured Cabling (TEAM A)","ticket": "#899 - Procurement","product": "DY-900-FRM-3A","qty": 1},{"project": "#324 - Video Surveillance Cameras Outdoor & Indoor","phase": "0","ticket": "0","product": "UACC-PoE++-10G","qty": 1},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#668 - 27 10 - Structured Cabling - Implementation","ticket": "#3788 - Materials","product": "12848-758","qty": 1},{"project": "#274 - GU REBID Harmony Science Academy City Place (NEW)","phase": "#684 - 28 10 - Access Control - Implementation","ticket": "#3824 - Materials","product": "68HOU-PPRO-01-CRS","qty": 1},{"project": "#272 - SST Schools Vape Sensors and Guest Management system  - Quote #437.V10 & Quote #433.V2","phase": "#646 - SST Bayshore Elementary","ticket": "#3760 - Materials and Implementation","product": "BH61-HW","qty": 1},{"project": "#59 - 2021-18 (01-223020) SST Sugarland HS (ALL Divisions-FULL)","phase": "#85 - 27 41 16 - Integrated Audio-Video Systems (TEAM A)","ticket": "#931 - Procurement","product": "CMS006W","qty": 1},{"project": "#279 - HPS2024_HSA Bridgeland (NEW)","phase": "#725 - 28 10 - Access Control - Implementation","ticket": "#3914 - Materials","product": "415110-30OSDP","qty": 1},{"project": "#279 - HPS2024_HSA Bridgeland (NEW)","phase": "#725 - 28 10 - Access Control - Implementation","ticket": "#3914 - Materials","product": "4461030-OSDP","qty": 1}]'));
//
//        $lines = collect();
//
//        $list->map(function ($item, $key) use ($connectWiseService, $cin7Service, &$lines) {
//
//            echo json_encode($item) . "\n";
//
//            $ticketId = $item->ticket !== '0' ? Str::replace('#', '', explode(' - ', $item->ticket)[0]) : null;
//            $projectId = Str::replace('#', '', explode(' - ', $item->project)[0]);
//
//            $pfSku = $connectWiseService->generateProductFamilySku($item->product);
//
//            $pSku = $connectWiseService->generateProductSku($pfSku, $projectId, $ticketId);
//
//            $onHand = $cin7Service->productAvailabilityBySku($pSku);
//
//            sleep(1);
//
//            if ($onHand && $onHand->OnHand > 0) {
//
//                $lines->push([
//                    "ProductID" =>$onHand->ID,
//                    "Quantity" => 0,
//                    "UnitCost" => 0.0001,
//                    "Location" => Cin7Service::INVENTORY_AZAD_MAY
//                ]);
//            }
//        });
//
//        cache()->put('lines', $lines);
//
//        $cin7Service->stockAdjustBulk($lines);

//
//        $connectWiseService->publishProductOnCin7($product, $product->quantity, true);

//        $pickedReport = (cache()->get('pickedReport'))->map(function ($item) {
//            return [
//                'company' => $item['product']->company->name,
//                'project' => @$item['product']->project ? "#{$item['product']->project->id} - {$item['product']->project->name}" : "",
//                'phase' => @$item['product']->phase ? "#{$item['product']->phase->id} - {$item['product']->phase->name}" : "",
//                'ticket' => @$item['product']->ticket ? "#{$item['product']->ticket->id} - {$item['product']->ticket->summary}" : "",
//                'product' => $item['product']->catalogItem->identifier,
//                'picked_quantity' => $item['picked']
//            ];
//        })->toArray();
//
//        $csvFileName = 'storage/app/public/reports/pickedReport.csv';
//        $csvFile = fopen($csvFileName, 'w');
//        $headers = array_keys($pickedReport[0]); // Get the column headers from the first row
//        fputcsv($csvFile, $headers);
//
//        foreach ($pickedReport as $row) {
//            fputcsv($csvFile, (array) $row);
//        }
//
//        fclose($csvFile);

//        $products = collect($cin7Service->products(1, 1000)->Products);
//        sleep(1);
//        $products->map(function ($product) use ($cin7Service, $bigCommerceService) {
//            if (Str::contains($product->SKU, '-PROJECT')) {
//
//                try {
//
//                    $bcProduct = $bigCommerceService->getProductBySku(explode('PROJECT', $product->SKU)[0] . 'PROJECT');
//
//                    $bcVariant = $bigCommerceService->getProductVariantBySku($bcProduct->id, $product->SKU);
//                } catch (\Exception) {}
//
//                $product->SKU = Str::replace('TICKET', 'T', Str::replace('COMPANY', 'C', $product->SKU));
//
//                $cin7Service->updateProduct([
//                    'ID' => $product->ID,
//                    'SKU' => $product->SKU
//                ]);
//
//                try {
//                    $bigCommerceService->updateProductVariant($bcProduct->id, $bcVariant->id, [
//                        'sku' => $product->SKU
//                    ]);
//                } catch (\Exception) {}
//
//                sleep(1);
//            }
//        });

//        $pickedReport = cache()->get('pickedReport') ?: collect();
//
//        $start = false;
//
//        $lines = $pickedReport->map(function ($item) use ($cin7Service, $connectWiseService, &$start) {
//
//            if ($item['product']->id == 14623) {
//                $start = true;
//            }
//
//            if (!$start) {
//                return false;
//            }
//
//            $cin7ProductSku = $connectWiseService->generateProductSku(
//                $item['product']->catalogItem->identifier . '-PROJECT',
//                $item['product']->project->id ?? null,
//                $item['product']->ticket->id ?? null,
//                $item['product']->company->id ?? null
//            );
//
//            $product = $cin7Service->productBySku($cin7ProductSku);
//            sleep(1);
//
//            return [
//                "ProductID" => $product ? $product->ID : '',
//                "SKU" => $cin7ProductSku,
//                "Quantity" => $item['picked'],
//                "UnitCost" => 0.0001,
//                "Location" => Cin7Service::INVENTORY_AZAD_MAY
//            ];
//        });
//            $lines = cache()->get('lines')->filter(fn($it) => $it)->where('ProductID', '!=', '') ?: collect();
//
//        $groupedByValue = $lines->groupBy('SKU');
//
//        $dupes = $groupedByValue->filter(function (Collection $groups) {
//            return $groups->count() > 1;
//        });
//
//        $newLines = [];
//
//        $dupes[] = '2C22/4C22/3P22 FS/4C18-CMP-YW-R-PROJECT-276-T-3871';

//        $cin7Service->stockAdjustBulk($newLines);

//        $ids = collect();
//
//        $ids->map(function ($id) use ($connectWiseService, &$pickedReport) {
//            collect($connectWiseService->getProducts(1, 'catalogItem/id=' . $id, 1000))->map(function ($product) use ($connectWiseService, &$pickedReport) {
//                $picks = collect($connectWiseService->getProductPickingShippingDetails($product->id));
//
//                if ($picks->count() > 0 && ($picked = $picks->pluck('pickedQuantity')->sum() - $picks->pluck('shippedQuantity')->sum()) > 0) {
//
//                    $pickedReport->push([
//                        'product' => $product,
//                        'picked' => $picked
//                    ]);
//                }
//            });
//        });
////
//        cache()->put('pickedReport', $pickedReport);

//        array_map(function ($product) use ($connectWiseService) {
//
//            $catalogItem = $connectWiseService->getCatalogItemByIdentifier($product->SKU);
//
//            if ($catalogItem) {
//                $connectWiseService->syncCatalogItemAttachmentsWithCin7($catalogItem->id, $product->ID, isProductFamily: false);
//            }
//
//        }, $cin7Service->products(16, 50)->Products);

//        $product = $bigCommerceService->getProduct(5626);
//
//        if (!Str::contains($product->sku, 'PROJECT')) {
//            try {
//                $bigCommerceService->createProductModifier($product->id, BigCommerceService::PRODUCT_OPTION_PROJECT);
//                $bigCommerceService->createProductModifier($product->id, BigCommerceService::PRODUCT_OPTION_PHASE, sort_order: 1);
//                $bigCommerceService->createProductModifier($product->id, BigCommerceService::PRODUCT_OPTION_PROJECT_TICKET, sort_order: 2);
//                $bigCommerceService->createProductModifier($product->id, BigCommerceService::PRODUCT_OPTION_COMPANY, sort_order: 3);
//                $bigCommerceService->createProductModifier($product->id, BigCommerceService::PRODUCT_OPTION_SERVICE_TICKET, sort_order: 4);
//                $bigCommerceService->createProductModifier($product->id, BigCommerceService::PRODUCT_OPTION_BUNDLE, sort_order: 5);
//            } catch (\Exception) {}
//        }


//        $products = cache()->get('products') ?:
//            collect();
//
//        $products->map(function ($item) use ($bigCommerceService) {
//            $product = $item['product'];
//
//            $options = collect($bigCommerceService->getProductModifiers($product->id));
//
//            if (!Str::contains($product->sku, 'PROJECT')) {
//                $option = $options->where('display_name', BigCommerceService::PRODUCT_OPTION_BUNDLE)->first();
//
//                $option->sort_order = 5;
//
//                $bigCommerceService->updateProductModifier($product->id, $option);
//            }
//        });


//
//

//
//        $products = collect($cin7Service->products(1, 1000)->Products);
//
//        sleep(1);
//
//        $products->map(function ($product) use ($connectWiseService, $cin7Service) {
//
//            if (Str::endsWith($product->SKU, 'ft)') && Str::startsWith($product->Name, '[USED] [USED]')) {
//
//                $name = Str::replace('[USED] [USED]', '[USED]', $product->Name);
//
//                $cin7Service->updateProduct([
//                    'ID' => $product->ID,
//                    'Name' => Str::limit($name, Str::length($name) - 8, '')
//                ]);
//                sleep(1);
//            }
//        });

//        dd($connectWiseService->purchaseOrder(1028));

//        $connectWiseService->updatePurchaseOrderCin7SalesOrderId($connectWiseService->purchaseOrder(1029), '');

//        $cin7Service->updateSale([
//            'ID' => 'e64c8bad-a394-46b4-a515-713c2055b33b',
//            'CustomerReference' => 'ConnectWise PO: 1069-PROJECT-#275-#186'
//        ]);
//
//        $cin7Service->sale([
//            'ID' => 'e64c8bad-a394-46b4-a515-713c2055b33b',
//            'CustomerReference' => 'ConnectWise PO: 1069-PROJECT-#275-#186'
//        ]);

//        $onHands = $connectWiseService->getProductCatalogOnHand(1, 'onHand > 0', null, 1000, ConnectWiseService::DEFAULT_WAREHOUSE_DEFAULT_BIN);
//
//        dd($onHands);

//        $products = collect($bigCommerceService->getProducts(2, 250)->data);
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


//        $catalogItem = $connectWiseService->getCatalogItemByIdentifier('66-240-4B(186ft)');
//
//        $cin7Product = $cin7Service->productBySku($catalogItem->identifier);
//
//        if (!$cin7Product) {
//            $cin7Product = $cin7Service->createProduct(
//                $catalogItem->identifier,
//                $connectWiseService->generateProductName($catalogItem->description, $catalogItem->identifier),
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
