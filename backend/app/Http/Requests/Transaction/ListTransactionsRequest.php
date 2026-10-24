<?php

namespace App\Http\Requests\Transaction;

use App\Enums\TransactionKind;
use App\Http\Requests\ApiFormRequest;
use App\Models\Portfolio;
use Illuminate\Validation\Rule;

class ListTransactionsRequest extends ApiFormRequest
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
            'asset_id' => ['sometimes', 'integer', 'min:1'],
            'kind' => ['sometimes', Rule::enum(TransactionKind::class)],
            'occurred_from' => ['sometimes', 'date'],
            'occurred_to' => ['sometimes', 'date', 'after_or_equal:occurred_from'],
            'cursor' => ['sometimes', 'string'],
        ];
    }

    protected function allowedFields(): array
    {
        return ['asset_id', 'kind', 'occurred_from', 'occurred_to', 'cursor'];
    }
}
