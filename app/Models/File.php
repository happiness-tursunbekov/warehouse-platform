<?php

namespace App\Models;

use App\Traits\ModelCamelCase;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use ModelCamelCase;

    protected $fillable = [
        'path',
        'type'
    ];
}
