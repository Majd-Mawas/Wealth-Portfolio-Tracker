<?php

namespace App\Http\Requests\Asset;

use App\Enums\AssetType;
use App\Http\Requests\ApiFormRequest;
use App\Models\Portfolio;
use Brick\Math\BigDecimal;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAssetRequest extends ApiFormRequest
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
            'type' => ['required', Rule::enum(AssetType::class)],
            'name' => ['required', 'string', 'max:160'],
            'symbol' => ['nullable', 'string', 'max:32'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'current_price' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,8})?$/', 'not_regex:/^0(?:\.0{1,8})?$/'],
            'price_as_of' => ['required', 'date'],
            'metadata' => ['nullable', 'array', 'max:20'],
            'opening' => ['sometimes', 'array:quantity,unit_price,occurred_at,note'],
            'opening.quantity' => ['required_with:opening', 'string', 'regex:/^(0|[1-9][0-9]{0,15})(\.[0-9]{1,8})?$/', 'not_regex:/^0(?:\.0{1,8})?$/'],
            'opening.unit_price' => ['nullable', 'string', 'regex:/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,8})?$/', 'not_regex:/^0(?:\.0{1,8})?$/'],
            'opening.occurred_at' => ['required_with:opening', 'date'],
            'opening.note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var Portfolio $portfolio */
            $portfolio = $this->route('portfolio');

            if (! $validator->errors()->has('currency')
                && $this->input('currency') !== $portfolio->base_currency) {
                $validator->errors()->add('currency', 'The currency must match the portfolio base currency.');
            }

            if (! $validator->errors()->hasAny(['type', 'current_price'])
                && $this->input('type') === AssetType::Cash->value
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
        return ['type', 'name', 'symbol', 'currency', 'current_price', 'price_as_of', 'metadata', 'opening'];
    }
}
