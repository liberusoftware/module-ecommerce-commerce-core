<?php

namespace Liberu\Ecommerce\CommerceCore\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Liberu\Ecommerce\CommerceCore\Database\Factories\StoreFactory;
use Liberu\Ecommerce\CommerceCore\Enums\Capability;
use Liberu\Ecommerce\CommerceCore\Enums\StoreStatus;

/**
 * A storefront's worth of commerce data, owned by a team.
 *
 * The team model is resolved from configuration at call time and never
 * imported — see ADR 0006. A module that names `App\Models\Team` in a `use`
 * statement installs into exactly one application.
 */
class Store extends Model
{
    use HasFactory;

    protected $fillable = ['team_id', 'name', 'slug', 'status', 'currency', 'locale', 'timezone'];

    protected $casts = [
        'status' => StoreStatus::class,
        'archived_at' => 'immutable_datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(config('commerce-core.team_model'));
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(StoreSetting::class);
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(StoreCapability::class);
    }

    public function orderSequences(): HasMany
    {
        return $this->hasMany(OrderNumberSequence::class);
    }

    /**
     * Whether a capability is on.
     *
     * Absence is the default rather than an error, so a capability added in a
     * later release reads as off for every store that predates it instead of
     * throwing across the estate on deploy.
     */
    public function allows(Capability $capability): bool
    {
        $granted = $this->relationLoaded('capabilities')
            ? $this->capabilities->firstWhere('capability', $capability)
            : $this->capabilities()->where('capability', $capability->value)->first();

        return $granted?->enabled ?? $capability->defaultEnabled();
    }

    /** @param  Builder<self>  $query */
    public function scopeServing(Builder $query): void
    {
        $query->where('status', StoreStatus::Active);
    }

    protected static function newFactory(): Factory
    {
        return StoreFactory::new();
    }
}
