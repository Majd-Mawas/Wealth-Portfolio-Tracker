<?php

namespace App\Http\Requests\Portfolio;

use App\Http\Requests\ApiFormRequest;
use App\Models\Portfolio;

class StorePortfolioRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Portfolio::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'base_currency' => mb_strtoupper(trim((string) $this->input('base_currency'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'base_currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
        ];
    }

    protected function allowedFields(): array
    {
        return ['name', 'base_currency'];
    }
}
