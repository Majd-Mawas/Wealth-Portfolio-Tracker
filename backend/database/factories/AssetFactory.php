<?php

namespace Database\Factories;

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Portfolio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'portfolio_id' => Portfolio::factory(),
            'type' => AssetType::Stock,
            'name' => fake()->company(),
            'symbol' => strtoupper(fake()->lexify('???')),
            'currency' => 'USD',
            'current_price' => '100.00000000',
            'price_as_of' => now(),
            'metadata' => null,
        ];
    }

    public function cash(): static
    {
        return $this->state(fn (): array => [
            'type' => AssetType::Cash,
            'name' => 'Cash',
            'symbol' => null,
            'current_price' => '1.00000000',
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['archived_at' => now()]);
    }
}
