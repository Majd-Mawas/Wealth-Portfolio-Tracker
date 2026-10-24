<?php

namespace App\Http\Requests\Asset;

use App\Enums\AssetType;
use App\Http\Requests\ApiFormRequest;
use App\Models\Asset;
use App\Models\Portfolio;
use Brick\Math\BigDecimal;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAssetRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $portfolio = $this->route('portfolio');

        return $portfolio instanceof Portfolio
            && ($this->user()?->can('manageAssets', $portfolio) ?? false);
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(AssetType::class)],
            'name' => ['sometimes', 'string', 'max:160'],
            'symbol' => ['sometimes', 'nullable', 'string', 'max:32'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'current_price' => ['sometimes', 'string', 'regex:/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,8})?$/', 'not_regex:/^0(?:\.0{1,8})?$/'],
            'price_as_of' => ['required_with:current_price', 'date'],
            'metadata' => ['sometimes', 'nullable', 'array', 'max:20'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var Portfolio $portfolio */
            $portfolio = $this->route('portfolio');
            /** @var Asset $asset */
            $asset = $this->route('asset');
            $type = $validator->errors()->has('type')
                ? null
                : AssetType::tryFrom($this->input('type', $asset->type->value));

            if ($this->has('currency')
                && ! $validator->errors()->has('currency')
                && $this->input('currency') !== $portfolio->base_currency) {
                $validator->errors()->add('currency', 'The currency must match the portfolio base currency.');
            }

            if ($type === AssetType::Cash
                && $this->has('current_price')
                && ! $validator->errors()->has('current_price')
                && ! BigDecimal::of($this->input('current_price'))->isEqualTo('1')) {
                $validator->errors()->add('current_price', 'The current price for cash must be 1.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $updates = [];

        if ($this->has('currency')) {
            $updates['currency'] = $this->string('currency')->upper()->toString();
        }

        if ($this->filled('symbol')) {
            $updates['symbol'] = $this->string('symbol')->upper()->toString();
        }

        $this->merge($updates);
    }

    protected function allowedFields(): array
    {
        return ['type', 'name', 'symbol', 'currency', 'current_price', 'price_as_of', 'metadata'];
    }
}
