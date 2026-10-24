<?php

namespace Database\Seeders;

use App\Enums\AssetType;
use App\Enums\PortfolioRole;
use App\Enums\TransactionKind;
use App\Enums\TransactionSource;
use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\PortfolioMembership;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $owner = $this->demoUser('owner@example.com', 'Demo Owner');
        $editor = $this->demoUser('editor@example.com', 'Demo Editor');
        $viewer = $this->demoUser('viewer@example.com', 'Demo Viewer');

        $portfolio = Portfolio::query()->updateOrCreate(
            ['name' => 'Demo Household'],
            ['base_currency' => 'USD'],
        );

        foreach ([
            [$owner, PortfolioRole::Owner],
            [$editor, PortfolioRole::Editor],
            [$viewer, PortfolioRole::Viewer],
        ] as [$user, $role]) {
            PortfolioMembership::query()->updateOrCreate(
                ['portfolio_id' => $portfolio->getKey(), 'user_id' => $user->getKey()],
                ['role' => $role],
            );
        }

        $stock = $this->demoAsset($portfolio, [
            'type' => AssetType::Stock,
            'name' => 'Global Equity ETF',
            'symbol' => 'GLOB',
            'current_price' => '125.34000000',
        ]);
        $crypto = $this->demoAsset($portfolio, [
            'type' => AssetType::Crypto,
            'name' => 'Bitcoin',
            'symbol' => 'BTC',
            'current_price' => '68000.12345678',
        ]);
        $property = $this->demoAsset($portfolio, [
            'type' => AssetType::RealEstate,
            'name' => 'Damascus Apartment',
            'symbol' => null,
            'current_price' => '180000.00000000',
        ]);
        $cash = $this->demoAsset($portfolio, [
            'type' => AssetType::Cash,
            'name' => 'Emergency Fund',
            'symbol' => null,
            'current_price' => '1.00000000',
        ]);

        $this->demoTransaction($stock, $owner, TransactionKind::Buy, '24.00000000', '125.34000000', 'Demo opening stock position');
        $this->demoTransaction($crypto, $owner, TransactionKind::Buy, '0.15000000', '68000.12345678', 'Demo opening crypto position');
        $this->demoTransaction($property, $owner, TransactionKind::Buy, '1.00000000', '180000.00000000', 'Demo property valuation');
        $this->demoTransaction($cash, $owner, TransactionKind::Deposit, '25000.00000000', '1.00000000', 'Demo cash deposit');
    }

    private function demoUser(string $email, string $name): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );
    }

    /** @param array{type: AssetType, name: string, symbol: ?string, current_price: string} $data */
    private function demoAsset(Portfolio $portfolio, array $data): Asset
    {
        return Asset::query()->updateOrCreate(
            ['portfolio_id' => $portfolio->getKey(), 'name' => $data['name']],
            [
                'type' => $data['type'],
                'symbol' => $data['symbol'],
                'currency' => 'USD',
                'current_price' => $data['current_price'],
                'price_as_of' => now(),
                'metadata' => ['price_source' => 'demo_seed'],
                'archived_at' => null,
            ],
        );
    }

    private function demoTransaction(
        Asset $asset,
        User $creator,
        TransactionKind $kind,
        string $quantityDelta,
        string $unitPrice,
        string $note,
    ): void {
        Transaction::query()->firstOrCreate(
            ['asset_id' => $asset->getKey(), 'note' => $note],
            [
                'created_by' => $creator->getKey(),
                'kind' => $kind,
                'quantity_delta' => $quantityDelta,
                'unit_price' => $unitPrice,
                'fees' => '0.00000000',
                'occurred_at' => now()->subDays(7),
                'source' => TransactionSource::Api,
            ],
        );
    }
}
