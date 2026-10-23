<?php

namespace Tests\Feature;

use App\Enums\PortfolioRole;
use App\Models\Portfolio;
use App\Models\PortfolioMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PortfolioAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_creates_a_portfolio_and_becomes_its_owner(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/portfolios', [
            'name' => 'Household Wealth',
            'base_currency' => 'usd',
        ]);

        $portfolioId = $response
            ->assertCreated()
            ->assertJsonPath('data.base_currency', 'USD')
            ->assertJsonPath('data.role', 'owner')
            ->assertJsonPath('data.capabilities.manage_members', true)
            ->json('data.id');

        $this->assertDatabaseHas('portfolio_memberships', [
            'portfolio_id' => $portfolioId,
            'user_id' => $user->id,
            'role' => PortfolioRole::Owner->value,
        ]);
    }

    public function test_client_cannot_supply_an_owner_id(): void
    {
        $actor = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($actor)->postJson('/api/v1/portfolios', [
            'name' => 'Household Wealth',
            'base_currency' => 'USD',
            'owner_id' => $otherUser->id,
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.fields.owner_id.0', 'The owner_id field is not allowed.');

        $this->assertDatabaseCount('portfolios', 0);
    }

    public function test_index_returns_only_the_users_portfolios(): void
    {
        $user = User::factory()->create();
        $visible = $this->portfolioWithMember($user, PortfolioRole::Viewer);
        $hidden = Portfolio::factory()->create();
        PortfolioMembership::factory()->owner()->create(['portfolio_id' => $hidden->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/portfolios');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($visible->id, $response->json('data.0.id'));
    }

    public function test_non_member_cannot_discover_or_modify_a_portfolio(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);

        $this->actingAs($outsider)
            ->getJson("/api/v1/portfolios/{$portfolio->id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');

        $this->actingAs($outsider)
            ->patchJson("/api/v1/portfolios/{$portfolio->id}", ['name' => 'Stolen'])
            ->assertNotFound();

        $this->assertDatabaseHas('portfolios', [
            'id' => $portfolio->id,
            'name' => $portfolio->name,
        ]);
    }

    public function test_owner_can_update_and_soft_delete_a_portfolio(): void
    {
        $owner = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);

        $this->actingAs($owner)
            ->patchJson("/api/v1/portfolios/{$portfolio->id}", ['name' => 'Updated Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');

        $this->actingAs($owner)
            ->deleteJson("/api/v1/portfolios/{$portfolio->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('portfolios', ['id' => $portfolio->id]);
    }

    #[DataProvider('nonOwnerRoles')]
    public function test_non_owners_can_view_but_receive_403_when_modifying_portfolios(PortfolioRole $role): void
    {
        $user = User::factory()->create();
        $portfolio = $this->portfolioWithMember($user, $role);

        $this->actingAs($user)
            ->getJson("/api/v1/portfolios/{$portfolio->id}")
            ->assertOk()
            ->assertJsonPath('data.role', $role->value);

        $this->actingAs($user)
            ->patchJson("/api/v1/portfolios/{$portfolio->id}", ['name' => 'Forbidden'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');

        $this->actingAs($user)
            ->deleteJson("/api/v1/portfolios/{$portfolio->id}")
            ->assertForbidden();
    }

    /** @return array<string, array{PortfolioRole}> */
    public static function nonOwnerRoles(): array
    {
        return [
            'editor' => [PortfolioRole::Editor],
            'viewer' => [PortfolioRole::Viewer],
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
