<?php

namespace App\Models;

use App\Traits\ModelCamelCase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @property File[]|Collection $files
 * @property ProductBarcode[]|Collection $barcodes
*/
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

    public function files()
    {
        return $this->belongsToMany(File::class, 'product_file');
    }

    public function barcodes()
    {
        return $this->hasMany(ProductBarcode::class, 'product_id');
    }
}
