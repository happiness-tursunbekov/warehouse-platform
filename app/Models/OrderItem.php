<?php

namespace App\Models;

use App\Traits\ModelCamelCase;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use ModelCamelCase;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'cost',
        'status'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
