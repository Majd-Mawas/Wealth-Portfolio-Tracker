<?php

namespace App\Http\Requests\Transaction;

use App\Http\Requests\ApiFormRequest;
use App\Models\Portfolio;

class ReverseTransactionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $portfolio = $this->route('portfolio');

        return $portfolio instanceof Portfolio
            && ($this->user()?->can('recordTransactions', $portfolio) ?? false);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    protected function allowedFields(): array
    {
        return ['reason'];
    }
}
