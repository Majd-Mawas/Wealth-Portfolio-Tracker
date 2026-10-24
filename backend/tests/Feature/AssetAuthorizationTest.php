<?php

namespace Tests\Feature;

use App\Enums\AssetType;
use App\Enums\PortfolioRole;
use App\Enums\TransactionKind;
use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\PortfolioMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssetAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('writableRoles')]
    public function test_owner_and_editor_can_create_update_and_archive_zero_balance_assets(PortfolioRole $role): void
    {
        $user = User::factory()->create();
        $portfolio = $this->portfolioWithMember($user, $role);

        $assetId = $this->actingAs($user)
            ->postJson("/api/v1/portfolios/{$portfolio->id}/assets", $this->assetPayload())
            ->assertCreated()
            ->assertJsonPath('data.current_price', '125.34000000')
            ->assertJsonPath('data.quantity', '0.00000000')
            ->json('data.id');

        $this->actingAs($user)
            ->patchJson("/api/v1/portfolios/{$portfolio->id}/assets/{$assetId}", [
                'name' => 'Updated Corp',
                'current_price' => '130.125',
                'price_as_of' => '2026-10-24T14:00:00Z',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Corp')
            ->assertJsonPath('data.current_price', '130.12500000');

        $this->actingAs($user)
            ->deleteJson("/api/v1/portfolios/{$portfolio->id}/assets/{$assetId}")
            ->assertNoContent();

        $this->assertDatabaseHas('assets', [
            'id' => $assetId,
            'name' => 'Updated Corp',
        ]);
        $this->assertNotNull(Asset::query()->findOrFail($assetId)->archived_at);
    }

    public function test_viewer_receives_403_for_every_asset_write(): void
    {
        $viewer = User::factory()->create();
        $portfolio = $this->portfolioWithMember($viewer, PortfolioRole::Viewer);
        $asset = Asset::factory()->for($portfolio)->create();

        $this->actingAs($viewer)
            ->postJson("/api/v1/portfolios/{$portfolio->id}/assets", $this->assetPayload())
            ->assertForbidden();

        $this->actingAs($viewer)
            ->patchJson("/api/v1/portfolios/{$portfolio->id}/assets/{$asset->id}", ['name' => 'Forbidden'])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->deleteJson("/api/v1/portfolios/{$portfolio->id}/assets/{$asset->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'name' => $asset->name]);
    }

    public function test_non_member_and_cross_portfolio_asset_ids_return_404(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);
        $otherPortfolio = Portfolio::factory()->create();
        $otherAsset = Asset::factory()->for($otherPortfolio)->create();

        $this->actingAs($outsider)
            ->getJson("/api/v1/portfolios/{$portfolio->id}/assets")
            ->assertNotFound();

        $this->actingAs($owner)
            ->getJson("/api/v1/portfolios/{$portfolio->id}/assets/{$otherAsset->id}")
            ->assertNotFound();
    }

    #[DataProvider('assetTypes')]
    public function test_each_supported_asset_type_can_be_created_with_an_atomic_opening_transaction(
        AssetType $type,
        string $price,
        TransactionKind $expectedKind,
    ): void {
        $owner = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);
        $payload = $this->assetPayload($type, $price);
        $payload['opening'] = [
            'quantity' => '2.50000001',
            'unit_price' => $price,
            'occurred_at' => '2026-10-20T09:30:00Z',
            'note' => 'Opening position',
        ];

        $assetId = $this->actingAs($owner)
            ->postJson("/api/v1/portfolios/{$portfolio->id}/assets", $payload)
            ->assertCreated()
            ->assertJsonPath('data.type', $type->value)
            ->assertJsonPath('data.quantity', '2.50000001')
            ->json('data.id');

        $this->assertDatabaseHas('transactions', [
            'asset_id' => $assetId,
            'created_by' => $owner->id,
            'kind' => $expectedKind->value,
            'quantity_delta' => '2.50000001',
        ]);
    }

    public function test_asset_validation_rejects_non_string_decimals_wrong_currency_cash_price_and_unknown_fields(): void
    {
        $owner = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);

        $this->actingAs($owner)
            ->postJson("/api/v1/portfolios/{$portfolio->id}/assets", [
                ...$this->assetPayload(AssetType::Cash, '2.00'),
                'currency' => 'EUR',
                'quantity' => '100.00',
                'current_price' => 2.0,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.fields.currency.0', 'The currency must match the portfolio base currency.')
            ->assertJsonPath('error.fields.current_price.0', 'The current price field must be a string.')
            ->assertJsonPath('error.fields.quantity.0', 'The quantity field is not allowed.');

        $this->assertDatabaseCount('assets', 0);
    }

    public function test_asset_list_supports_type_archive_filters_and_cursor_pagination(): void
    {
        $viewer = User::factory()->create();
        $portfolio = $this->portfolioWithMember($viewer, PortfolioRole::Viewer);
        $archived = Asset::factory()->for($portfolio)->archived()->create(['type' => AssetType::Stock]);
        Asset::factory()->for($portfolio)->count(16)->create(['type' => AssetType::Stock]);
        Asset::factory()->for($portfolio)->create(['type' => AssetType::Crypto]);

        $firstPage = $this->actingAs($viewer)
            ->getJson("/api/v1/portfolios/{$portfolio->id}/assets?type=stock")
            ->assertOk()
            ->assertJsonCount(15, 'data');

        $cursor = $firstPage->json('meta.next_cursor');
        $this->assertNotNull($cursor);

        $this->actingAs($viewer)
            ->getJson("/api/v1/portfolios/{$portfolio->id}/assets?type=stock&cursor=".urlencode($cursor))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($viewer)
            ->getJson("/api/v1/portfolios/{$portfolio->id}/assets?type=stock&include_archived=true")
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('data.0.id', $archived->id);
    }

    public function test_portfolio_base_currency_cannot_change_after_an_asset_exists(): void
    {
        $owner = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);
        Asset::factory()->for($portfolio)->create();

        $this->actingAs($owner)
            ->patchJson("/api/v1/portfolios/{$portfolio->id}", ['base_currency' => 'EUR'])
            ->assertConflict()
            ->assertJsonPath('error.code', 'currency_change_not_allowed');

        $this->assertDatabaseHas('portfolios', ['id' => $portfolio->id, 'base_currency' => 'USD']);
    }

    public function test_asset_with_a_balance_cannot_be_archived_or_reclassified(): void
    {
        $owner = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);
        $asset = Asset::factory()->for($portfolio)->create();

        $this->actingAs($owner)->postJson(
            "/api/v1/portfolios/{$portfolio->id}/assets/{$asset->id}/transactions",
            [
                'kind' => 'buy',
                'quantity' => '2.00000000',
                'unit_price' => '100.00000000',
                'fees' => '0.00000000',
                'occurred_at' => '2026-10-20T09:30:00Z',
            ],
        )->assertCreated();

        $this->actingAs($owner)
            ->deleteJson("/api/v1/portfolios/{$portfolio->id}/assets/{$asset->id}")
            ->assertConflict()
            ->assertJsonPath('error.code', 'asset_has_balance');

        $this->actingAs($owner)
            ->patchJson("/api/v1/portfolios/{$portfolio->id}/assets/{$asset->id}", ['type' => 'crypto'])
            ->assertConflict()
            ->assertJsonPath('error.code', 'asset_classification_immutable');

        $this->assertNull($asset->refresh()->archived_at);
        $this->assertSame(AssetType::Stock, $asset->type);
    }

    /** @return array<string, array{PortfolioRole}> */
    public static function writableRoles(): array
    {
        return [
            'owner' => [PortfolioRole::Owner],
            'editor' => [PortfolioRole::Editor],
        ];
    }

    /** @return array<string, array{AssetType, string, TransactionKind}> */
    public static function assetTypes(): array
    {
        return [
            'stock' => [AssetType::Stock, '125.34000000', TransactionKind::Buy],
            'crypto' => [AssetType::Crypto, '68000.12345678', TransactionKind::Buy],
            'real estate' => [AssetType::RealEstate, '250000.00000000', TransactionKind::Buy],
            'cash' => [AssetType::Cash, '1.00000000', TransactionKind::Deposit],
        ];
    }

    /** @return array<string, mixed> */
    private function assetPayload(
        AssetType $type = AssetType::Stock,
        string $price = '125.34000000',
    ): array {
        return [
            'type' => $type->value,
            'name' => 'Example Asset',
            'symbol' => $type === AssetType::RealEstate ? null : 'EXM',
            'currency' => 'USD',
            'current_price' => $price,
            'price_as_of' => '2026-10-24T10:00:00Z',
            'metadata' => ['source' => 'manual'],
        ];
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
