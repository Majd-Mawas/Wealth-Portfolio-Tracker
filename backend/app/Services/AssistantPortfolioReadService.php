<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AssistantPortfolioReadService
{
    public function __construct(private readonly ValuationService $valuations) {}

    /** @return array<string, mixed> */
    public function summary(User $actor, Portfolio $portfolio): array
    {
        $dashboard = $this->valuations->dashboard($actor, $portfolio);

        return [
            'portfolio' => $dashboard['portfolio'],
            'total_value' => $dashboard['total_value'],
            'valued_at' => $dashboard['valued_at'],
            'price_basis' => 'manual',
            'allocation_by_type' => $dashboard['allocation_by_type'],
            'asset_count' => count($dashboard['assets']),
        ];
    }

    /** @return array<string, mixed> */
    public function assets(
        User $actor,
        Portfolio $portfolio,
        ?string $type,
        int $limit,
    ): array {
        Gate::forUser($actor)->authorize('view', $portfolio);

        $assets = $portfolio->assets()
            ->whereNull('archived_at')
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $this->valuations->decorateAssets($assets);

        return [
            'portfolio_id' => $portfolio->getKey(),
            'price_basis' => 'manual',
            'assets' => $assets->map(fn (Asset $asset): array => [
                'id' => $asset->getKey(),
                'name' => $asset->name,
                'type' => $asset->type->value,
                'symbol' => $asset->symbol,
                'currency' => $asset->currency,
                'quantity' => $asset->getAttribute('calculated_quantity'),
                'current_price' => $asset->current_price,
                'price_as_of' => $asset->price_as_of?->toISOString(),
                'value' => $asset->getAttribute('calculated_value'),
            ])->values()->all(),
            'truncated' => $assets->count() === $limit,
        ];
    }

    /** @return array<string, mixed> */
    public function transactions(
        User $actor,
        Portfolio $portfolio,
        ?int $assetId,
        ?string $kind,
        int $limit,
    ): array {
        Gate::forUser($actor)->authorize('view', $portfolio);

        $transactions = Transaction::query()
            ->select([
                'transactions.id',
                'transactions.asset_id',
                'transactions.kind',
                'transactions.quantity_delta',
                'transactions.unit_price',
                'transactions.fees',
                'transactions.occurred_at',
                'transactions.note',
                'transactions.source',
                'transactions.reversal_of_id',
            ])
            ->whereHas('asset', fn ($query) => $query->where('portfolio_id', $portfolio->getKey()))
            ->with('asset:id,name,symbol,type')
            ->when($assetId !== null, fn ($query) => $query->where('asset_id', $assetId))
            ->when($kind !== null, fn ($query) => $query->where('kind', $kind))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return [
            'portfolio_id' => $portfolio->getKey(),
            'transactions' => $transactions->map(fn (Transaction $transaction): array => [
                'id' => $transaction->getKey(),
                'asset' => [
                    'id' => $transaction->asset->getKey(),
                    'name' => $transaction->asset->name,
                    'symbol' => $transaction->asset->symbol,
                    'type' => $transaction->asset->type->value,
                ],
                'kind' => $transaction->kind->value,
                'quantity_delta' => $transaction->quantity_delta,
                'unit_price' => $transaction->unit_price,
                'fees' => $transaction->fees,
                'occurred_at' => $transaction->occurred_at?->toISOString(),
                'note' => $transaction->note,
                'source' => $transaction->source->value,
                'reversal_of_id' => $transaction->reversal_of_id,
            ])->values()->all(),
            'truncated' => $transactions->count() === $limit,
        ];
    }
}
