<?php

namespace Database\Factories;

use App\Enums\TransactionKind;
use App\Enums\TransactionSource;
use App\Models\Asset;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'created_by' => User::factory(),
            'kind' => TransactionKind::Buy,
            'quantity_delta' => '1.00000000',
            'unit_price' => '100.00000000',
            'fees' => '0.00000000',
            'occurred_at' => now(),
            'note' => null,
            'source' => TransactionSource::Api,
            'reversal_of_id' => null,
        ];
    }

    public function forAsset(Asset $asset): static
    {
        return $this->state(fn (): array => ['asset_id' => $asset->getKey()]);
    }
}
