<?php

namespace App\Models;

use App\Traits\ModelCamelCase;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Collection\Collection;

/**
 * @property PurchaseOrderItem[]|Collection $items
*/
class PurchaseOrder extends Model
{
    use ModelCamelCase;

    protected $fillable = [
        'id',
        'status_id',
        'closed_flag',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
