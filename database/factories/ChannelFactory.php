<?php

namespace Liberu\Ecommerce\CommerceCore\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Liberu\Ecommerce\CommerceCore\Models\Channel;
use Liberu\Ecommerce\CommerceCore\Models\Store;

/**
 * @extends Factory<Channel>
 */
class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => 'Web',
            'theme' => 'theme-ecommerce',
        ];
    }
}
