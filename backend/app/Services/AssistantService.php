<?php

namespace App\Services;

use App\Exceptions\AssistantToolException;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;
use UnexpectedValueException;

class AssistantService
{
    private const int MAX_TOOL_ROUNDS = 4;

    public function __construct(
        private readonly OpenAIResponsesClient $client,
        private readonly AssistantToolRegistry $tools,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return \Generator<int, array{event: string, data: array<string, mixed>}>
     */
    public function stream(User $actor, Portfolio $portfolio, array $messages): \Generator
    {
        $input = [[
            'role' => 'developer',
            'content' => "The active portfolio ID is {$portfolio->getKey()}. All tool calls must use this exact ID.",
        ], ...$messages];

        for ($round = 1; $round <= self::MAX_TOOL_ROUNDS; $round++) {
            $response = $this->client->create($input, $this->tools->definitions());
            $output = $response['output'];
            $functionCalls = collect($output)
                ->filter(fn (mixed $item): bool => is_array($item) && ($item['type'] ?? null) === 'function_call')
                ->values()
                ->all();

            if ($functionCalls === []) {
                $text = $this->outputText($output);

                if ($text === '') {
                    yield $this->event('error', [
                        'code' => 'assistant_empty_response',
                        'message' => 'The assistant returned no answer. Please try again.',
                        'retryable' => true,
                    ]);

                    return;
                }

                foreach (mb_str_split($text, 120) as $chunk) {
                    yield $this->event('text_delta', ['text' => $chunk]);
                }

                return;
            }

            foreach ($output as $item) {
                if (is_array($item)) {
                    $input[] = $item;
                }
            }

            foreach ($functionCalls as $functionCall) {
                $tool = is_string($functionCall['name'] ?? null) ? $functionCall['name'] : 'unknown';
                $callId = $functionCall['call_id'] ?? null;

                if (! is_string($callId) || $callId === '') {
                    throw new UnexpectedValueException('OpenAI returned a function call without a call_id.');
                }

                yield $this->event('tool_started', ['tool' => $tool]);

                try {
                    $arguments = $this->decodeArguments($functionCall['arguments'] ?? null);
                    $result = $this->tools->execute($actor, $portfolio, $tool, $arguments);
                    yield $this->event('tool_completed', ['tool' => $tool]);
                } catch (AssistantToolException $exception) {
                    $result = $this->toolError($tool, $exception->errorCode, $exception->getMessage(), $exception->fields);
                    yield $this->event('tool_error', $result['error'] + ['tool' => $tool]);
                } catch (AuthorizationException) {
                    $result = $this->toolError(
                        $tool,
                        'tool_forbidden',
                        'The requested portfolio data is not available.',
                    );
                    yield $this->event('tool_error', $result['error'] + ['tool' => $tool]);
                } catch (Throwable $exception) {
                    report($exception);
                    $result = $this->toolError(
                        $tool,
                        'tool_execution_failed',
                        'The portfolio tool could not complete the request.',
                    );
                    yield $this->event('tool_error', $result['error'] + ['tool' => $tool]);
                }

                $input[] = [
                    'type' => 'function_call_output',
                    'call_id' => $callId,
                    'output' => json_encode($result, JSON_THROW_ON_ERROR),
                ];
            }
        }

        Log::warning('Assistant tool round limit reached.', [
            'actor_id' => $actor->getKey(),
            'portfolio_id' => $portfolio->getKey(),
        ]);

        yield $this->event('error', [
            'code' => 'assistant_tool_limit',
            'message' => 'The assistant used too many tool steps. Please ask a narrower question.',
            'retryable' => false,
        ]);
    }

    /** @return array<string, mixed> */
    private function decodeArguments(mixed $arguments): array
    {
        if (! is_string($arguments)) {
            throw new AssistantToolException(
                'invalid_tool_arguments',
                'The assistant supplied invalid tool arguments.',
            );
        }

        try {
            $decoded = json_decode($arguments, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new AssistantToolException(
                'invalid_tool_arguments',
                'The assistant supplied invalid tool arguments.',
            );
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new AssistantToolException(
                'invalid_tool_arguments',
                'The assistant supplied invalid tool arguments.',
            );
        }

        return $decoded;
    }

    /** @param list<array<string, mixed>> $output */
    private function outputText(array $output): string
    {
        $text = '';

        foreach ($output as $item) {
            if (! is_array($item) || ($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ($item['content'] ?? [] as $content) {
                if (is_array($content) && ($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    $text .= $content['text'];
                }
            }
        }

        return trim($text);
    }

    /**
     * @param  array<string, list<string>>  $fields
     * @return array{ok: false, error: array<string, mixed>}
     */
    private function toolError(string $tool, string $code, string $message, array $fields = []): array
    {
        Log::notice('Assistant read tool rejected.', [
            'tool' => $tool,
            'code' => $code,
        ]);

        return [
            'ok' => false,
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'fields' => $fields === [] ? null : $fields,
            ], fn (mixed $value): bool => $value !== null),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{event: string, data: array<string, mixed>}
     */
    private function event(string $event, array $data): array
    {
        return ['event' => $event, 'data' => $data];
    }
}
