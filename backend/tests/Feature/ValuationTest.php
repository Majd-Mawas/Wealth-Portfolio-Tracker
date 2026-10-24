<?php

namespace Tests\Feature;

use App\Enums\AssetType;
use App\Enums\PortfolioRole;
use App\Enums\TransactionKind;
use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\PortfolioMembership;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ValuationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dashboard_calculates_precise_manual_values_and_allocations(): void
    {
        $viewer = User::factory()->create();
        $creator = User::factory()->create();
        $portfolio = $this->portfolioWithMember($viewer, PortfolioRole::Viewer);
        $stock = Asset::factory()->for($portfolio)->create([
            'type' => AssetType::Stock,
            'name' => 'Stock Holding',
            'current_price' => '10.10000000',
        ]);
        $cash = Asset::factory()->for($portfolio)->cash()->create(['name' => 'Cash Reserve']);
        $crypto = Asset::factory()->for($portfolio)->create([
            'type' => AssetType::Crypto,
            'name' => 'Crypto Holding',
            'current_price' => '12345.67890123',
        ]);

        Transaction::factory()->forAsset($stock)->create([
            'created_by' => $creator->id,
            'quantity_delta' => '3.00000000',
        ]);
        Transaction::factory()->forAsset($cash)->create([
            'created_by' => $creator->id,
            'kind' => TransactionKind::Deposit,
            'quantity_delta' => '100.00500000',
        ]);
        Transaction::factory()->forAsset($crypto)->create([
            'created_by' => $creator->id,
            'quantity_delta' => '0.12345678',
        ]);

        $this->actingAs($viewer)
            ->getJson("/api/v1/portfolios/{$portfolio->id}/dashboard")
            ->assertOk()
            ->assertJsonPath('data.total_value', '1654.46276406')
            ->assertJsonPath('data.allocation_by_type.0.type', 'crypto')
            ->assertJsonPath('data.allocation_by_type.0.value', '1524.15776406')
            ->assertJsonPath('data.allocation_by_type.0.percentage', '92.12402945')
            ->assertJsonPath('data.allocation_by_type.1.type', 'cash')
            ->assertJsonPath('data.allocation_by_type.1.percentage', '6.04456034')
            ->assertJsonPath('data.allocation_by_type.2.type', 'stock')
            ->assertJsonPath('data.allocation_by_type.2.percentage', '1.83141021')
            ->assertJsonPath('data.assets.0.value', '30.30000000')
            ->assertJsonPath('data.assets.1.value', '100.00500000')
            ->assertJsonPath('data.assets.2.value', '1524.15776406')
            ->assertJsonPath('data.assets.2.price_status', 'manual');
    }

    public function test_zero_value_dashboard_returns_zero_percentages_and_excludes_archived_assets(): void
    {
        $viewer = User::factory()->create();
        $portfolio = $this->portfolioWithMember($viewer, PortfolioRole::Viewer);
        Asset::factory()->for($portfolio)->create(['name' => 'Empty Holding']);
        Asset::factory()->for($portfolio)->archived()->create(['name' => 'Archived Holding']);

        $this->actingAs($viewer)
            ->getJson("/api/v1/portfolios/{$portfolio->id}/dashboard")
            ->assertOk()
            ->assertJsonPath('data.total_value', '0.00000000')
            ->assertJsonCount(1, 'data.assets')
            ->assertJsonPath('data.assets.0.name', 'Empty Holding')
            ->assertJsonPath('data.assets.0.percentage', '0.00000000')
            ->assertJsonPath('data.allocation_by_type.0.percentage', '0.00000000');
    }

    public function test_current_manual_price_drives_valuation_not_historical_unit_price_or_fees(): void
    {
        $owner = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);
        $asset = Asset::factory()->for($portfolio)->create(['current_price' => '25.00000000']);
        Transaction::factory()->forAsset($asset)->create([
            'created_by' => $owner->id,
            'quantity_delta' => '2.00000000',
            'unit_price' => '999.00000000',
            'fees' => '100.00000000',
        ]);

        $this->actingAs($owner)
            ->getJson("/api/v1/portfolios/{$portfolio->id}/dashboard")
            ->assertOk()
            ->assertJsonPath('data.total_value', '50.00000000');

        $this->actingAs($owner)
            ->patchJson("/api/v1/portfolios/{$portfolio->id}/assets/{$asset->id}", [
                'current_price' => '30.00000000',
                'price_as_of' => '2026-10-24T15:00:00Z',
            ])
            ->assertOk();

        $this->actingAs($owner)
            ->getJson("/api/v1/portfolios/{$portfolio->id}/dashboard")
            ->assertOk()
            ->assertJsonPath('data.total_value', '60.00000000');
    }

    public function test_non_member_cannot_access_dashboard(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);

        $this->actingAs($outsider)
            ->getJson("/api/v1/portfolios/{$portfolio->id}/dashboard")
            ->assertNotFound();
    }

    private function portfolioWithMember(User $user, PortfolioRole $role): Portfolio
    {
        $portfolio = Portfolio::factory()->create();

        PortfolioMembership::factory()->create([
            'portfolio_id' => $portfolio->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $portfolio;
    }
}
