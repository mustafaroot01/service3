<?php

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'gender',
        'phone',
        'password',
        'governorate_id',
        'district_id',
        'terms_accepted_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'status' => UserStatus::class,
        'phone_verified_at' => 'datetime',
        'deletion_requested_at' => 'datetime',
        'terms_accepted_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function appNotifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }
}
