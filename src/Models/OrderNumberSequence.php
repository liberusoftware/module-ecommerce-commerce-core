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
 *
 * @property int $id
 * @property int $store_id
 * @property string $prefix
 * @property int $next_number
 * @property int $pad_to
 */
class OrderNumberSequence extends Model
{
    protected $table = 'ecommerce_commerce_core_order_sequences';

    protected $fillable = ['store_id', 'prefix', 'next_number', 'pad_to'];

    /**
     * The starting shape of a sequence, on the model rather than only in the
     * migration.
     *
     * A column default is applied by the database and never read back, so a
     * freshly `create()`d row holds nulls in memory while holding 1 and 6 on
     * disk — and the first number allocated formats from the nulls. The column
     * defaults stay as the backstop for rows this class did not insert.
     */
    protected $attributes = [
        'prefix' => '',
        'next_number' => 1,
        'pad_to' => 6,
    ];

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
