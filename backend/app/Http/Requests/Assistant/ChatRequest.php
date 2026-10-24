<?php

namespace App\Http\Requests\Assistant;

use App\Http\Requests\ApiFormRequest;
use App\Models\Portfolio;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ChatRequest extends ApiFormRequest
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
            'messages' => ['required', 'array', 'min:1', 'max:12'],
            'messages.*' => ['required', 'array:role,content'],
            'messages.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'messages.*.content' => ['required', 'string', 'min:1', 'max:4000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('messages')) {
                return;
            }

            $messages = $this->input('messages', []);
            $totalLength = collect($messages)->sum(
                fn (mixed $message): int => is_array($message) && is_string($message['content'] ?? null)
                    ? mb_strlen($message['content'])
                    : 0,
            );

            if ($totalLength > 12000) {
                $validator->errors()->add('messages', 'The conversation may not exceed 12000 characters.');
            }

            $lastMessage = is_array($messages) ? end($messages) : false;

            if (is_array($lastMessage) && ($lastMessage['role'] ?? null) !== 'user') {
                $validator->errors()->add('messages', 'The final message must be from the user.');
            }
        }];
    }

    /** @return list<string> */
    protected function allowedFields(): array
    {
        return ['messages'];
    }
}
