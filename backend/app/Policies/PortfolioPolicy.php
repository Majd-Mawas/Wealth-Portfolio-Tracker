<?php

namespace App\Policies;

use App\Models\Portfolio;
use App\Models\User;

class PortfolioPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Portfolio $portfolio): bool
    {
        return $portfolio->roleFor($user) !== null;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Portfolio $portfolio): bool
    {
        return $portfolio->roleFor($user)?->canManagePortfolio() ?? false;
    }

    public function delete(User $user, Portfolio $portfolio): bool
    {
        return $this->update($user, $portfolio);
    }

    public function manageMembers(User $user, Portfolio $portfolio): bool
    {
        return $portfolio->roleFor($user)?->canManageMembers() ?? false;
    }

    public function manageAssets(User $user, Portfolio $portfolio): bool
    {
        return $portfolio->roleFor($user)?->canModifyPortfolioData() ?? false;
    }

    public function recordTransactions(User $user, Portfolio $portfolio): bool
    {
        return $this->manageAssets($user, $portfolio);
    }
}
