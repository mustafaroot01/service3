<?php

namespace App\Models;

use App\Enums\LegalPageKey;
use Illuminate\Database\Eloquent\Model;

class LegalPage extends Model
{
    protected $fillable = [
        'key',
        'title',
        'content',
    ];

    protected $casts = [
        'key' => LegalPageKey::class,
    ];
}
