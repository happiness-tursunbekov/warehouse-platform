<?php

namespace App\Models;

use App\Traits\ModelCamelCase;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use ModelCamelCase;

    protected $fillable = [
        'id',
        'on_hand',
        'inactive_flag',
        'on_hand_available',
        'identifier',
        'description'
    ];

    public function receive(int $qty): bool
    {
        return $this->fill([
            'onHand' => $this->onHand += $qty,
            'onHandAvailable' => $this->onHandAvailable += $qty
        ])->save();
    }
}
