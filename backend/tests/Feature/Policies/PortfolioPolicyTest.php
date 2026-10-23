<?php

namespace Tests\Feature\Policies;

use App\Enums\PortfolioRole;
use App\Models\Portfolio;
use App\Models\PortfolioMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PortfolioPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @param array<string, bool> $expectedAbilities */
    #[DataProvider('roleAbilityMatrix')]
    public function test_role_has_the_documented_portfolio_abilities(
        ?PortfolioRole $role,
        array $expectedAbilities,
    ): void {
        $user = User::factory()->create();
        $portfolio = Portfolio::factory()->create();

        if ($role !== null) {
            PortfolioMembership::factory()->create([
                'portfolio_id' => $portfolio->id,
                'user_id' => $user->id,
                'role' => $role,
            ]);
        }

        $roleLabel = $role?->value ?? 'non-member';

        foreach ($expectedAbilities as $ability => $expected) {
            $this->assertSame(
                $expected,
                Gate::forUser($user)->allows($ability, $portfolio),
                "Unexpected [{$ability}] decision for role [{$roleLabel}].",
            );
        }
    }

    public function test_authenticated_users_can_list_and_create_portfolios(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', Portfolio::class));
        $this->assertTrue(Gate::forUser($user)->allows('create', Portfolio::class));
    }

    /** @return array<string, array{?PortfolioRole, array<string, bool>}> */
    public static function roleAbilityMatrix(): array
    {
        return [
            'owner' => [
                PortfolioRole::Owner,
                [
                    'view' => true,
                    'update' => true,
                    'delete' => true,
                    'manageMembers' => true,
                    'manageAssets' => true,
                    'recordTransactions' => true,
                ],
            ],
            'editor' => [
                PortfolioRole::Editor,
                [
                    'view' => true,
                    'update' => false,
                    'delete' => false,
                    'manageMembers' => false,
                    'manageAssets' => true,
                    'recordTransactions' => true,
                ],
            ],
            'viewer' => [
                PortfolioRole::Viewer,
                [
                    'view' => true,
                    'update' => false,
                    'delete' => false,
                    'manageMembers' => false,
                    'manageAssets' => false,
                    'recordTransactions' => false,
                ],
            ],
            'non-member' => [
                null,
                [
                    'view' => false,
                    'update' => false,
                    'delete' => false,
                    'manageMembers' => false,
                    'manageAssets' => false,
                    'recordTransactions' => false,
                ],
            ],
        ];
    }
}
