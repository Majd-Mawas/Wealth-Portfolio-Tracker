<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortfolioResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $role = $user === null ? null : $this->resource->roleFor($user);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'base_currency' => $this->base_currency,
            'role' => $role?->value,
            'capabilities' => [
                'update_portfolio' => $role?->canManagePortfolio() ?? false,
                'manage_members' => $role?->canManageMembers() ?? false,
                'manage_assets' => $role?->canModifyPortfolioData() ?? false,
                'record_transactions' => $role?->canModifyPortfolioData() ?? false,
                'delete_portfolio' => $role?->canManagePortfolio() ?? false,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
