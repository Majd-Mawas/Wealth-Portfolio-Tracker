<?php

namespace Tests\Feature;

use App\Enums\PortfolioRole;
use App\Models\Portfolio;
use App\Models\PortfolioMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MembershipAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_any_member_can_list_memberships(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);
        $this->addMember($portfolio, $viewer, PortfolioRole::Viewer);

        $this->actingAs($viewer)
            ->getJson("/api/v1/portfolios/{$portfolio->id}/members")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_owner_can_add_change_and_remove_a_member(): void
    {
        $owner = User::factory()->create();
        $candidate = User::factory()->create(['email' => 'editor@example.com']);
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);

        $membershipId = $this->actingAs($owner)
            ->postJson("/api/v1/portfolios/{$portfolio->id}/members", [
                'email' => 'EDITOR@example.com',
                'role' => 'editor',
            ])
            ->assertCreated()
            ->assertJsonPath('data.user.id', $candidate->id)
            ->assertJsonPath('data.role', 'editor')
            ->json('data.id');

        $this->actingAs($owner)
            ->patchJson("/api/v1/portfolios/{$portfolio->id}/members/{$membershipId}", [
                'role' => 'viewer',
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'viewer');

        $this->actingAs($owner)
            ->deleteJson("/api/v1/portfolios/{$portfolio->id}/members/{$membershipId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('portfolio_memberships', [
            'portfolio_id' => $portfolio->id,
            'user_id' => $candidate->id,
        ]);
    }

    public function test_editor_cannot_add_change_or_remove_members(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $candidate = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);
        $editorMembership = $this->addMember($portfolio, $editor, PortfolioRole::Editor);

        $this->actingAs($editor)
            ->postJson("/api/v1/portfolios/{$portfolio->id}/members", [
                'email' => $candidate->email,
                'role' => 'viewer',
            ])
            ->assertForbidden();

        $this->actingAs($editor)
            ->patchJson("/api/v1/portfolios/{$portfolio->id}/members/{$editorMembership->id}", [
                'role' => 'owner',
            ])
            ->assertForbidden();

        $this->actingAs($editor)
            ->deleteJson("/api/v1/portfolios/{$portfolio->id}/members/{$editorMembership->id}")
            ->assertForbidden();
    }

    public function test_viewer_cannot_manage_members(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $candidate = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);
        $this->addMember($portfolio, $viewer, PortfolioRole::Viewer);

        $this->actingAs($viewer)
            ->postJson("/api/v1/portfolios/{$portfolio->id}/members", [
                'email' => $candidate->email,
                'role' => 'viewer',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_last_owner_cannot_be_demoted_or_removed(): void
    {
        $owner = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);
        $membership = PortfolioMembership::query()->where('portfolio_id', $portfolio->id)->firstOrFail();

        $this->actingAs($owner)
            ->patchJson("/api/v1/portfolios/{$portfolio->id}/members/{$membership->id}", [
                'role' => 'viewer',
            ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'last_owner_required');

        $this->actingAs($owner)
            ->deleteJson("/api/v1/portfolios/{$portfolio->id}/members/{$membership->id}")
            ->assertConflict()
            ->assertJsonPath('error.code', 'last_owner_required');
    }

    public function test_owner_can_transfer_ownership_before_demoting_themself(): void
    {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);
        $ownerMembership = PortfolioMembership::query()->where('portfolio_id', $portfolio->id)->firstOrFail();
        $this->addMember($portfolio, $newOwner, PortfolioRole::Owner);

        $this->actingAs($owner)
            ->patchJson("/api/v1/portfolios/{$portfolio->id}/members/{$ownerMembership->id}", [
                'role' => 'viewer',
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'viewer');
    }

    public function test_nested_membership_must_belong_to_the_route_portfolio(): void
    {
        $owner = User::factory()->create();
        $first = $this->portfolioWithMember($owner, PortfolioRole::Owner);
        $second = $this->portfolioWithMember($owner, PortfolioRole::Owner);
        $secondMembership = PortfolioMembership::query()->where('portfolio_id', $second->id)->firstOrFail();

        $this->actingAs($owner)
            ->patchJson("/api/v1/portfolios/{$first->id}/members/{$secondMembership->id}", [
                'role' => 'viewer',
            ])
            ->assertNotFound();
    }

    public function test_non_member_cannot_access_membership_endpoints(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $portfolio = $this->portfolioWithMember($owner, PortfolioRole::Owner);

        $this->actingAs($outsider)
            ->getJson("/api/v1/portfolios/{$portfolio->id}/members")
            ->assertNotFound();
    }

    private function portfolioWithMember(User $user, PortfolioRole $role): Portfolio
    {
        $portfolio = Portfolio::factory()->create();
        $this->addMember($portfolio, $user, $role);

        return $portfolio;
    }

    private function addMember(
        Portfolio $portfolio,
        User $user,
        PortfolioRole $role,
    ): PortfolioMembership {
        return PortfolioMembership::factory()->create([
            'portfolio_id' => $portfolio->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }
}
