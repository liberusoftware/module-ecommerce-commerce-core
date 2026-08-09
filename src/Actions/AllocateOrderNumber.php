<?php

namespace Liberu\Ecommerce\CommerceCore\Actions;

use Illuminate\Support\Facades\DB;
use Liberu\Ecommerce\CommerceCore\Events\OrderNumberAllocated;
use Liberu\Ecommerce\CommerceCore\Models\OrderNumberSequence;
use Liberu\Ecommerce\CommerceCore\Models\Store;

/**
 * Hand out the next order number for a store, exactly once.
 *
 * The whole point is the row lock. Two checkouts completing in the same
 * millisecond both read the same `next_number` without one, and two orders
 * carrying the same number is the kind of fault that is found by a customer
 * rather than by a test.
 *
 * A gap is fine and a duplicate is not, which is why the number is spent on
 * allocation rather than on the order being saved: a failed checkout burning a
 * number costs nothing, and holding the lock until the order commits would put
 * the payment gateway's latency inside a lock every other checkout waits on.
 */
final class AllocateOrderNumber
{
    public function handle(Store $store, string $prefix = ''): string
    {
        return DB::transaction(function () use ($store, $prefix) {
            $sequence = OrderNumberSequence::query()
                ->where('store_id', $store->getKey())
                ->where('prefix', $prefix)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $sequence = OrderNumberSequence::query()->create([
                    'store_id' => $store->getKey(),
                    'prefix' => $prefix,
                ]);
            }

            $value = (int) $sequence->next_number;
            $number = $sequence->format($value);

            $sequence->forceFill(['next_number' => $value + 1])->save();

            OrderNumberAllocated::dispatch((int) $store->getKey(), $number, $value);

            return $number;
        });
    }
}
