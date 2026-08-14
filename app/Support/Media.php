<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class Media
{
    public static function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
