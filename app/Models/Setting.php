<?php

namespace App\Models;

use App\Enums\SettingKey;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected $hidden = [
        'value',
    ];

    protected $casts = [
        'key' => SettingKey::class,
        'value' => 'encrypted',
    ];
}
