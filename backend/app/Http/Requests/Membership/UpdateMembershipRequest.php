<?php

namespace App\Http\Requests\Membership;

use App\Enums\PortfolioRole;
use App\Http\Requests\ApiFormRequest;
use App\Models\Portfolio;
use Illuminate\Validation\Rule;

class UpdateMembershipRequest extends ApiFormRequest
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
            'role' => mb_strtolower(trim((string) $this->input('role'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(PortfolioRole::class)],
        ];
    }

    protected function allowedFields(): array
    {
        return ['role'];
    }
}
