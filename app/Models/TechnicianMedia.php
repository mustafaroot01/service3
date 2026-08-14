<?php

namespace App\Models;

use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicianMedia extends Model
{
    protected $table = 'technician_media';

    protected $fillable = [
        'technician_id',
        'type',
        'path',
        'sort',
    ];

    protected $casts = [
        'type' => MediaType::class,
        'sort' => 'integer',
    ];

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }
}
