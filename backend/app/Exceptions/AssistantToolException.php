<?php

namespace App\Exceptions;

use RuntimeException;

class AssistantToolException extends RuntimeException
{
    /** @param array<string, list<string>> $fields */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $fields = [],
    ) {
        parent::__construct($message);
    }
}
