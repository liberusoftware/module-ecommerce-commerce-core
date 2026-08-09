<?php

namespace Liberu\Ecommerce\CommerceCore\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Liberu\Ecommerce\CommerceCore\Models\Store;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'team_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 100000),
        ];
    }
}
