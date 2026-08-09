<?php

namespace Liberu\Ecommerce\CommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Liberu\Ecommerce\CommerceCore\Actions\AllocateOrderNumber;

/**
 * The counter one store's order numbers come from.
 *
 * Allocation is {@see AllocateOrderNumber}'s
 * job, not this model's: incrementing safely needs a transaction and a row
 * lock, and a model method invites a caller to do it without either.
 */
class OrderNumberSequence extends Model
{
    protected $table = 'ecommerce_commerce_core_order_sequences';

    protected $fillable = ['store_id', 'prefix', 'next_number', 'pad_to'];

    protected $casts = [
        'next_number' => 'integer',
        'pad_to' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** How a number is spelled once allocated. */
    public function format(int $number): string
    {
        return $this->prefix.str_pad((string) $number, $this->pad_to, '0', STR_PAD_LEFT);
    }
}
