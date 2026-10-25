<?php

namespace Tests\Feature;

use App\Enums\PortfolioRole;
use App\Models\Portfolio;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoPortfolioSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_database_seeder_loads_the_repeatable_local_demo_dataset(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $growth = Portfolio::query()->where('name', 'Growth Portfolio')->firstOrFail();
        $alex = User::query()->where('email', 'alex@example.test')->firstOrFail();

        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('portfolios', 4);
        $this->assertDatabaseCount('portfolio_memberships', 10);
        $this->assertDatabaseCount('assets', 13);
        $this->assertDatabaseCount('transactions', 39);
        $this->assertTrue(Hash::check(DemoPortfolioSeeder::PASSWORD, $alex->password));

        foreach ([
            'alex@example.test' => 'Alex Morgan',
            'sarah@example.test' => 'Sarah Bennett',
            'daniel@example.test' => 'Daniel Reed',
            'emma@example.test' => 'Emma Wilson',
        ] as $email => $name) {
            $this->assertDatabaseHas('users', ['email' => $email, 'name' => $name]);
        }

        foreach ([
            ['Growth Portfolio', 'alex@example.test', PortfolioRole::Owner],
            ['Growth Portfolio', 'sarah@example.test', PortfolioRole::Editor],
            ['Growth Portfolio', 'daniel@example.test', PortfolioRole::Viewer],
            ['Family Wealth', 'sarah@example.test', PortfolioRole::Owner],
            ['Family Wealth', 'alex@example.test', PortfolioRole::Editor],
            ['Family Wealth', 'emma@example.test', PortfolioRole::Viewer],
            ['Retirement Savings', 'daniel@example.test', PortfolioRole::Owner],
            ['Retirement Savings', 'emma@example.test', PortfolioRole::Editor],
            ['Retirement Savings', 'alex@example.test', PortfolioRole::Viewer],
            ['Crypto Holdings', 'emma@example.test', PortfolioRole::Owner],
        ] as [$portfolioName, $email, $role]) {
            $portfolio = Portfolio::query()->where('name', $portfolioName)->firstOrFail();
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertDatabaseHas('portfolio_memberships', [
                'portfolio_id' => $portfolio->getKey(),
                'user_id' => $user->getKey(),
                'role' => $role->value,
            ]);
        }

        $this->actingAs($alex)
            ->getJson("/api/v1/portfolios/{$growth->getKey()}/dashboard")
            ->assertOk()
            ->assertJsonPath('data.total_value', '45446.81250000');
    }
}
