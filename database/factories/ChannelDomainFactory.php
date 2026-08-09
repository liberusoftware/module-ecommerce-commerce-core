<?php

namespace Liberu\Ecommerce\CommerceCore\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Liberu\Ecommerce\CommerceCore\Models\Channel;
use Liberu\Ecommerce\CommerceCore\Models\ChannelDomain;

/**
 * @extends Factory<ChannelDomain>
 */
class ChannelDomainFactory extends Factory
{
    protected $model = ChannelDomain::class;

    public function definition(): array
    {
        return [
            'channel_id' => Channel::factory(),
            'host' => $this->faker->unique()->domainName(),
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
