<?php

namespace App\Services;

use App\Enums\PortfolioRole;
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
