<?php

namespace App\Http\Requests\Membership;

use App\Enums\PortfolioRole;
use App\Http\Requests\ApiFormRequest;
use App\Models\Portfolio;
use Illuminate\Validation\Rule;

class StoreMembershipRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $portfolio = $this->route('portfolio');

        return $portfolio instanceof Portfolio
            && ($this->user()?->can('manageMembers', $portfolio) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'role' => mb_strtolower(trim((string) $this->input('role'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::enum(PortfolioRole::class)],
        ];
    }

    protected function allowedFields(): array
    {
        return ['email', 'role'];
    }
}
