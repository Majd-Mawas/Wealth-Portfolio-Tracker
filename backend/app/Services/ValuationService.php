<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class ValuationService
{
    public const int SCALE = 8;

    /**
     * @param  Collection<int, Asset>  $assets
     * @return Collection<int, Asset>
     */
    public function decorateAssets(Collection $assets): Collection
    {
        $quantities = $this->quantitiesFor($assets);

        foreach ($assets as $asset) {
            $quantity = $quantities[$asset->getKey()] ?? BigDecimal::zero();
            $value = $quantity->multipliedBy($asset->current_price);

            $asset->setAttribute('calculated_quantity', $this->format($quantity));
            $asset->setAttribute('calculated_value', $this->format($value));
        }

        return $assets;
    }

    public function quantityFor(Asset $asset): BigDecimal
    {
        $transactions = Transaction::query()
            ->where('asset_id', $asset->getKey())
            ->get(['quantity_delta']);

        return $transactions->reduce(
            fn (BigDecimal $quantity, Transaction $transaction): BigDecimal => $quantity->plus($transaction->quantity_delta),
            BigDecimal::zero(),
        );
    }

    /** @return array<string, mixed> */
    public function dashboard(User $actor, Portfolio $portfolio): array
    {
        Gate::forUser($actor)->authorize('view', $portfolio);

        $assets = $portfolio->assets()
            ->whereNull('archived_at')
            ->orderBy('id')
            ->get();

        $this->decorateAssets($assets);

        $total = BigDecimal::zero();
        /** @var array<string, BigDecimal> $typeValues */
        $typeValues = [];
        /** @var array<int, BigDecimal> $assetValues */
        $assetValues = [];

        foreach ($assets as $asset) {
            $value = BigDecimal::of($asset->getAttribute('calculated_quantity'))
                ->multipliedBy($asset->current_price);
            $assetValues[$asset->getKey()] = $value;
            $total = $total->plus($value);

            $type = $asset->type->value;
            $typeValues[$type] = ($typeValues[$type] ?? BigDecimal::zero())->plus($value);
        }

        $allocationByType = collect($typeValues)
            ->map(fn (BigDecimal $value, string $type): array => [
                'type' => $type,
                'value' => $this->format($value),
                'percentage' => $this->percentage($value, $total),
            ])
            ->sortByDesc('value', SORT_NATURAL)
            ->values()
            ->all();

        return [
            'portfolio' => [
                'id' => $portfolio->getKey(),
                'name' => $portfolio->name,
                'base_currency' => $portfolio->base_currency,
            ],
            'total_value' => $this->format($total),
            'valued_at' => now()->toISOString(),
            'allocation_by_type' => $allocationByType,
            'assets' => $assets->map(fn (Asset $asset): array => [
                'asset_id' => $asset->getKey(),
                'name' => $asset->name,
                'type' => $asset->type->value,
                'quantity' => $asset->getAttribute('calculated_quantity'),
                'current_price' => $asset->current_price,
                'value' => $this->format($assetValues[$asset->getKey()]),
                'percentage' => $this->percentage($assetValues[$asset->getKey()], $total),
                'price_as_of' => $asset->price_as_of?->toISOString(),
                'price_status' => 'manual',
            ])->all(),
        ];
    }

    public function format(BigDecimal $value): string
    {
        return $value->toScale(self::SCALE, RoundingMode::HalfEven)->toString();
    }

    private function percentage(BigDecimal $value, BigDecimal $total): string
    {
        if ($total->isZero()) {
            return $this->format(BigDecimal::zero());
        }

        return $value
            ->multipliedBy('100')
            ->dividedBy($total, self::SCALE, RoundingMode::HalfEven)
            ->toString();
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @return array<int, BigDecimal>
     */
    private function quantitiesFor(Collection $assets): array
    {
        $quantities = [];

        foreach ($assets as $asset) {
            $quantities[$asset->getKey()] = BigDecimal::zero();
        }

        if ($assets->isEmpty()) {
            return $quantities;
        }

        $transactions = Transaction::query()
            ->whereIn('asset_id', $assets->map(fn (Asset $asset) => $asset->getKey())->all())
            ->get(['asset_id', 'quantity_delta']);

        foreach ($transactions as $transaction) {
            $quantities[$transaction->asset_id] = $quantities[$transaction->asset_id]
                ->plus($transaction->quantity_delta);
        }

        return $quantities;
    }
}
