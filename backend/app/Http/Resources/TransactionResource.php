<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset' => [
                'id' => $this->asset->id,
                'name' => $this->asset->name,
                'type' => $this->asset->type->value,
                'symbol' => $this->asset->symbol,
            ],
            'kind' => $this->kind->value,
            'quantity_delta' => $this->quantity_delta,
            'unit_price' => $this->unit_price,
            'fees' => $this->fees,
            'occurred_at' => $this->occurred_at?->toISOString(),
            'note' => $this->note,
            'source' => $this->source->value,
            'reversal_of_id' => $this->reversal_of_id,
            'created_by' => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
