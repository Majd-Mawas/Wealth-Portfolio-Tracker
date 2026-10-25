<?php

namespace Database\Seeders;

use App\Enums\AssetType;
use App\Enums\PortfolioRole;
use App\Enums\TransactionKind;
use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\PortfolioMembership;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AssetService;
use App\Services\MembershipService;
use App\Services\PortfolioService;
use App\Services\TransactionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class DemoPortfolioSeeder extends Seeder
{
    public const string PASSWORD = 'DemoOnly!2026';

    private const string PRICE_AS_OF = '2026-10-20T12:00:00+00:00';

    public function __construct(
        private readonly PortfolioService $portfolios,
        private readonly MembershipService $memberships,
        private readonly AssetService $assets,
        private readonly TransactionService $transactions,
    ) {}

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Demo portfolio data may only be seeded in local or testing environments.');
        }

        DB::transaction(function (): void {
            $users = [
                'alex' => $this->user('Alex Morgan', 'alex@example.test'),
                'sarah' => $this->user('Sarah Bennett', 'sarah@example.test'),
                'daniel' => $this->user('Daniel Reed', 'daniel@example.test'),
                'emma' => $this->user('Emma Wilson', 'emma@example.test'),
            ];

            $this->seedGrowthPortfolio($users);
            $this->seedFamilyWealth($users);
            $this->seedRetirementSavings($users);
            $this->seedCryptoHoldings($users);
        });
    }

    /** @param array<string, User> $users */
    private function seedGrowthPortfolio(array $users): void
    {
        $portfolio = $this->portfolio($users['alex'], 'Growth Portfolio', 'USD');
        $this->membership($users['alex'], $portfolio, $users['sarah'], PortfolioRole::Editor);
        $this->membership($users['alex'], $portfolio, $users['daniel'], PortfolioRole::Viewer);

        $apple = $this->asset($users['alex'], $portfolio, AssetType::Stock, 'Apple', 'AAPL', '232.50000000');
        $microsoft = $this->asset($users['alex'], $portfolio, AssetType::Stock, 'Microsoft', 'MSFT', '518.75000000');
        $nvidia = $this->asset($users['alex'], $portfolio, AssetType::Stock, 'NVIDIA', 'NVDA', '195.25000000');
        $cash = $this->asset($users['alex'], $portfolio, AssetType::Cash, 'USD Cash Balance', null, '1.00000000');

        $this->history($users, $portfolio, $apple, [
            ['alex', TransactionKind::Buy, '20', '189.25', '4.95', '2025-11-04T14:30:00+00:00', '[DEMO] Initial Apple purchase'],
            ['sarah', TransactionKind::Buy, '8.5', '207.40', '4.95', '2026-02-12T15:10:00+00:00', '[DEMO] Added Apple shares'],
            ['sarah', TransactionKind::Sell, '5', '198.10', '4.95', '2026-05-20T13:45:00+00:00', '[DEMO] Trimmed Apple position'],
            ['alex', TransactionKind::Buy, '3', '226.00', '4.95', '2026-09-09T14:05:00+00:00', '[DEMO] Apple reinvestment'],
        ]);
        $this->history($users, $portfolio, $microsoft, [
            ['alex', TransactionKind::Buy, '12', '432.10', '4.95', '2025-12-02T15:00:00+00:00', '[DEMO] Initial Microsoft purchase'],
            ['sarah', TransactionKind::Buy, '4', '410.25', '4.95', '2026-04-18T14:20:00+00:00', '[DEMO] Added Microsoft shares'],
            ['alex', TransactionKind::Sell, '2', '502.35', '4.95', '2026-08-01T14:40:00+00:00', '[DEMO] Microsoft partial sale'],
        ]);
        $this->history($users, $portfolio, $nvidia, [
            ['sarah', TransactionKind::Buy, '30', '134.80', '4.95', '2026-01-15T15:25:00+00:00', '[DEMO] Initial NVIDIA purchase'],
            ['alex', TransactionKind::Buy, '10', '119.50', '4.95', '2026-03-28T14:15:00+00:00', '[DEMO] NVIDIA market pullback purchase'],
            ['sarah', TransactionKind::Sell, '8', '172.40', '4.95', '2026-07-22T13:55:00+00:00', '[DEMO] NVIDIA profit taking'],
            ['alex', TransactionKind::Buy, '5.25', '187.60', '4.95', '2026-10-10T14:10:00+00:00', '[DEMO] NVIDIA portfolio top-up'],
        ]);
        $this->history($users, $portfolio, $cash, [
            ['alex', TransactionKind::Deposit, '20000', '1', '0', '2025-11-01T09:00:00+00:00', '[DEMO] Opening growth cash reserve'],
            ['alex', TransactionKind::Deposit, '5000', '1', '0', '2026-01-05T09:00:00+00:00', '[DEMO] Quarterly growth contribution'],
            ['sarah', TransactionKind::Withdrawal, '3500', '1', '0', '2026-06-15T09:00:00+00:00', '[DEMO] Planned cash withdrawal'],
            ['alex', TransactionKind::Deposit, '2500', '1', '0', '2026-09-01T09:00:00+00:00', '[DEMO] Autumn growth contribution'],
            ['alex', TransactionKind::Deposit, '750', '1', '0', '2026-10-05T09:00:00+00:00', '[DEMO] Monthly growth contribution'],
        ]);
    }

    /** @param array<string, User> $users */
    private function seedFamilyWealth(array $users): void
    {
        $portfolio = $this->portfolio($users['sarah'], 'Family Wealth', 'USD');
        $this->membership($users['sarah'], $portfolio, $users['alex'], PortfolioRole::Editor);
        $this->membership($users['sarah'], $portfolio, $users['emma'], PortfolioRole::Viewer);

        $apartment = $this->asset($users['sarah'], $portfolio, AssetType::RealEstate, 'Residential Apartment', null, '285000.00000000');
        $rental = $this->asset($users['sarah'], $portfolio, AssetType::RealEstate, 'Rental Property', null, '410000.00000000');
        $cash = $this->asset($users['sarah'], $portfolio, AssetType::Cash, 'Family Cash Reserve', null, '1.00000000');

        $this->history($users, $portfolio, $apartment, [
            ['sarah', TransactionKind::Buy, '1', '270000', '0', '2025-11-20T10:00:00+00:00', '[DEMO] Residential apartment acquisition'],
        ]);
        $this->history($users, $portfolio, $rental, [
            ['alex', TransactionKind::Buy, '1', '395000', '0', '2026-02-15T10:00:00+00:00', '[DEMO] Rental property acquisition'],
        ]);
        $this->history($users, $portfolio, $cash, [
            ['sarah', TransactionKind::Deposit, '60000', '1', '0', '2025-11-01T08:00:00+00:00', '[DEMO] Opening family reserve'],
            ['alex', TransactionKind::Withdrawal, '12000', '1', '0', '2026-01-10T08:30:00+00:00', '[DEMO] Home improvement payment'],
            ['sarah', TransactionKind::Deposit, '8500', '1', '0', '2026-05-05T08:30:00+00:00', '[DEMO] Family savings contribution'],
            ['alex', TransactionKind::Withdrawal, '2500', '1', '0', '2026-09-12T08:30:00+00:00', '[DEMO] Annual property maintenance'],
        ]);
    }

    /** @param array<string, User> $users */
    private function seedRetirementSavings(array $users): void
    {
        $portfolio = $this->portfolio($users['daniel'], 'Retirement Savings', 'EUR');
        $this->membership($users['daniel'], $portfolio, $users['emma'], PortfolioRole::Editor);
        $this->membership($users['daniel'], $portfolio, $users['alex'], PortfolioRole::Viewer);

        $equity = $this->asset($users['daniel'], $portfolio, AssetType::Stock, 'European Equity ETF', 'EUEQ', '84.75000000');
        $bonds = $this->asset($users['daniel'], $portfolio, AssetType::Stock, 'Euro Bond ETF', 'EBND', '102.40000000');
        $cash = $this->asset($users['daniel'], $portfolio, AssetType::Cash, 'EUR Cash Balance', null, '1.00000000');

        $this->history($users, $portfolio, $equity, [
            ['daniel', TransactionKind::Buy, '150', '77.20', '6.50', '2025-11-12T11:00:00+00:00', '[DEMO] Initial European equity allocation'],
            ['emma', TransactionKind::Buy, '50', '80.10', '4.50', '2026-04-02T11:00:00+00:00', '[DEMO] Retirement equity contribution'],
            ['daniel', TransactionKind::Sell, '20', '86.30', '4.50', '2026-08-18T11:00:00+00:00', '[DEMO] Retirement equity rebalance'],
        ]);
        $this->history($users, $portfolio, $bonds, [
            ['daniel', TransactionKind::Buy, '100', '99.80', '5.00', '2025-12-08T11:00:00+00:00', '[DEMO] Initial euro bond allocation'],
            ['emma', TransactionKind::Buy, '25', '101.10', '3.50', '2026-06-06T11:00:00+00:00', '[DEMO] Added euro bond units'],
        ]);
        $this->history($users, $portfolio, $cash, [
            ['daniel', TransactionKind::Deposit, '35000', '1', '0', '2025-11-01T08:00:00+00:00', '[DEMO] Opening EUR retirement cash'],
            ['daniel', TransactionKind::Deposit, '7000', '1', '0', '2026-03-01T08:00:00+00:00', '[DEMO] Annual retirement contribution'],
            ['emma', TransactionKind::Withdrawal, '2500', '1', '0', '2026-09-20T08:00:00+00:00', '[DEMO] Retirement account fee reserve'],
        ]);
    }

    /** @param array<string, User> $users */
    private function seedCryptoHoldings(array $users): void
    {
        $portfolio = $this->portfolio($users['emma'], 'Crypto Holdings', 'USD');

        $bitcoin = $this->asset($users['emma'], $portfolio, AssetType::Crypto, 'Bitcoin', 'BTC', '62500.00000000');
        $ethereum = $this->asset($users['emma'], $portfolio, AssetType::Crypto, 'Ethereum', 'ETH', '3350.00000000');
        $solana = $this->asset($users['emma'], $portfolio, AssetType::Crypto, 'Solana', 'SOL', '145.00000000');

        $this->history($users, $portfolio, $bitcoin, [
            ['emma', TransactionKind::Buy, '0.15', '54000', '18.50', '2025-11-18T17:00:00+00:00', '[DEMO] Initial Bitcoin purchase'],
            ['emma', TransactionKind::Buy, '0.05', '59000', '9.00', '2026-03-10T17:00:00+00:00', '[DEMO] Added Bitcoin position'],
            ['emma', TransactionKind::Sell, '0.03', '67000', '8.50', '2026-08-25T17:00:00+00:00', '[DEMO] Bitcoin partial sale'],
        ]);
        $this->history($users, $portfolio, $ethereum, [
            ['emma', TransactionKind::Buy, '2', '2850', '12.00', '2025-12-14T17:00:00+00:00', '[DEMO] Initial Ethereum purchase'],
            ['emma', TransactionKind::Buy, '1.5', '3100', '10.00', '2026-04-22T17:00:00+00:00', '[DEMO] Added Ethereum position'],
            ['emma', TransactionKind::Sell, '0.5', '3600', '8.00', '2026-09-18T17:00:00+00:00', '[DEMO] Ethereum partial sale'],
        ]);
        $this->history($users, $portfolio, $solana, [
            ['emma', TransactionKind::Buy, '30', '108', '7.50', '2026-01-08T17:00:00+00:00', '[DEMO] Initial Solana purchase'],
            ['emma', TransactionKind::Buy, '20', '122', '6.50', '2026-05-16T17:00:00+00:00', '[DEMO] Added Solana position'],
            ['emma', TransactionKind::Sell, '10', '158', '6.00', '2026-10-02T17:00:00+00:00', '[DEMO] Solana partial sale'],
        ]);
    }

    private function user(string $name, string $email): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => self::PASSWORD,
                'email_verified_at' => '2025-10-25T00:00:00+00:00',
            ],
        );
    }

    private function portfolio(User $owner, string $name, string $currency): Portfolio
    {
        $portfolio = Portfolio::query()
            ->where('name', $name)
            ->whereHas('memberships', function (Builder $query) use ($owner): void {
                $query->where('user_id', $owner->getKey())
                    ->where('role', PortfolioRole::Owner->value);
            })
            ->first();

        if ($portfolio === null) {
            return $this->portfolios->create($owner, [
                'name' => $name,
                'base_currency' => $currency,
            ]);
        }

        return $this->portfolios->update($owner, $portfolio, [
            'name' => $name,
            'base_currency' => $currency,
        ]);
    }

    private function membership(
        User $owner,
        Portfolio $portfolio,
        User $member,
        PortfolioRole $role,
    ): void {
        $membership = PortfolioMembership::query()
            ->where('portfolio_id', $portfolio->getKey())
            ->where('user_id', $member->getKey())
            ->first();

        if ($membership === null) {
            $this->memberships->add($owner, $portfolio, $member->email, $role);

            return;
        }

        if ($membership->role !== $role) {
            $this->memberships->update($owner, $portfolio, $membership, $role);
        }
    }

    private function asset(
        User $owner,
        Portfolio $portfolio,
        AssetType $type,
        string $name,
        ?string $symbol,
        string $currentPrice,
    ): Asset {
        $data = [
            'type' => $type->value,
            'name' => $name,
            'symbol' => $symbol,
            'currency' => $portfolio->base_currency,
            'current_price' => $currentPrice,
            'price_as_of' => self::PRICE_AS_OF,
            'metadata' => [
                'demo_data' => true,
                'price_source' => 'fictional_demo_reference',
            ],
        ];
        $asset = $portfolio->assets()->where('name', $name)->first();

        if ($asset === null) {
            return $this->assets->create($owner, $portfolio, $data);
        }

        return $this->assets->update($owner, $portfolio, $asset, $data);
    }

    /**
     * @param  array<string, User>  $users
     * @param  list<array{string, TransactionKind, string, string, string, string, string}>  $entries
     */
    private function history(array $users, Portfolio $portfolio, Asset $asset, array $entries): void
    {
        foreach ($entries as [$actorKey, $kind, $quantity, $unitPrice, $fees, $occurredAt, $note]) {
            $exists = Transaction::query()
                ->where('asset_id', $asset->getKey())
                ->where('note', $note)
                ->exists();

            if ($exists) {
                continue;
            }

            $this->transactions->record($users[$actorKey], $portfolio, $asset, [
                'kind' => $kind->value,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'fees' => $fees,
                'occurred_at' => $occurredAt,
                'note' => $note,
            ]);
        }
    }
}
