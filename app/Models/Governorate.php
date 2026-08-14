<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Governorate extends Model
{
    protected $fillable = [
        'name',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function technicians(): HasMany
    {
        return $this->hasMany(Technician::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
