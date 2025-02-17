<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Support\Str;

trait ModelCamelCase
{
    public static function convertCamelToSnake(array $attributes)
    {
        $attrs = [];

        foreach ($attributes as $key => $value) {
            $attrs[Str::snake($key)] = $value;
        }

        return $attrs;
    }

    public function getAttribute($key)
    {
        return parent::getAttribute(Str::snake($key));
    }

    public function setAttribute($key, $value)
    {
        return parent::setAttribute(Str::snake($key), $value);
    }

    public function fill(array $attributes)
    {
        parent::fill(self::convertCamelToSnake($attributes));

        return $this;
    }
}
