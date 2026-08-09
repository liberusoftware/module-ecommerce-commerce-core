<?php

namespace Liberu\Ecommerce\CommerceCore\Actions;

use Liberu\Ecommerce\CommerceCore\Enums\ChannelStatus;
use Liberu\Ecommerce\CommerceCore\Events\ChannelStatusChanged;
use Liberu\Ecommerce\CommerceCore\Exceptions\InvalidStatusTransition;
use Liberu\Ecommerce\CommerceCore\Models\Channel;

final class ChangeChannelStatus
{
    public function handle(Channel $channel, ChannelStatus $to): Channel
    {
        $from = $channel->status;

        if ($from === $to) {
            return $channel;
        }

        if (! $from->canTransitionTo($to)) {
            throw InvalidStatusTransition::between('channel', $from->value, $to->value);
        }

        $channel->forceFill(['status' => $to])->save();

        ChannelStatusChanged::dispatch($channel, $from, $to);

        return $channel;
    }
}
