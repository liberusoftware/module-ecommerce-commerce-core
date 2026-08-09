<?php

namespace Liberu\Ecommerce\CommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One per-store setting.
 *
 * Deliberately not a settings *class* with typed properties: the readers are
 * other modules, each wanting its own keys, and a class here would have to
 * know all of them. The key namespace is the writer's — `checkout.terms_url`,
 * `shipping.free_over` — and this table only guarantees that a key means one
 * thing per store.
 */
class StoreSetting extends Model
{
    protected $table = 'ecommerce_commerce_core_settings';

    protected $fillable = ['store_id', 'key', 'value'];

    protected $casts = ['value' => 'array'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
