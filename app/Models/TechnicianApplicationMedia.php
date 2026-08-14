<?php

namespace App\Models;

use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicianApplicationMedia extends Model
{
    protected $table = 'technician_application_media';

    protected $fillable = [
        'application_id',
        'type',
        'path',
        'sort',
    ];

    protected $casts = [
        'type' => MediaType::class,
        'sort' => 'integer',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(TechnicianApplication::class, 'application_id');
    }
}
