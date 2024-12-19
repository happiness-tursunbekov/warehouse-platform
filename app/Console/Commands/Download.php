<?php


namespace App\Console\Commands;

use App\Services\ConnectWiseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Download extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:download';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(ConnectWiseService $connectWiseService, \App\Services\BigCommerceService $bigCommerceService)
    {
        $page = 1;
        while (true) {

            $items = $connectWiseService->getCatalogItems($page, 'inactiveFlag=false', '', null, 1000);
            array_map(function ($item) use ($page, $connectWiseService, $bigCommerceService) {

                $bcProductId = $connectWiseService->getBigCommerceProductId($item);

                if (!$bcProductId) return false;

                $path = Str::replace(' ', '', "/cw-items/{$item->category->id}/{$item->id}_{$item->identifier}.jpg");
                try {
                    if (!Storage::exists($path)) {
                        $path = Str::replace('/cw', '/cw2', $path);
                        if (!Storage::exists($path)) {
                            $path = Str::replace('/cw2', '/egg', $path);
                            if (!Storage::exists($path)) {
                                return false;
                            }
                        }
                    }
                    if (Storage::size($path) == 6722) return false;

                    $dItems = $connectWiseService->getCatalogItems($page, "inactiveFlag=false and description='{$item->description}' and id!={$item->id}", '', null, 1000);

                    if (count($dItems) === 0) return false;

                    array_map(function ($dItem) use ($connectWiseService, $bigCommerceService, $path){
                        $bcProductId = $connectWiseService->getBigCommerceProductId($dItem);

                        if (!$bcProductId) return false;

                        $connectWiseService->systemDocumentUploadTemp(Storage::get($path), 'ProductSetup', $dItem->id, "{$dItem->id}_{$dItem->identifier}.jpg", 'Product image');
                        $bigCommerceService->uploadProductImage($bcProductId, Storage::get($path), "{$dItem->id}_{$dItem->identifier}.jpg");

                        echo $dItem->identifier . "\n";
                    }, $dItems);
                } catch (\Exception $e) {
                    return false;
                }


            }, $items);

            if (count($items) < 1000)
                break;

            $page++;
        }
    }

    private function searfor($text, $searchfor)
    {

        $pattern = preg_quote($searchfor, '/');

        $pattern = "/^.*$pattern.*\$/m";

        preg_match_all($pattern, $text, $matches);

        return @$matches[0][0] ?? null;
    }

//$search = file_get_contents("https://origin.cdw.com/sp.prod/v1.0/typeahead/com/product/query?key={$item->identifier}");
//
//$id = @json_decode($search)->productSuggestions[0]->imageEdc;
//
//if ($id) {
//
//    $url = "https://webobjects2.cdw.com/is/image/CDW/{$id}?\$product-detail$";
//
//    $ext = explode('/', get_headers($url,1)['Content-Type'])[1];
//
//    if ($ext == 'jpeg')
//        $ext = 'jpg';
//
//    Storage::put("/cw-items/{$item->category->id}/{$item->id}_{$item->identifier}.{$ext}", file_get_contents($url));
//}
}


//$page = 2;
//
//while (true) {
//
//    $items = $connectWiseService->getCatalogItems($page, 'inactiveFlag=false', '', 'id,identifier,category', 100);
//    array_map(function ($item) use ($page) {
//        try {
//            $lower = Str::lower($item->identifier);
//            $upper = Str::upper($item->identifier);
//
//            $file = file_get_contents("https://www.cdw.com/search/?key={$lower}");
//
//            $res = $this->searfor($file, "MFG#: {$upper}");
//
//            if ($res) {
//                $res = $this->searfor($file, 'class="search-result-product-url"');
//                $doc = new \DOMDocument();
//                @$doc->loadHTML($res);
//
//                $href = $doc->getElementsByTagName('a')[0]->getAttribute('href');
//
//                $temp = explode('/', $href);
//
//                $fileId = $temp[count($temp) - 1];
//
//                $url = "https://webobjects2.cdw.com/is/image/CDW/{$fileId}?\$product-detail$";
//
//                $ext = explode('/', get_headers($url,1)['Content-Type'])[1];
//
//                if ($ext == 'jpeg')
//                    $ext = 'jpg';
//
//                Storage::put("/cw-items/{$item->category->id}/{$item->id}_{$item->identifier}.{$ext}", file_get_contents($url));
//            }
//
//            sleep(2);
//        } catch (\Exception $e) {
//            echo "{$item->identifier}: Not found! \n";
//        }
//    }, $items);
//
//    if (count($items) < 100)
//        break;
//
//    $page++;
//}


//$page = 1;
//$i= 1734554898870;
//$i2 = 1734554898869;
//$i3 = 351014653707614150924;
//while (true) {
//
//    $items = $connectWiseService->getCatalogItems($page, 'inactiveFlag=false and id > 2894', '', null, 1000);
//    array_map(function ($item) use ($page, $connectWiseService, $bigCommerceService, $i, $i2, $i3) {
//
//        $bcProductId = $connectWiseService->getBigCommerceProductId($item);
//
//        if (!$bcProductId) return false;
//
//        $path = Str::replace(' ', '', "/cw-items/{$item->category->id}/{$item->id}_{$item->identifier}.jpg");
//        try {
//            if (Storage::exists($path) || Storage::exists(Str::replace('/cw', '/egg', $path)) || Storage::exists(Str::replace('/cw', '/egg-description', $path))) {
//                return false;
//            }
//        } catch (\Exception $e) {
//            return false;
//        }
//
//        $lower = Str::lower($item->description);
//        $upper = Str::upper($item->description);
//
//        try {
//            $res = file_get_contents("https://www.neweggbusiness.com/common/ajax/keywordsuggestion.aspx?callback=keywordsuggestion&callback=jQuery{$i3}_{$i2}&keyword={$upper}&nodeid=-1&_={$i}");
//        } catch (\Exception $e) {
//            $i++;
//            $res = file_get_contents("https://www.neweggbusiness.com/common/ajax/keywordsuggestion.aspx?callback=keywordsuggestion&callback=jQuery{$i3}_{$i2}&keyword={$upper}&nodeid=-1&_={$i}");
//
//        }
//
//        try {
//            $resJson = json_decode(Str::replace('})', '}', "{" . explode('({', $res)[1]));
//        } catch (\Exception $e) {
//            return false;
//        }
//
//        if (!$resJson || !$resJson->Recommendation || !$resJson->Recommendation->Items || !$resJson->Recommendation->Items->RecommendItems) return false;
//
//        $product = $resJson->Recommendation->Items->RecommendItems[0]->Product;
//
//        if (Str::contains(Str::lower($product->ProductTitle), $lower)) {
//            $url = Str::replace('ProductImageCompressAll125', 'ProductImageCompressAll1280', $product->ImageUrl);
//            Storage::put("/egg-description-items/{$item->category->id}/{$item->id}_{$item->identifier}.jpg", file_get_contents($url));
//            print_r($url);
//            echo "\n";
//        }
//
//
//    }, $items);
//
//    if (count($items) < 1000)
//        break;
//
//    $page++;
//}
