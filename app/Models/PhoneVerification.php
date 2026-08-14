<?php

namespace App\Models;

use App\Enums\OtpPurpose;
use Illuminate\Database\Eloquent\Model;

class PhoneVerification extends Model
{
    protected $fillable = [
        'phone',
        'message_id',
        'purpose',
        'expires_at',
    ];

    protected $casts = [
        'purpose' => OtpPurpose::class,
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function isUsable(): bool
    {
        return $this->verified_at === null && ! $this->expires_at?->isPast();
    }
}
