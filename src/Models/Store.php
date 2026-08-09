<?php

namespace Liberu\Ecommerce\CommerceCore\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Liberu\Ecommerce\CommerceCore\Database\Factories\StoreFactory;

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

    protected $fillable = ['team_id', 'name', 'slug'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(config('commerce-core.team_model'));
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    protected static function newFactory(): Factory
    {
        return StoreFactory::new();
    }
}
