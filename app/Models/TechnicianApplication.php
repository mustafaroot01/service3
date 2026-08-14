<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TechnicianApplication extends Model
{
    protected $guarded = [
        'id',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'status' => ApplicationStatus::class,
        'reviewed_at' => 'datetime',
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
        return $this->belongsToMany(Specialization::class, 'technician_application_specialization', 'application_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(TechnicianApplicationMedia::class, 'application_id')
            ->orderBy('type')
            ->orderBy('sort');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    /** Everything still on file is open — accepted applications are removed. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [ApplicationStatus::PENDING, ApplicationStatus::UNDER_REVIEW]);
    }

    /**
     * @return Collection<int, TechnicianApplicationMedia>
     */
    public function mediaOfType(MediaType $type): Collection
    {
        return $this->media->where('type', $type)->values();
    }

    /**
     * @return array<int, MediaType>
     */
    public function missingDocuments(): array
    {
        $present = $this->media->pluck('type')->all();

        return array_values(array_filter(
            MediaType::requiredDocuments(),
            fn (MediaType $type) => ! in_array($type, $present, true)
        ));
    }
}
