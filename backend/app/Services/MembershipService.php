<?php

namespace App\Services;

use App\Enums\PortfolioRole;
use App\Exceptions\ApiException;
use App\Models\Portfolio;
use App\Models\PortfolioMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MembershipService
{
    public function add(
        User $actor,
        Portfolio $portfolio,
        string $email,
        PortfolioRole $role,
    ): PortfolioMembership {
        return DB::transaction(function () use ($actor, $portfolio, $email, $role): PortfolioMembership {
            $lockedPortfolio = $this->lockPortfolio($portfolio);
            Gate::forUser($actor)->authorize('manageMembers', $lockedPortfolio);

            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                throw new ApiException('member_not_found', 'No eligible registered user was found.', 422);
            }

            $exists = PortfolioMembership::query()
                ->where('portfolio_id', $lockedPortfolio->getKey())
                ->where('user_id', $user->getKey())
                ->exists();

            if ($exists) {
                throw new ApiException('membership_exists', 'The user is already a portfolio member.', 409);
            }

            return PortfolioMembership::query()->create([
                'portfolio_id' => $lockedPortfolio->getKey(),
                'user_id' => $user->getKey(),
                'role' => $role,
            ])->load('user');
        });
    }

    public function update(
        User $actor,
        Portfolio $portfolio,
        PortfolioMembership $membership,
        PortfolioRole $role,
    ): PortfolioMembership {
        return DB::transaction(function () use ($actor, $portfolio, $membership, $role): PortfolioMembership {
            $lockedPortfolio = $this->lockPortfolio($portfolio);
            Gate::forUser($actor)->authorize('manageMembers', $lockedPortfolio);
            $lockedMembership = $this->lockMembership($lockedPortfolio, $membership);

            if ($lockedMembership->role === PortfolioRole::Owner && $role !== PortfolioRole::Owner) {
                $this->assertAnotherOwnerExists($lockedPortfolio, $lockedMembership);
            }

            $lockedMembership->update(['role' => $role]);

            return $lockedMembership->refresh()->load('user');
        });
    }

    public function remove(
        User $actor,
        Portfolio $portfolio,
        PortfolioMembership $membership,
    ): void {
        DB::transaction(function () use ($actor, $portfolio, $membership): void {
            $lockedPortfolio = $this->lockPortfolio($portfolio);
            Gate::forUser($actor)->authorize('manageMembers', $lockedPortfolio);
            $lockedMembership = $this->lockMembership($lockedPortfolio, $membership);

            if ($lockedMembership->role === PortfolioRole::Owner) {
                $this->assertAnotherOwnerExists($lockedPortfolio, $lockedMembership);
            }

            $lockedMembership->delete();
        });
    }

    private function lockPortfolio(Portfolio $portfolio): Portfolio
    {
        return Portfolio::query()
            ->whereKey($portfolio->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockMembership(
        Portfolio $portfolio,
        PortfolioMembership $membership,
    ): PortfolioMembership {
        return PortfolioMembership::query()
            ->where('portfolio_id', $portfolio->getKey())
            ->whereKey($membership->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertAnotherOwnerExists(
        Portfolio $portfolio,
        PortfolioMembership $membership,
    ): void {
        $anotherOwnerExists = PortfolioMembership::query()
            ->where('portfolio_id', $portfolio->getKey())
            ->where('role', PortfolioRole::Owner->value)
            ->where('id', '!=', $membership->getKey())
            ->exists();

        if (! $anotherOwnerExists) {
            throw new ApiException(
                'last_owner_required',
                'A portfolio must retain at least one owner.',
                409,
            );
        }
    }
}
