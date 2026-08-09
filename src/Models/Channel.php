<?php

namespace Liberu\Ecommerce\CommerceCore\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Liberu\Ecommerce\CommerceCore\Database\Factories\ChannelFactory;
use Liberu\Ecommerce\CommerceCore\Enums\ChannelStatus;

/**
 * One way into a Store — a set of hostnames and the theme they render with.
 */
class Channel extends Model
{
    use HasFactory;

    protected $fillable = ['store_id', 'name', 'theme', 'status', 'currency', 'locale'];

    protected $casts = ['status' => ChannelStatus::class];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(ChannelDomain::class);
    }

    /**
     * The hostname canonical URLs are built from, which is not necessarily the
     * one the request arrived on.
     */
    public function primaryDomain(): ?ChannelDomain
    {
        return $this->domains()->where('is_primary', true)->first()
            ?? $this->domains()->first();
    }

    /**
     * A channel serves only when its store does.
     *
     * Asked as one question rather than two so that no caller checks the
     * channel, forgets the store, and serves a suspended merchant's catalogue
     * from a channel nobody thought to disable.
     */
    public function isServing(): bool
    {
        return $this->status->isServing() && $this->store->status->isServing();
    }

    /** @param  Builder<self>  $query */
    public function scopeServing(Builder $query): void
    {
        $query->where('status', ChannelStatus::Active)
            ->whereHas('store', fn (Builder $store) => $store->serving());
    }

    protected static function newFactory(): Factory
    {
        return ChannelFactory::new();
    }
}
