<?php

namespace App\Models;

use App\Traits\ModelCamelCase;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use ModelCamelCase;

    const RECEIVED_STATUS_FULLY_RECEIVED = 'FullyReceived';
    const RECEIVED_STATUS_WAITING = 'Waiting';
    const RECEIVED_STATUS_CANCELLED = 'Cancelled';

    protected $fillable = [
        'id',
        'purchase_order_id',
        'received_status',
        'catalog_item_id',
        'cin7_adjustment_id'
    ];
}
