<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Support\Exceptions;

use RuntimeException;

class AccessException extends RuntimeException
{
    /** @param array<string, mixed> $errors */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 403,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}
