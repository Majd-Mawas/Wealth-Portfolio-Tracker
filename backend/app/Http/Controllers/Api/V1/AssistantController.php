<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assistant\ChatRequest;
use App\Models\Portfolio;
use App\Models\User;
use App\Services\AssistantService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\StreamedEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AssistantController extends Controller
{
    public function __construct(private readonly AssistantService $assistant) {}

    public function __invoke(ChatRequest $request, Portfolio $portfolio): StreamedResponse
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }

        /** @var list<array{role: string, content: string}> $messages */
        $messages = $request->validated('messages');
        $requestId = (string) $request->attributes->get('request_id');

        return response()->eventStream(function () use ($actor, $portfolio, $messages, $requestId): \Generator {
            yield $this->streamedEvent('meta', ['request_id' => $requestId]);

            $finishReason = 'stop';

            try {
                foreach ($this->assistant->stream($actor, $portfolio, $messages) as $event) {
                    if ($event['event'] === 'error') {
                        $finishReason = 'error';
                    }

                    yield $this->streamedEvent($event['event'], $event['data']);
                }
            } catch (Throwable $exception) {
                report($exception);
                $finishReason = 'error';

                yield $this->streamedEvent('error', [
                    'code' => 'assistant_upstream_unavailable',
                    'message' => 'The assistant is temporarily unavailable.',
                    'request_id' => $requestId,
                    'retryable' => true,
                ]);
            }

            yield $this->streamedEvent('done', ['finish_reason' => $finishReason]);
        });
    }

    /** @param array<string, mixed> $data */
    private function streamedEvent(string $event, array $data): StreamedEvent
    {
        return new StreamedEvent(
            event: $event,
            data: json_encode($data, JSON_THROW_ON_ERROR),
        );
    }
}
