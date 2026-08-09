<?php

namespace Liberu\Ecommerce\CommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Liberu\Ecommerce\CommerceCore\Enums\Capability;

/**
 * A store's decision about one capability.
 *
 * A row means somebody decided. No row means nobody has, which is not the same
 * thing and is why {@see Store::allows()} falls back to the capability's own
 * default rather than treating a missing row as `false` by accident.
 *
 * @property int $id
 * @property int $store_id
 * @property Capability $capability
 * @property bool $enabled
 */
class StoreCapability extends Model
{
    protected $table = 'ecommerce_commerce_core_capabilities';

    protected $fillable = ['store_id', 'capability', 'enabled'];

    protected $casts = [
        'capability' => Capability::class,
        'enabled' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
