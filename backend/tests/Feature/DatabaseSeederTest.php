<?php

namespace Tests\Feature;

use App\Enums\PortfolioRole;
use App\Models\Portfolio;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_demo_seed_is_repeatable_and_provides_each_portfolio_role(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $portfolio = Portfolio::query()->where('name', 'Demo Household')->firstOrFail();
        $viewer = User::query()->where('email', 'viewer@example.com')->firstOrFail();

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('portfolios', 1);
        $this->assertDatabaseCount('portfolio_memberships', 3);
        $this->assertDatabaseCount('assets', 4);
        $this->assertDatabaseCount('transactions', 4);
        $this->assertDatabaseHas('portfolio_memberships', [
            'portfolio_id' => $portfolio->getKey(),
            'user_id' => $viewer->getKey(),
            'role' => PortfolioRole::Viewer->value,
        ]);

        $this->actingAs($viewer)
            ->getJson("/api/v1/portfolios/{$portfolio->getKey()}/dashboard")
            ->assertOk()
            ->assertJsonPath('data.total_value', '218208.17851852');
    }
}
