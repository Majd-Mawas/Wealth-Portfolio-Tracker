<?php

namespace App\Http\Requests\Asset;

use App\Enums\AssetType;
use App\Http\Requests\ApiFormRequest;
use App\Models\Portfolio;
use Illuminate\Validation\Rule;

class ListAssetsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $portfolio = $this->route('portfolio');

        return $portfolio instanceof Portfolio
            && ($this->user()?->can('view', $portfolio) ?? false);
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(AssetType::class)],
            'include_archived' => ['sometimes', 'boolean'],
            'cursor' => ['sometimes', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('include_archived'))) {
            $value = filter_var($this->input('include_archived'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($value !== null) {
                $this->merge(['include_archived' => $value]);
            }
        }
    }

    protected function allowedFields(): array
    {
        return ['type', 'include_archived', 'cursor'];
    }
}
