<?php

namespace App\Models;

use App\Traits\ModelCamelCase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @property OrderItem[]|Collection $items
*/
class Order extends Model
{
    use ModelCamelCase;

    protected $fillable = [
        'project_id',
        'team_id',
        'prepared_by_id',
        'accepted_by_member_id',
        'signature_id',
        'status',
        'total_cost',
        'author_type',
        'author_id'
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
