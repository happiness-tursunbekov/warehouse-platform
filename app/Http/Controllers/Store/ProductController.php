<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductBrowseResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\ConnectWiseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'query' => ['nullable', 'string'],
            'categoryId' => ['nullable', 'integer'],
            'subcategoryId' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', Rule::in(['', 'NEW', 'USED'])]
        ]);

        $query = $request->get('query');
        $type = $request->get('type');
        $categoryId = $request->get('categoryId');
        $subcategoryId = $request->get('subcategoryId');
        $page = (int)$request->get('page', 1);
        $perPage = 24;

        $conditions = "inactiveFlag=false";

        if ($query)
            $conditions .= " and (identifier like '*{$query}*' or description like '*{$query}*')";

        if ($categoryId)
            $conditions .= " and category/id = {$categoryId}";

        if ($subcategoryId)
            $conditions .= " and subcategory/id = {$subcategoryId}";

        if ($type) {
            if ($type === 'USED')
                $conditions .= " and identifier like '*-used*'";
            else
                $conditions .= " and identifier not like '*-used*'";
        }

        $cwProducts = $connectWiseService->getCatalogItems($page, $conditions, 'id,identifier,description,category,subcategory,cost,unitOfMeasure', $perPage);
        $qty = $connectWiseService->getCatalogItemsQty($conditions)->count ?? 0;

        /** @var Collection $products */
        $products = Product::whereIn('id', array_column($cwProducts, 'id'))->get();

        $cwProducts = array_map(function ($item) use ($products) {
            $product = $products->find($item->id);

            $item->wpDetails = new ProductBrowseResource($product);

            return $item;
        }, $cwProducts);

        return response()->json([
            'products' => $cwProducts,
            'meta' => [
                'total' => $qty,
                'currentPage' => $page,
                'perPage' => $perPage,
                'totalPages' => ceil($qty / $perPage),
            ],
        ]);
    }

    public function options(ConnectWiseService $connectWiseService)
    {
        $subcats = collect($connectWiseService->getSubcategories(null, 'inactiveFlag=false', 'name asc'));

        $cats = array_map(function (\stdClass $cat) use ($subcats) {
            $cat->subcategories = $subcats->where('category.id', $cat->id)->values();

            return $cat;
        }, $connectWiseService->getCategories(null, 'inactiveFlag=false', 'name asc'));

        return response()->json([
            'categories' => $cats
        ]);
    }
}
