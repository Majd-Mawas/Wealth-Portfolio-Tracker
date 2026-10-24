<?php

namespace App\Http\Requests\Transaction;

use App\Enums\TransactionKind;
use App\Http\Requests\ApiFormRequest;
use App\Models\Portfolio;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $portfolio = $this->route('portfolio');

        return $portfolio instanceof Portfolio
            && ($this->user()?->can('recordTransactions', $portfolio) ?? false);
    }

    public function rules(): array
    {
        $positiveKinds = [
            TransactionKind::Buy->value,
            TransactionKind::Sell->value,
            TransactionKind::Deposit->value,
            TransactionKind::Withdrawal->value,
        ];

        return [
            'kind' => ['required', Rule::enum(TransactionKind::class), Rule::notIn([TransactionKind::Reversal->value])],
            'quantity' => [
                'required',
                'string',
                'regex:/^-?(0|[1-9][0-9]{0,15})(\.[0-9]{1,8})?$/',
                'not_regex:/^-?0(?:\.0{1,8})?$/',
                Rule::when(in_array($this->input('kind'), $positiveKinds, true), 'not_regex:/^-/', []),
            ],
            'unit_price' => ['nullable', 'string', 'regex:/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,8})?$/', 'not_regex:/^0(?:\.0{1,8})?$/'],
            'fees' => ['sometimes', 'string', 'regex:/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,8})?$/'],
            'occurred_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500', Rule::requiredIf($this->input('kind') === TransactionKind::Adjustment->value)],
        ];
    }

    protected function allowedFields(): array
    {
        return ['kind', 'quantity', 'unit_price', 'fees', 'occurred_at', 'note'];
    }
}
