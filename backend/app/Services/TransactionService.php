<?php

namespace App\Services;

use App\Enums\AssetType;
use App\Enums\TransactionKind;
use App\Enums\TransactionSource;
use App\Exceptions\ApiException;
use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TransactionService
{
    public function __construct(private readonly ValuationService $valuations) {}

    /**
     * @param  array{asset_id?: int, kind?: string, occurred_from?: string, occurred_to?: string}  $filters
     */
    public function paginate(User $actor, Portfolio $portfolio, array $filters): CursorPaginator
    {
        Gate::forUser($actor)->authorize('view', $portfolio);

        return Transaction::query()
            ->whereHas('asset', fn ($query) => $query->where('portfolio_id', $portfolio->getKey()))
            ->with(['asset:id,name,type,symbol', 'creator:id,name'])
            ->when(isset($filters['asset_id']), fn ($query) => $query->where('asset_id', $filters['asset_id']))
            ->when(isset($filters['kind']), fn ($query) => $query->where('kind', $filters['kind']))
            ->when(isset($filters['occurred_from']), fn ($query) => $query->where('occurred_at', '>=', $filters['occurred_from']))
            ->when(isset($filters['occurred_to']), fn ($query) => $query->where('occurred_at', '<=', $filters['occurred_to']))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->cursorPaginate(15);
    }

    /**
     * @param  array{kind: string, quantity: string, unit_price?: ?string, fees?: string, occurred_at: string, note?: ?string}  $data
     */
    public function record(
        User $actor,
        Portfolio $portfolio,
        Asset $asset,
        array $data,
        TransactionSource $source = TransactionSource::Api,
    ): Transaction {
        return DB::transaction(function () use ($actor, $portfolio, $asset, $data, $source): Transaction {
            $lockedPortfolio = $this->lockPortfolio($portfolio);
            Gate::forUser($actor)->authorize('recordTransactions', $lockedPortfolio);
            $lockedAsset = $this->lockAsset($lockedPortfolio, $asset);

            if ($lockedAsset->archived_at !== null) {
                throw new ApiException('asset_archived', 'Transactions cannot be recorded for an archived asset.', 409);
            }

            $kind = TransactionKind::from($data['kind']);
            $this->assertKindAllowed($lockedAsset->type, $kind);
            $delta = $this->deriveDelta($kind, BigDecimal::of($data['quantity']));
            $resultingQuantity = $this->valuations->quantityFor($lockedAsset)->plus($delta);

            if ($resultingQuantity->isLessThan(0)) {
                throw new ApiException('insufficient_quantity', 'The transaction would reduce the asset quantity below zero.', 409);
            }

            return Transaction::query()->create([
                'asset_id' => $lockedAsset->getKey(),
                'created_by' => $actor->getKey(),
                'kind' => $kind,
                'quantity_delta' => $this->valuations->format($delta),
                'unit_price' => isset($data['unit_price']) ? $this->valuations->format(BigDecimal::of($data['unit_price'])) : null,
                'fees' => $this->valuations->format(BigDecimal::of($data['fees'] ?? '0')),
                'occurred_at' => $data['occurred_at'],
                'note' => $data['note'] ?? null,
                'source' => $source,
            ])->load(['asset:id,name,type,symbol', 'creator:id,name']);
        }, 3);
    }

    public function reverse(
        User $actor,
        Portfolio $portfolio,
        Transaction $transaction,
        string $reason,
    ): Transaction {
        return DB::transaction(function () use ($actor, $portfolio, $transaction, $reason): Transaction {
            $lockedPortfolio = $this->lockPortfolio($portfolio);
            Gate::forUser($actor)->authorize('recordTransactions', $lockedPortfolio);
            $lockedAsset = Asset::query()
                ->where('portfolio_id', $lockedPortfolio->getKey())
                ->whereKey($transaction->asset_id)
                ->lockForUpdate()
                ->firstOrFail();
            $original = Transaction::query()
                ->where('asset_id', $lockedAsset->getKey())
                ->whereKey($transaction->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($original->kind === TransactionKind::Reversal) {
                throw new ApiException('reversal_not_allowed', 'A reversal transaction cannot be reversed.', 409);
            }

            if (Transaction::query()->where('reversal_of_id', $original->getKey())->exists()) {
                throw new ApiException('transaction_already_reversed', 'The transaction has already been reversed.', 409);
            }

            $delta = BigDecimal::of($original->quantity_delta)->negated();
            $resultingQuantity = $this->valuations->quantityFor($lockedAsset)->plus($delta);

            if ($resultingQuantity->isLessThan(0)) {
                throw new ApiException('insufficient_quantity', 'Reversing this transaction would reduce the asset quantity below zero.', 409);
            }

            return Transaction::query()->create([
                'asset_id' => $lockedAsset->getKey(),
                'created_by' => $actor->getKey(),
                'kind' => TransactionKind::Reversal,
                'quantity_delta' => $this->valuations->format($delta),
                'unit_price' => $original->unit_price,
                'fees' => $this->valuations->format(BigDecimal::of($original->fees)->negated()),
                'occurred_at' => now(),
                'note' => $reason,
                'source' => TransactionSource::Api,
                'reversal_of_id' => $original->getKey(),
            ])->load(['asset:id,name,type,symbol', 'creator:id,name']);
        }, 3);
    }

    private function deriveDelta(TransactionKind $kind, BigDecimal $quantity): BigDecimal
    {
        if ($kind === TransactionKind::Adjustment) {
            if ($quantity->isZero()) {
                throw new ApiException('invalid_quantity', 'An adjustment quantity must be non-zero.', 422);
            }

            return $quantity;
        }

        if (! $quantity->isGreaterThan(0)) {
            throw new ApiException('invalid_quantity', 'Transaction quantity must be positive.', 422);
        }

        return $kind->isNegative() ? $quantity->negated() : $quantity;
    }

    private function assertKindAllowed(AssetType $type, TransactionKind $kind): void
    {
        $allowed = $type->isCash()
            ? [TransactionKind::Deposit, TransactionKind::Withdrawal, TransactionKind::Adjustment]
            : [TransactionKind::Buy, TransactionKind::Sell, TransactionKind::Adjustment];

        if (! in_array($kind, $allowed, true)) {
            throw new ApiException('transaction_kind_not_allowed', 'The transaction kind is not allowed for this asset type.', 422);
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
