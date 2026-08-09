<?php

namespace Liberu\Ecommerce\CommerceCore\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Liberu\Ecommerce\CommerceCore\Data\StoreData;
use Liberu\Ecommerce\CommerceCore\Models\Store;

/**
 * The read side of stores, for consumers outside this module.
 *
 * Returns {@see StoreData}, never models, so a presentation package can render
 * a store without importing one — which is the boundary rule an `-api` adapter
 * is held to, and a sensible discipline for the others.
 *
 * Scoping is by team and explicit. A query object with an optional tenant is a
 * query object somebody will call without one; passing null has to be a choice
 * somebody typed, and the two call sites that legitimately need it are a
 * console command and the host's own composition.
 */
final class StoreQuery
{
    /** @return LengthAwarePaginator<int, StoreData> */
    public function paginate(?int $teamId, int $perPage = 25): LengthAwarePaginator
    {
        return Store::query()
            ->when($teamId !== null, fn ($query) => $query->where('team_id', $teamId))
            ->with('capabilities')
            ->orderBy('id')
            ->paginate($perPage)
            ->through(StoreData::from(...));
    }

    public function find(int $id): ?StoreData
    {
        $store = Store::query()->with('capabilities')->find($id);

        return $store === null ? null : StoreData::from($store);
    }

    public function findBySlug(string $slug): ?StoreData
    {
        $store = Store::query()->with('capabilities')->where('slug', $slug)->first();

        return $store === null ? null : StoreData::from($store);
    }
}
