<?php

namespace App\Services;

use App\Enums\PortfolioRole;
use App\Exceptions\ApiException;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PortfolioService
{
    /** @param array{name: string, base_currency: string} $data */
    public function create(User $actor, array $data): Portfolio
    {
        Gate::forUser($actor)->authorize('create', Portfolio::class);

        return DB::transaction(function () use ($actor, $data): Portfolio {
            $portfolio = Portfolio::query()->create($data);

            $portfolio->memberships()->create([
                'user_id' => $actor->getKey(),
                'role' => PortfolioRole::Owner,
            ]);

            return $portfolio->refresh();
        });
    }

    /** @param array{name?: string, base_currency?: string} $data */
    public function update(User $actor, Portfolio $portfolio, array $data): Portfolio
    {
        return DB::transaction(function () use ($actor, $portfolio, $data): Portfolio {
            $lockedPortfolio = Portfolio::query()
                ->whereKey($portfolio->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('update', $lockedPortfolio);

            if (isset($data['base_currency'])
                && $data['base_currency'] !== $lockedPortfolio->base_currency
                && $lockedPortfolio->assets()->exists()) {
                throw new ApiException(
                    'currency_change_not_allowed',
                    'The base currency cannot change after assets have been created.',
                    409,
                );
            }

            $lockedPortfolio->update($data);

            return $lockedPortfolio->refresh();
        });
    }

    public function delete(User $actor, Portfolio $portfolio): void
    {
        DB::transaction(function () use ($actor, $portfolio): void {
            $lockedPortfolio = Portfolio::query()
                ->whereKey($portfolio->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('delete', $lockedPortfolio);
            $lockedPortfolio->delete();
        });
    }
}
