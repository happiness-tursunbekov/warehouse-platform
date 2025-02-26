<?php

namespace App\Models;

use App\Traits\ModelCamelCase;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use ModelCamelCase;

    protected $casts = [
        'value' => 'array'
    ];
    
    const NOT_ALLOWED_CATEGORIES = 'not-allowed-categories';

    public static function getBySlug($slug) : ?self
    {
        return self::where('slug', $slug)->first();
    }
}
