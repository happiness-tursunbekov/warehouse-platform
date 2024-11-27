<?php

namespace App\Models;

use App\Traits\ModelCamelCase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProductBarcode extends Model
{
    use ModelCamelCase;
    protected $fillable = [
        'product_id',
        'barcode'
    ];

    public static function addBarcode(array $productIds, string $barcode) : array
    {
        $res = [];
        foreach ($productIds as $productId) {
            $data = ['product_id' => $productId, 'barcode' => $barcode];
            $item = self::where($data)->first();
            $res[] = $item ?: self::create($data);
        }

        return $res;
    }

    public static function getByBarcode(string $barcode) : Collection
    {
        return self::where('barcode', $barcode)->get();
    }
}
