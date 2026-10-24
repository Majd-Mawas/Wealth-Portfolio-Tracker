<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'portfolio_id' => $this->portfolio_id,
            'type' => $this->type->value,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'currency' => $this->currency,
            'current_price' => $this->current_price,
            'price_as_of' => $this->price_as_of?->toISOString(),
            'quantity' => $this->resource->getAttribute('calculated_quantity'),
            'value' => $this->resource->getAttribute('calculated_value'),
            'metadata' => $this->metadata,
            'archived_at' => $this->archived_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
