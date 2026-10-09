<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Support\Exceptions;

use Enadstack\LaravelRoles\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    /**
     * Guardrail violations are expected outcomes, not server errors: returning true marks the
     * exception as already reported, so Laravel's handler skips logging it.
     */
    public function report(): bool
    {
        return true;
    }

    public function render(Request $request): Response|JsonResponse
    {
        if ($request->expectsJson()) {
            return ApiResponse::error($this->getMessage(), $this->errorCode, $this->status, $this->errors);
        }

        return response($this->getMessage(), $this->status);
    }
}
