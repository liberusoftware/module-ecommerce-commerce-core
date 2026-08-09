<?php

namespace Liberu\Ecommerce\CommerceCore\Telemetry;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Liberu\Ecommerce\CommerceCore\Events\ChannelCreated;
use Liberu\Ecommerce\CommerceCore\Events\ChannelDomainAdded;
use Liberu\Ecommerce\CommerceCore\Events\ChannelDomainRemoved;
use Liberu\Ecommerce\CommerceCore\Events\ChannelStatusChanged;
use Liberu\Ecommerce\CommerceCore\Events\OrderNumberAllocated;
use Liberu\Ecommerce\CommerceCore\Events\PrimaryDomainChanged;
use Liberu\Ecommerce\CommerceCore\Events\StoreCapabilityChanged;
use Liberu\Ecommerce\CommerceCore\Events\StoreCreated;
use Liberu\Ecommerce\CommerceCore\Events\StoreSettingChanged;
use Liberu\Ecommerce\CommerceCore\Events\StoreStatusChanged;

/**
 * The module's telemetry: its own domain events, written as structured records.
 *
 * This is deliberately a *listener* and not an instrumentation layer. The module
 * consumes observability from the shared foundation rather than duplicating it —
 * so there is no metrics client here, no tracer, and no second logging stack.
 * What it adds is the one thing a foundation cannot supply: the vocabulary. An
 * application's log has no idea that a store moving to `suspended` is worth
 * finding, or that two order numbers with the same value would be an incident.
 *
 * **Off by default.** A merchant estate generates one of these per checkout, and
 * a package that starts writing to a deployment's log the moment it installs is
 * a package that decided somebody else's retention bill. The deployment turns it
 * on, and picks the channel.
 *
 * Everything logged here is already an event any listener could subscribe to.
 * That is the point of it being thin: a deployment wanting these in a metrics
 * backend subscribes to the same events and never touches this class.
 */
final class DomainEventLogger
{
    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            StoreCreated::class => 'onStoreCreated',
            StoreStatusChanged::class => 'onStoreStatusChanged',
            ChannelCreated::class => 'onChannelCreated',
            ChannelStatusChanged::class => 'onChannelStatusChanged',
            ChannelDomainAdded::class => 'onChannelDomainAdded',
            ChannelDomainRemoved::class => 'onChannelDomainRemoved',
            PrimaryDomainChanged::class => 'onPrimaryDomainChanged',
            StoreSettingChanged::class => 'onStoreSettingChanged',
            StoreCapabilityChanged::class => 'onStoreCapabilityChanged',
            OrderNumberAllocated::class => 'onOrderNumberAllocated',
        ];
    }

    public function onStoreCreated(StoreCreated $event): void
    {
        $this->record('store.created', [
            'store_id' => $event->store->id,
            'team_id' => $event->store->team_id,
            'slug' => $event->store->slug,
        ]);
    }

    /**
     * Both ends of the move, and a level that reflects what it means.
     *
     * A store leaving `active` stops serving shoppers. That is an operational
     * event somebody should be able to alert on without parsing a message
     * string, which is why it is a warning and a creation is not.
     */
    public function onStoreStatusChanged(StoreStatusChanged $event): void
    {
        $this->record('store.status_changed', [
            'store_id' => $event->store->id,
            'from' => $event->from->value,
            'to' => $event->to->value,
        ], $event->from->isServing() && ! $event->to->isServing() ? 'warning' : 'info');
    }

    public function onChannelCreated(ChannelCreated $event): void
    {
        $this->record('channel.created', [
            'channel_id' => $event->channel->id,
            'store_id' => $event->channel->store_id,
        ]);
    }

    public function onChannelStatusChanged(ChannelStatusChanged $event): void
    {
        $this->record('channel.status_changed', [
            'channel_id' => $event->channel->id,
            'store_id' => $event->channel->store_id,
            'from' => $event->from->value,
            'to' => $event->to->value,
        ], $event->from->isServing() && ! $event->to->isServing() ? 'warning' : 'info');
    }

    public function onChannelDomainAdded(ChannelDomainAdded $event): void
    {
        $this->record('channel_domain.added', [
            'domain_id' => $event->domain->id,
            'channel_id' => $event->domain->channel_id,
            'host' => $event->domain->host,
            'is_primary' => $event->domain->is_primary,
        ]);
    }

    public function onChannelDomainRemoved(ChannelDomainRemoved $event): void
    {
        $this->record('channel_domain.removed', [
            'channel_id' => $event->channelId,
            'host' => $event->host,
        ], 'warning');
    }

    /**
     * Every canonical URL the storefront generates changes with this, so it is
     * the record somebody reaches for when search traffic moves.
     */
    public function onPrimaryDomainChanged(PrimaryDomainChanged $event): void
    {
        $this->record('channel_domain.primary_changed', [
            'domain_id' => $event->domain->id,
            'channel_id' => $event->domain->channel_id,
            'host' => $event->domain->host,
            'previous_host' => $event->previousHost,
        ], 'warning');
    }

    /**
     * The key and whether it changed, never the values.
     *
     * A setting holds whatever the writing module put there, which on some keys
     * is a credential or a customer-facing address. Logging the value would put
     * it in a store with different retention and different access than the
     * table it came from.
     */
    public function onStoreSettingChanged(StoreSettingChanged $event): void
    {
        $this->record('store_setting.changed', [
            'store_id' => $event->storeId,
            'key' => $event->key,
            'had_previous_value' => $event->from !== null,
            'cleared' => $event->to === null,
        ]);
    }

    public function onStoreCapabilityChanged(StoreCapabilityChanged $event): void
    {
        $this->record('store_capability.changed', [
            'store_id' => $event->storeId,
            'capability' => $event->capability->value,
            'enabled' => $event->enabled,
        ]);
    }

    public function onOrderNumberAllocated(OrderNumberAllocated $event): void
    {
        $this->record('order_number.allocated', [
            'store_id' => $event->storeId,
            'number' => $event->number,
            'sequence_value' => $event->sequenceValue,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function record(string $event, array $context, string $level = 'info'): void
    {
        if (! config('commerce-core.telemetry.enabled')) {
            return;
        }

        Log::channel(config('commerce-core.telemetry.channel'))
            ->log($level, 'commerce-core.'.$event, $context + ['event' => 'commerce-core.'.$event]);
    }
}
