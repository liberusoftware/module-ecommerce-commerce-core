<?php

namespace Liberu\Ecommerce\CommerceCore\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Liberu\Ecommerce\CommerceCore\Data\ChannelData;
use Liberu\Ecommerce\CommerceCore\Models\Channel;

final class ChannelQuery
{
    /** @return LengthAwarePaginator<int, ChannelData> */
    public function paginateForStore(int $storeId, int $perPage = 25): LengthAwarePaginator
    {
        return Channel::query()
            ->where('store_id', $storeId)
            ->with('domains')
            ->orderBy('id')
            ->paginate($perPage)
            ->through(ChannelData::from(...));
    }

    public function find(int $id): ?ChannelData
    {
        $channel = Channel::query()->with('domains')->find($id);

        return $channel === null ? null : ChannelData::from($channel);
    }
}
