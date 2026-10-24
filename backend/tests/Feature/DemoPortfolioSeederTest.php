<?php

namespace Tests\Feature;

use App\Enums\PortfolioRole;
use App\Enums\TransactionKind;
use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ValuationService;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoPortfolioSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Tests\TestCase;

class DemoPortfolioSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_demo_seeder_refuses_to_run_outside_local_or_testing_environments(): void
    {
        $originalEnvironment = app()->environment();
        $caught = null;
        app()->detectEnvironment(fn (): string => 'production');

        try {
            app(DemoPortfolioSeeder::class)->run();
        } catch (LogicException $exception) {
            $caught = $exception;
        } finally {
            app()->detectEnvironment(fn (): string => $originalEnvironment);
        }

        $this->assertInstanceOf(LogicException::class, $caught);
        $this->assertSame(
            'Demo portfolio data may only be seeded in local or testing environments.',
            $caught->getMessage(),
        );
        $this->assertDatabaseCount('users', 0);
    }

    public function test_demo_seeder_is_repeatable_and_preserves_unrelated_records(): void
    {
        $unrelatedUser = User::factory()->create(['email' => 'unrelated@example.test']);
        $unrelatedPortfolio = Portfolio::factory()->create(['name' => 'Existing User Portfolio']);

        $this->seed(DemoPortfolioSeeder::class);
        $this->seed(DemoPortfolioSeeder::class);

        $this->assertModelExists($unrelatedUser);
        $this->assertModelExists($unrelatedPortfolio);
        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('portfolios', 5);
        $this->assertDatabaseCount('portfolio_memberships', 10);
        $this->assertDatabaseCount('assets', 13);
        $this->assertDatabaseCount('transactions', 39);
    }

    public function test_demo_quantities_valuations_and_transaction_history_are_consistent(): void
    {
        $this->seed(DemoPortfolioSeeder::class);
        $valuations = app(ValuationService::class);
        $expectedQuantities = [
            'Growth Portfolio|Apple' => '26.50000000',
            'Growth Portfolio|Microsoft' => '14.00000000',
            'Growth Portfolio|NVIDIA' => '37.25000000',
            'Growth Portfolio|USD Cash Balance' => '24750.00000000',
            'Family Wealth|Residential Apartment' => '1.00000000',
            'Family Wealth|Rental Property' => '1.00000000',
            'Family Wealth|Family Cash Reserve' => '54000.00000000',
            'Retirement Savings|European Equity ETF' => '180.00000000',
            'Retirement Savings|Euro Bond ETF' => '125.00000000',
            'Retirement Savings|EUR Cash Balance' => '39500.00000000',
            'Crypto Holdings|Bitcoin' => '0.17000000',
            'Crypto Holdings|Ethereum' => '3.00000000',
            'Crypto Holdings|Solana' => '40.00000000',
        ];

        $assets = Asset::query()->with('portfolio')->orderBy('id')->get();

        foreach ($assets as $asset) {
            $key = $asset->portfolio->name.'|'.$asset->name;

            $this->assertSame(
                $expectedQuantities[$key],
                $valuations->format($valuations->quantityFor($asset)),
                "Unexpected seeded quantity for {$key}.",
            );

            $runningQuantity = BigDecimal::zero();

            foreach ($asset->transactions()->orderBy('occurred_at')->orderBy('id')->get() as $transaction) {
                $runningQuantity = $runningQuantity->plus($transaction->quantity_delta);
                $this->assertFalse(
                    $runningQuantity->isLessThan(0),
                    "Seeded history produces a negative balance for {$key} at {$transaction->occurred_at}.",
                );
            }
        }

        $expectedTotals = [
            'Growth Portfolio' => '45446.81250000',
            'Family Wealth' => '749000.00000000',
            'Retirement Savings' => '67555.00000000',
            'Crypto Holdings' => '26475.00000000',
        ];

        foreach ($expectedTotals as $portfolioName => $expectedTotal) {
            $portfolio = Portfolio::query()->where('name', $portfolioName)->firstOrFail();
            $owner = $portfolio->users()
                ->wherePivot('role', PortfolioRole::Owner->value)
                ->firstOrFail();

            $this->assertSame($expectedTotal, $valuations->dashboard($owner, $portfolio)['total_value']);
        }

        $this->assertSame(
            '2025-11-01',
            CarbonImmutable::parse((string) Transaction::query()->min('occurred_at'))->toDateString(),
        );
        $this->assertSame(
            '2026-10-10',
            CarbonImmutable::parse((string) Transaction::query()->max('occurred_at'))->toDateString(),
        );
        $this->assertGreaterThan(0, Transaction::query()->where('kind', TransactionKind::Buy->value)->count());
        $this->assertGreaterThan(0, Transaction::query()->where('kind', TransactionKind::Sell->value)->count());
        $this->assertGreaterThan(0, Transaction::query()->where('kind', TransactionKind::Deposit->value)->count());
        $this->assertGreaterThan(0, Transaction::query()->where('kind', TransactionKind::Withdrawal->value)->count());
    }

    public function test_demo_roles_and_private_portfolio_boundaries_are_enforced(): void
    {
        $this->seed(DemoPortfolioSeeder::class);
        $alex = User::query()->where('email', 'alex@example.test')->firstOrFail();
        $sarah = User::query()->where('email', 'sarah@example.test')->firstOrFail();
        $daniel = User::query()->where('email', 'daniel@example.test')->firstOrFail();
        $emma = User::query()->where('email', 'emma@example.test')->firstOrFail();
        $growth = Portfolio::query()->where('name', 'Growth Portfolio')->firstOrFail();
        $crypto = Portfolio::query()->where('name', 'Crypto Holdings')->firstOrFail();

        $this->assertTrue(Gate::forUser($alex)->allows('manageMembers', $growth));
        $this->assertTrue(Gate::forUser($sarah)->allows('manageAssets', $growth));
        $this->assertFalse(Gate::forUser($sarah)->allows('manageMembers', $growth));
        $this->assertTrue(Gate::forUser($daniel)->allows('view', $growth));
        $this->assertFalse(Gate::forUser($daniel)->allows('manageAssets', $growth));
        $this->assertFalse(Gate::forUser($emma)->allows('view', $growth));

        $this->actingAs($daniel)
            ->getJson("/api/v1/portfolios/{$growth->getKey()}/dashboard")
            ->assertOk();

        $this->actingAs($daniel)
            ->postJson("/api/v1/portfolios/{$growth->getKey()}/assets", [
                'type' => 'stock',
                'name' => 'Viewer Must Not Create This',
                'symbol' => 'DENY',
                'currency' => 'USD',
                'current_price' => '10.00000000',
                'price_as_of' => '2026-10-20T12:00:00+00:00',
            ])
            ->assertForbidden();

        $this->actingAs($sarah)
            ->postJson("/api/v1/portfolios/{$growth->getKey()}/assets", [
                'type' => 'stock',
                'name' => 'Editor Authorization Check',
                'symbol' => 'EDIT',
                'currency' => 'USD',
                'current_price' => '10.00000000',
                'price_as_of' => '2026-10-20T12:00:00+00:00',
            ])
            ->assertCreated();

        $this->actingAs($sarah)
            ->postJson("/api/v1/portfolios/{$growth->getKey()}/members", [
                'email' => $emma->email,
                'role' => PortfolioRole::Viewer->value,
            ])
            ->assertForbidden();

        $this->actingAs($emma)
            ->getJson("/api/v1/portfolios/{$growth->getKey()}/dashboard")
            ->assertNotFound();

        $this->actingAs($alex)
            ->getJson("/api/v1/portfolios/{$crypto->getKey()}/dashboard")
            ->assertNotFound();

        $this->actingAs($emma)
            ->getJson("/api/v1/portfolios/{$crypto->getKey()}/dashboard")
            ->assertOk()
            ->assertJsonPath('data.total_value', '26475.00000000');
    }

    public function test_growth_transaction_history_supports_filtering_and_pagination(): void
    {
        $this->seed(DemoPortfolioSeeder::class);
        $alex = User::query()->where('email', 'alex@example.test')->firstOrFail();
        $growth = Portfolio::query()->where('name', 'Growth Portfolio')->firstOrFail();

        $firstPage = $this->actingAs($alex)
            ->getJson("/api/v1/portfolios/{$growth->getKey()}/transactions")
            ->assertOk()
            ->assertJsonCount(15, 'data');

        $this->assertNotNull($firstPage->json('meta.next_cursor'));

        $this->actingAs($alex)
            ->getJson("/api/v1/portfolios/{$growth->getKey()}/transactions?kind=sell")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }
}
