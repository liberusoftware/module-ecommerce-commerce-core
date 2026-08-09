<?php

namespace Liberu\Ecommerce\CommerceCore\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Liberu\Ecommerce\CommerceCore\Enums\StoreStatus;
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
            // Active, unlike the action's default. A test that wanted a draft
            // says so; the rest want a store that behaves like a real one, and
            // making every test call ->active() first is friction with no reader.
            'status' => StoreStatus::Active,
            'currency' => 'USD',
            'locale' => 'en',
            'timezone' => 'UTC',
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => StoreStatus::Active]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => StoreStatus::Draft]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => StoreStatus::Suspended]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => StoreStatus::Archived, 'archived_at' => now()]);
    }

    public function ownedBy(int $teamId): static
    {
        return $this->state(fn () => ['team_id' => $teamId]);
    }
}
