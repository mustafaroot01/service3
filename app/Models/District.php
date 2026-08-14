<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    protected $fillable = [
        'governorate_id',
        'name',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
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
        return $query->where('is_active', true)
            ->whereHas('governorate', fn (Builder $q) => $q->where('is_active', true));
    }
}
