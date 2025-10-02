<?php

namespace App\Enums;

trait ListsTraits
{
    public static function names()
    {
        return array_column(static::cases(), 'name');
    }
}