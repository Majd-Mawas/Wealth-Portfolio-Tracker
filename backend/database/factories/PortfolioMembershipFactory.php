<?php

namespace Database\Factories;

use App\Enums\PortfolioRole;
use App\Models\Portfolio;
use App\Models\PortfolioMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PortfolioMembership> */
class PortfolioMembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'portfolio_id' => Portfolio::factory(),
            'user_id' => User::factory(),
            'role' => PortfolioRole::Viewer,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (): array => ['role' => PortfolioRole::Owner]);
    }

    public function editor(): static
    {
        return $this->state(fn (): array => ['role' => PortfolioRole::Editor]);
    }
}
