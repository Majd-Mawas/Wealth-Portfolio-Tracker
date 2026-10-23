<?php

namespace App\Http\Requests\Portfolio;

use App\Http\Requests\ApiFormRequest;
use App\Models\Portfolio;
use Illuminate\Validation\Validator;

class UpdatePortfolioRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $portfolio = $this->route('portfolio');

        return $portfolio instanceof Portfolio
            && ($this->user()?->can('update', $portfolio) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $changes = [];

        if ($this->has('name')) {
            $changes['name'] = trim((string) $this->input('name'));
        }

        if ($this->has('base_currency')) {
            $changes['base_currency'] = mb_strtoupper(trim((string) $this->input('base_currency')));
        }

        $this->merge($changes);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'base_currency' => ['sometimes', 'required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            if ($this->keys() === []) {
                $validator->errors()->add('request', 'At least one field must be provided.');
            }
        });
    }

    protected function allowedFields(): array
    {
        return ['name', 'base_currency'];
    }
}
