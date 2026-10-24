<?php

namespace App\Services;

use App\Enums\AssetType;
use App\Enums\TransactionKind;
use App\Exceptions\ApiException;
use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AssetService
{
    public function __construct(
        private readonly TransactionService $transactions,
        private readonly ValuationService $valuations,
    ) {}

    /** @param array{type?: string, include_archived?: bool} $filters */
    public function paginate(User $actor, Portfolio $portfolio, array $filters): CursorPaginator
    {
        Gate::forUser($actor)->authorize('view', $portfolio);

        $paginator = $portfolio->assets()
            ->when(isset($filters['type']), fn ($query) => $query->where('type', $filters['type']))
            ->when(! ($filters['include_archived'] ?? false), fn ($query) => $query->whereNull('archived_at'))
            ->orderBy('id')
            ->cursorPaginate(15);

        $this->valuations->decorateAssets(collect($paginator->items()));

        return $paginator;
    }

    /**
     * @param  array{
     *     type: string,
     *     name: string,
     *     symbol?: ?string,
     *     currency: string,
     *     current_price: string,
     *     price_as_of: string,
     *     metadata?: ?array<string, mixed>,
     *     opening?: array{quantity: string, unit_price?: ?string, occurred_at: string, note?: ?string}
     * }  $data
     */
    public function create(User $actor, Portfolio $portfolio, array $data): Asset
    {
        return DB::transaction(function () use ($actor, $portfolio, $data): Asset {
            $lockedPortfolio = $this->lockPortfolio($portfolio);
            Gate::forUser($actor)->authorize('manageAssets', $lockedPortfolio);

            $opening = $data['opening'] ?? null;
            unset($data['opening']);

            $type = AssetType::from($data['type']);
            $this->assertCurrency($lockedPortfolio, $data['currency']);

            if ($type->isCash()) {
                $this->assertCashPrice($data['current_price']);
                $data['current_price'] = '1.00000000';
            }

            $asset = $lockedPortfolio->assets()->create($data);

            if ($opening !== null) {
                $this->transactions->record($actor, $lockedPortfolio, $asset, [
                    'kind' => $type->isCash() ? TransactionKind::Deposit->value : TransactionKind::Buy->value,
                    'quantity' => $opening['quantity'],
                    'unit_price' => $opening['unit_price'] ?? null,
                    'fees' => '0',
                    'occurred_at' => $opening['occurred_at'],
                    'note' => $opening['note'] ?? null,
                ]);
            }

            return $this->decorate($asset->refresh());
        }, 3);
    }

    public function show(User $actor, Portfolio $portfolio, Asset $asset): Asset
    {
        Gate::forUser($actor)->authorize('view', $portfolio);

        $scopedAsset = $portfolio->assets()->whereKey($asset->getKey())->firstOrFail();

        return $this->decorate($scopedAsset);
    }

    /**
     * @param  array{type?: string, name?: string, symbol?: ?string, currency?: string, current_price?: string, price_as_of?: string, metadata?: ?array<string, mixed>}  $data
     */
    public function update(User $actor, Portfolio $portfolio, Asset $asset, array $data): Asset
    {
        return DB::transaction(function () use ($actor, $portfolio, $asset, $data): Asset {
            $lockedPortfolio = $this->lockPortfolio($portfolio);
            Gate::forUser($actor)->authorize('manageAssets', $lockedPortfolio);
            $lockedAsset = $this->lockAsset($lockedPortfolio, $asset);

            $newType = isset($data['type']) ? AssetType::from($data['type']) : $lockedAsset->type;
            $newCurrency = $data['currency'] ?? $lockedAsset->currency;
            $classificationChanged = $newType !== $lockedAsset->type || $newCurrency !== $lockedAsset->currency;

            if ($classificationChanged && $lockedAsset->transactions()->exists()) {
                throw new ApiException(
                    'asset_classification_immutable',
                    'Asset type and currency cannot change after transactions exist.',
                    409,
                );
            }

            $this->assertCurrency($lockedPortfolio, $newCurrency);

            if ($newType->isCash()) {
                if (isset($data['current_price'])) {
                    $this->assertCashPrice($data['current_price']);
                }

                $data['current_price'] = '1.00000000';
            }

            $lockedAsset->update($data);

            return $this->decorate($lockedAsset->refresh());
        }, 3);
    }

    public function archive(User $actor, Portfolio $portfolio, Asset $asset): void
    {
        DB::transaction(function () use ($actor, $portfolio, $asset): void {
            $lockedPortfolio = $this->lockPortfolio($portfolio);
            Gate::forUser($actor)->authorize('manageAssets', $lockedPortfolio);
            $lockedAsset = $this->lockAsset($lockedPortfolio, $asset);

            if (! $this->valuations->quantityFor($lockedAsset)->isZero()) {
                throw new ApiException('asset_has_balance', 'An asset must have a zero quantity before it can be archived.', 409);
            }

            $lockedAsset->update(['archived_at' => now()]);
        }, 3);
    }

    private function decorate(Asset $asset): Asset
    {
        $this->valuations->decorateAssets(new Collection([$asset]));

        return $asset;
    }

    private function assertCurrency(Portfolio $portfolio, string $currency): void
    {
        if ($currency !== $portfolio->base_currency) {
            throw new ApiException('currency_mismatch', 'The currency must match the portfolio base currency.', 422);
        }
    }

    private function assertCashPrice(string $price): void
    {
        if (! BigDecimal::of($price)->isEqualTo('1')) {
            throw new ApiException('cash_price_fixed', 'The current price for cash must be 1.', 422);
        }
    }

    private function lockPortfolio(Portfolio $portfolio): Portfolio
    {
        return Portfolio::query()->whereKey($portfolio->getKey())->lockForUpdate()->firstOrFail();
    }

    private function lockAsset(Portfolio $portfolio, Asset $asset): Asset
    {
        return Asset::query()
            ->where('portfolio_id', $portfolio->getKey())
            ->whereKey($asset->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
