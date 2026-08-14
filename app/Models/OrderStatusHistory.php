<?php

namespace App\Models;

use App\Enums\ActorType;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'actor_type',
        'actor_id',
        'note',
        'created_at',
    ];

    protected $casts = [
        'from_status' => OrderStatus::class,
        'to_status' => OrderStatus::class,
        'actor_type' => ActorType::class,
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    private static array $actorNames = [];

    public function actorName(): ?string
    {
        if ($this->actor_id === null || $this->actor_type === ActorType::SYSTEM) {
            return null;
        }

        $key = $this->actor_type->value.':'.$this->actor_id;

        return self::$actorNames[$key] ??= match ($this->actor_type) {
            ActorType::ADMIN => Admin::find($this->actor_id)?->name,
            ActorType::USER => User::find($this->actor_id)?->name,
            ActorType::SYSTEM => null,
        };
    }

    public static function forgetActorNames(): void
    {
        self::$actorNames = [];
    }

    /**
     * Resolves every actor name in one query per actor type, so rendering a
     * page of history rows never scales its query count with the row count.
     */
    public static function primeActorNames(iterable $histories): void
    {
        $wanted = [];

        foreach ($histories as $history) {
            if ($history->actor_id === null || $history->actor_type === ActorType::SYSTEM) {
                continue;
            }

            $key = $history->actor_type->value.':'.$history->actor_id;

            if (! isset(self::$actorNames[$key])) {
                $wanted[$history->actor_type->value][] = $history->actor_id;
            }
        }

        foreach ($wanted as $type => $ids) {
            $model = match (ActorType::from($type)) {
                ActorType::ADMIN => Admin::class,
                ActorType::USER => User::class,
                ActorType::SYSTEM => null,
            };

            if (! $model) {
                continue;
            }

            foreach ($model::whereKey(array_unique($ids))->pluck('name', 'id') as $id => $name) {
                self::$actorNames[$type.':'.$id] = $name;
            }
        }
    }
}
