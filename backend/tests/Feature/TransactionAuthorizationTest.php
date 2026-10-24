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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TransactionAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('writableRoles')]
    public function test_owner_and_editor_can_record_transactions_with_exact_quantities(PortfolioRole $role): void
    {
        $user = User::factory()->create();
        $portfolio = $this->portfolioWithMember($user, $role);
        $asset = Asset::factory()->for($portfolio)->create(['type' => AssetType::Crypto]);

        $this->actingAs($user)->postJson(
            "/api/v1/portfolios/{$portfolio->id}/assets/{$asset->id}/transactions",
            $this->transactionPayload(TransactionKind::Buy, '0.10000001'),
        )->assertCreated()->assertJsonPath('data.quantity_delta', '0.10000001');

        $this->actingAs($user)->postJson(
            "/api/v1/portfolios/{$portfolio->id}/assets/{$asset->id}/transactions",
            $this->transactionPayload(TransactionKind::Buy, '0.20000002'),
        )->assertCreated();

        $this->actingAs($user)->postJson(
            "/api/v1/portfolios/{$portfolio->id}/assets/{$asset->id}/transactions",
            $this->transactionPayload(TransactionKind::Sell, '0.05000003'),
        )->assertCreated()->assertJsonPath('data.quantity_delta', '-0.05000003');

        $this->actingAs($user)
            ->getJson("/api/v1/portfolios/{$portfolio->id}/assets/{$asset->id}")
            ->assertOk()
            ->assertJsonPath('data.quantity', '0.25000000');
    }

    public function test_viewer_cannot_record_or_reverse_transactions(): void
    {
        $viewer = User::factory()->create();
        $owner = User::factory()->create();
        $portfolio = $this->portfolioWithMember($viewer, PortfolioRole::Viewer);
        PortfolioMembership::factory()->owner()->create([
            'portfolio_id' => $portfolio->id,
            'user_id' => $owner->id,
        ]);
        $asset = Asset::factory()->for($portfolio)->create();
        $transaction = Transaction::factory()->forAsset($asset)->create(['created_by' => $owner->id]);

        $this->actingAs($viewer)->postJson(
            "/api/v1/portfolios/{$portfolio->id}/assets/{$asset->id}/transactions",
            $this->transactionPayload(TransactionKind::Buy, '1'),
        )->assertForbidden();

        $this->actingAs($viewer)
            ->postJson("/api/v1/portfolios/{$portfolio->id}/transactions/{$transaction->id}/reverse", [
                'reason' => 'Not allowed',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_non_member_and_cross_portfolio_asset_ids_return_404_without_writes(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);
        $otherPortfolio = Portfolio::factory()->create();
        $otherAsset = Asset::factory()->for($otherPortfolio)->create();
        $otherTransaction = Transaction::factory()->forAsset($otherAsset)->create();

        $this->actingAs($outsider)->postJson(
            "/api/v1/portfolios/{$portfolio->id}/assets/{$otherAsset->id}/transactions",
            $this->transactionPayload(TransactionKind::Buy, '1'),
        )->assertNotFound();

        $this->actingAs($owner)->postJson(
            "/api/v1/portfolios/{$portfolio->id}/assets/{$otherAsset->id}/transactions",
            $this->transactionPayload(TransactionKind::Buy, '1'),
        )->assertNotFound();

        $this->actingAs($owner)
            ->postJson("/api/v1/portfolios/{$portfolio->id}/transactions/{$otherTransaction->id}/reverse", [
                'reason' => 'Cross-portfolio attempt',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_transaction_cannot_reduce_quantity_below_zero(): void
    {
        $owner = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);
        $asset = Asset::factory()->for($portfolio)->create();
        Transaction::factory()->forAsset($asset)->create([
            'created_by' => $owner->id,
            'quantity_delta' => '2.00000000',
        ]);

        $this->actingAs($owner)->postJson(
            "/api/v1/portfolios/{$portfolio->id}/assets/{$asset->id}/transactions",
            $this->transactionPayload(TransactionKind::Sell, '2.00000001'),
        )->assertConflict()->assertJsonPath('error.code', 'insufficient_quantity');

        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_transaction_kinds_are_restricted_by_asset_type_and_adjustments_require_notes(): void
    {
        $owner = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);
        $stock = Asset::factory()->for($portfolio)->create();
        $cash = Asset::factory()->for($portfolio)->cash()->create();

        $this->actingAs($owner)->postJson(
            "/api/v1/portfolios/{$portfolio->id}/assets/{$stock->id}/transactions",
            $this->transactionPayload(TransactionKind::Deposit, '1'),
        )->assertUnprocessable()->assertJsonPath('error.code', 'transaction_kind_not_allowed');

        $this->actingAs($owner)->postJson(
            "/api/v1/portfolios/{$portfolio->id}/assets/{$cash->id}/transactions",
            $this->transactionPayload(TransactionKind::Buy, '1'),
        )->assertUnprocessable()->assertJsonPath('error.code', 'transaction_kind_not_allowed');

        $adjustment = $this->transactionPayload(TransactionKind::Adjustment, '-1');
        unset($adjustment['note']);

        $this->actingAs($owner)->postJson(
            "/api/v1/portfolios/{$portfolio->id}/assets/{$stock->id}/transactions",
            $adjustment,
        )->assertUnprocessable()->assertJsonPath('error.fields.note.0', 'The note field is required.');

        $this->assertDatabaseCount('transactions', 0);
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_quantities_return_422_without_writing(mixed $quantity, TransactionKind $kind): void
    {
        $owner = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);
        $asset = Asset::factory()->for($portfolio)->create();

        $this->actingAs($owner)->postJson(
            "/api/v1/portfolios/{$portfolio->id}/assets/{$asset->id}/transactions",
            $this->transactionPayload($kind, $quantity),
        )->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_archived_asset_rejects_new_transactions(): void
    {
        $owner = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);
        $asset = Asset::factory()->for($portfolio)->archived()->create();

        $this->actingAs($owner)->postJson(
            "/api/v1/portfolios/{$portfolio->id}/assets/{$asset->id}/transactions",
            $this->transactionPayload(TransactionKind::Buy, '1'),
        )->assertConflict()->assertJsonPath('error.code', 'asset_archived');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_reversal_appends_an_opposite_entry_and_preserves_the_original(): void
    {
        $owner = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);
        $asset = Asset::factory()->for($portfolio)->create();
        $original = Transaction::factory()->forAsset($asset)->create([
            'created_by' => $owner->id,
            'quantity_delta' => '3.25000000',
            'unit_price' => '99.12500000',
            'fees' => '1.50000000',
            'note' => 'Original entry',
        ]);

        $this->actingAs($owner)
            ->postJson("/api/v1/portfolios/{$portfolio->id}/transactions/{$original->id}/reverse", [
                'reason' => 'Duplicate entry',
            ])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'reversal')
            ->assertJsonPath('data.quantity_delta', '-3.25000000')
            ->assertJsonPath('data.fees', '-1.50000000')
            ->assertJsonPath('data.reversal_of_id', $original->id);

        $this->assertDatabaseHas('transactions', [
            'id' => $original->id,
            'quantity_delta' => '3.25000000',
            'note' => 'Original entry',
        ]);
        $this->assertDatabaseCount('transactions', 2);

        $this->actingAs($owner)
            ->postJson("/api/v1/portfolios/{$portfolio->id}/transactions/{$original->id}/reverse", [
                'reason' => 'Second attempt',
            ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'transaction_already_reversed');

        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_reversal_is_rejected_when_it_would_make_the_current_quantity_negative(): void
    {
        $owner = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);
        $asset = Asset::factory()->for($portfolio)->create();
        $buy = Transaction::factory()->forAsset($asset)->create([
            'created_by' => $owner->id,
            'quantity_delta' => '5.00000000',
        ]);
        Transaction::factory()->forAsset($asset)->create([
            'created_by' => $owner->id,
            'kind' => TransactionKind::Sell,
            'quantity_delta' => '-4.00000000',
        ]);

        $this->actingAs($owner)
            ->postJson("/api/v1/portfolios/{$portfolio->id}/transactions/{$buy->id}/reverse", [
                'reason' => 'Would overdraw',
            ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'insufficient_quantity');

        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_history_supports_filters_and_reverse_chronological_cursor_pagination(): void
    {
        $this->travelTo('2026-10-24 12:00:00');

        $viewer = User::factory()->create();
        $portfolio = $this->portfolioWithMember($viewer, PortfolioRole::Viewer);
        $asset = Asset::factory()->for($portfolio)->create();
        $otherAsset = Asset::factory()->for($portfolio)->create();
        $creator = User::factory()->create();

        Transaction::factory()->forAsset($asset)->count(16)->sequence(
            fn ($sequence): array => [
                'created_by' => $creator->id,
                'occurred_at' => now()->subMinutes($sequence->index),
            ],
        )->create();
        Transaction::factory()->forAsset($otherAsset)->create([
            'created_by' => $creator->id,
            'kind' => TransactionKind::Adjustment,
            'quantity_delta' => '1.00000000',
        ]);

        $firstPage = $this->actingAs($viewer)
            ->getJson("/api/v1/portfolios/{$portfolio->id}/transactions?asset_id={$asset->id}&kind=buy")
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('data.0.occurred_at', '2026-10-24T12:00:00.000000Z');

        $cursor = $firstPage->json('meta.next_cursor');
        $this->assertNotNull($cursor);

        $secondPage = $this->actingAs($viewer)
            ->getJson("/api/v1/portfolios/{$portfolio->id}/transactions?asset_id={$asset->id}&kind=buy&cursor=".urlencode($cursor))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.occurred_at', '2026-10-24T11:45:00.000000Z');
    }

    public function test_history_rejects_an_asset_filter_from_another_portfolio(): void
    {
        $viewer = User::factory()->create();
        $portfolio = $this->portfolioWithMember($viewer, PortfolioRole::Viewer);
        $otherAsset = Asset::factory()->create();

        $this->actingAs($viewer)
            ->getJson("/api/v1/portfolios/{$portfolio->id}/transactions?asset_id={$otherAsset->id}")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.fields.asset_id.0', 'The selected asset id is invalid.');
    }

    /** @return array<string, array{PortfolioRole}> */
    public static function writableRoles(): array
    {
        return [
            'owner' => [PortfolioRole::Owner],
            'editor' => [PortfolioRole::Editor],
        ];
    }

    /** @return array<string, array{mixed, TransactionKind}> */
    public static function invalidQuantities(): array
    {
        return [
            'JSON number' => [1.25, TransactionKind::Buy],
            'zero' => ['0.00000000', TransactionKind::Buy],
            'too many decimal places' => ['1.000000001', TransactionKind::Buy],
            'negative sell input' => ['-1.00000000', TransactionKind::Sell],
        ];
    }

    /** @return array<string, mixed> */
    private function transactionPayload(TransactionKind $kind, mixed $quantity): array
    {
        return [
            'kind' => $kind->value,
            'quantity' => $quantity,
            'unit_price' => '120.00000000',
            'fees' => '1.25000000',
            'occurred_at' => '2026-10-20T09:30:00Z',
            'note' => 'Recorded manually',
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
