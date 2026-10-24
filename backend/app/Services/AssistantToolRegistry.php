<?php

namespace App\Services;

use App\Enums\AssetType;
use App\Enums\TransactionKind;
use App\Exceptions\AssistantToolException;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AssistantToolRegistry
{
    public function __construct(private readonly AssistantPortfolioReadService $reads) {}

    /** @return list<array<string, mixed>> */
    public function definitions(): array
    {
        return [
            [
                'type' => 'function',
                'name' => 'get_portfolio_summary',
                'description' => 'Get the active portfolio total manual valuation and allocation by asset type. Use only for the active portfolio.',
                'strict' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'portfolio_id' => ['type' => 'integer', 'description' => 'The active portfolio ID.'],
                    ],
                    'required' => ['portfolio_id'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'type' => 'function',
                'name' => 'list_portfolio_assets',
                'description' => 'List active assets and their manual valuation data for the active portfolio.',
                'strict' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'portfolio_id' => ['type' => 'integer', 'description' => 'The active portfolio ID.'],
                        'type' => [
                            'type' => ['string', 'null'],
                            'enum' => ['stock', 'crypto', 'real_estate', 'cash', null],
                            'description' => 'Optional asset type filter.',
                        ],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                    ],
                    'required' => ['portfolio_id', 'type', 'limit'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'type' => 'function',
                'name' => 'list_portfolio_transactions',
                'description' => 'List recent transactions for the active portfolio, optionally filtered by asset or transaction kind.',
                'strict' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'portfolio_id' => ['type' => 'integer', 'description' => 'The active portfolio ID.'],
                        'asset_id' => ['type' => ['integer', 'null'], 'description' => 'Optional asset ID from the active portfolio.'],
                        'kind' => [
                            'type' => ['string', 'null'],
                            'enum' => ['buy', 'sell', 'deposit', 'withdrawal', 'adjustment', 'reversal', null],
                            'description' => 'Optional transaction kind filter.',
                        ],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                    ],
                    'required' => ['portfolio_id', 'asset_id', 'kind', 'limit'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(
        User $actor,
        Portfolio $portfolio,
        string $tool,
        array $arguments,
    ): array {
        $data = match ($tool) {
            'get_portfolio_summary' => $this->summary($actor, $portfolio, $arguments),
            'list_portfolio_assets' => $this->assets($actor, $portfolio, $arguments),
            'list_portfolio_transactions' => $this->transactions($actor, $portfolio, $arguments),
            default => throw new AssistantToolException(
                'unknown_tool',
                'The requested assistant tool is not available.',
            ),
        };

        Log::info('Assistant read tool completed.', [
            'actor_id' => $actor->getKey(),
            'portfolio_id' => $portfolio->getKey(),
            'tool' => $tool,
        ]);

        return [
            'ok' => true,
            'tool' => $tool,
            'data' => $data,
        ];
    }

    /** @param array<string, mixed> $arguments */
    private function summary(User $actor, Portfolio $portfolio, array $arguments): array
    {
        $this->validate($portfolio, $arguments, ['portfolio_id'], [
            'portfolio_id' => ['required', 'integer'],
        ]);

        return $this->reads->summary($actor, $portfolio);
    }

    /** @param array<string, mixed> $arguments */
    private function assets(User $actor, Portfolio $portfolio, array $arguments): array
    {
        $validated = $this->validate($portfolio, $arguments, ['portfolio_id', 'type', 'limit'], [
            'portfolio_id' => ['required', 'integer'],
            'type' => ['present', 'nullable', Rule::enum(AssetType::class)],
            'limit' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        return $this->reads->assets(
            $actor,
            $portfolio,
            $validated['type'],
            $validated['limit'],
        );
    }

    /** @param array<string, mixed> $arguments */
    private function transactions(User $actor, Portfolio $portfolio, array $arguments): array
    {
        $validated = $this->validate($portfolio, $arguments, ['portfolio_id', 'asset_id', 'kind', 'limit'], [
            'portfolio_id' => ['required', 'integer'],
            'asset_id' => [
                'present',
                'nullable',
                'integer',
                Rule::exists('assets', 'id')->where('portfolio_id', $portfolio->getKey()),
            ],
            'kind' => ['present', 'nullable', Rule::enum(TransactionKind::class)],
            'limit' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        return $this->reads->transactions(
            $actor,
            $portfolio,
            $validated['asset_id'],
            $validated['kind'],
            $validated['limit'],
        );
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $expectedFields
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validate(
        Portfolio $portfolio,
        array $arguments,
        array $expectedFields,
        array $rules,
    ): array {
        $unexpectedFields = array_values(array_diff(array_keys($arguments), $expectedFields));
        $validator = Validator::make($arguments, $rules);
        $validator->fails();

        foreach ($unexpectedFields as $field) {
            $validator->errors()->add($field, "The {$field} field is not allowed.");
        }

        if (($arguments['portfolio_id'] ?? null) !== $portfolio->getKey()) {
            $validator->errors()->add('portfolio_id', 'The portfolio_id must match the active portfolio.');
        }

        if ($validator->errors()->isNotEmpty()) {
            throw new AssistantToolException(
                'invalid_tool_arguments',
                'The assistant supplied invalid tool arguments.',
                $validator->errors()->toArray(),
            );
        }

        return $validator->validated();
    }
}
