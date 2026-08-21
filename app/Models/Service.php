<?php

namespace App\Models;

use App\Support\ArabicSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'image',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereHas('category', fn (Builder $q) => $q->where('is_active', true));
    }

    /**
     * Arabic-aware match on the service name and (folded) its category's name, so
     * typing a category surfaces the services under it. A name whose start matches
     * is ranked ahead of a mere containment. The category match rides a subquery
     * so no join is needed and the scope stays usable on any Service query.
     */
    public function scopeSearch(Builder $query, ?string $term, bool $withDescription = false): Builder
    {
        $term = ArabicSearch::normalize($term);

        if ($term === '') {
            return $query;
        }

        $contains = '%'.$term.'%';
        $prefix = $term.'%';
        $name = ArabicSearch::sql('name');

        return $query
            ->where(function (Builder $q) use ($name, $contains, $withDescription) {
                $q->whereRaw("{$name} LIKE ?", [$contains])
                    ->orWhereHas('category', fn (Builder $c) => $c->whereRaw(ArabicSearch::sql('name').' LIKE ?', [$contains]));

                if ($withDescription) {
                    $q->orWhereRaw(ArabicSearch::sql('description').' LIKE ?', [$contains]);
                }
            })
            ->orderByRaw("CASE WHEN {$name} LIKE ? THEN 0 ELSE 1 END", [$prefix]);
    }
}
