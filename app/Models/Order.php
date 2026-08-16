<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;

class Order extends Model
{
    protected $guarded = [
        'id',
        'order_number',
        'status',
        'inspection_note',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'scheduled_date' => 'date',
        'time_from' => 'datetime:H:i',
        'time_to' => 'datetime:H:i',
        'cancelled_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(OrderImage::class)->orderBy('sort');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('technician_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [OrderStatus::COMPLETED, OrderStatus::CANCELLED]);
    }

    /** A visit that ends earlier on the clock than it starts runs into the next day. */
    public function visitEndsNextDay(): bool
    {
        if (! $this->time_from || ! $this->time_to) {
            return false;
        }

        return $this->time_to->format('H:i') < $this->time_from->format('H:i');
    }

    /**
     * The daily counter, read under a lock.
     *
     * A plain read cannot be used: MySQL runs on REPEATABLE READ, so inside the
     * order transaction it returns the snapshot taken when the transaction
     * opened — a number another request has just committed stays invisible and
     * the retry below recomputes the same value forever. The locking read sees
     * the latest committed row and holds the range until this order commits,
     * so two customers ordering at the same instant queue instead of colliding.
     *
     * The sequence is read as a number rather than the last four characters, so
     * a day that passes 9999 keeps counting instead of wrapping back to 0001.
     */
    public static function nextNumber(): string
    {
        $prefix = 'HS-'.now()->format('ymd');

        $sequence = (int) static::where('order_number', 'like', $prefix.'-%')
            ->lockForUpdate()
            ->selectRaw("COALESCE(MAX(CAST(SUBSTRING_INDEX(order_number, '-', -1) AS UNSIGNED)), 0) AS seq")
            ->value('seq');

        return $prefix.'-'.str_pad((string) ($sequence + 1), 4, '0', STR_PAD_LEFT);
    }

    public static function createWithNumber(array $attributes, int $attempts = 5): self
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $order = new static($attributes);
                $order->order_number = static::nextNumber();
                $order->save();

                return $order;
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= $attempts) {
                    throw $e;
                }
            }
        }
    }
}
