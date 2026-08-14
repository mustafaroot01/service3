<?php

namespace App\Models;

use App\Enums\MediaType;
use App\Enums\TechnicianSource;
use App\Enums\TechnicianStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Technician extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'governorate_id',
        'district_id',
        'status',
        'source',
    ];

    protected $casts = [
        'status' => TechnicianStatus::class,
        'source' => TechnicianSource::class,
    ];

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function specializations(): BelongsToMany
    {
        return $this->belongsToMany(Specialization::class, 'technician_specialization');
    }

    public function media(): HasMany
    {
        return $this->hasMany(TechnicianMedia::class)->orderBy('type')->orderBy('sort');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function appNotifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    public function scopeAssignableTo(Builder $query, int $governorateId): Builder
    {
        return $query->where('status', TechnicianStatus::ACTIVE)
            ->where('governorate_id', $governorateId);
    }

    /**
     * @return Collection<int, TechnicianMedia>
     */
    public function mediaOfType(MediaType $type): Collection
    {
        return $this->media->where('type', $type)->values();
    }

    public function missingDocuments(): array
    {
        $present = $this->media->pluck('type')->all();

        return array_values(array_filter(
            MediaType::requiredDocuments(),
            fn (MediaType $type) => ! in_array($type, $present, true)
        ));
    }

    public function hasCompleteDocuments(): bool
    {
        return $this->missingDocuments() === [];
    }
}
