<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Support\Http;

use Enadstack\LaravelRoles\Support\ApiResponse;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ApiExceptionRenderer
{
    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        $prefix = trim((string) config('roles.routes.prefix'), '/');

        if ($prefix === '' || ! ($request->is($prefix) || $request->is($prefix.'/*'))) {
            return null;
        }

        return match (true) {
            $e instanceof AccessException => ApiResponse::error($e->getMessage(), $e->errorCode, $e->status, $e->errors),
            $e instanceof ValidationException => ApiResponse::error($e->getMessage(), 'validation_failed', 422, $e->errors()),
            $e instanceof AuthenticationException => ApiResponse::error($e->getMessage(), 'unauthenticated', 401),
            $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => ApiResponse::error($e->getMessage() ?: 'This action is unauthorized.', 'forbidden', 403),
            $e instanceof TokenMismatchException => ApiResponse::error('Your session expired. Refresh the page and try again.', 'session_expired', 419),
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => ApiResponse::error('Resource not found.', 'not_found', 404),
            $e instanceof HttpExceptionInterface => ApiResponse::error(
                $e->getMessage() !== '' ? $e->getMessage() : 'HTTP error.',
                match ($e->getStatusCode()) {
                    405 => 'method_not_allowed',
                    409 => 'conflict',
                    419 => 'session_expired',
                    429 => 'too_many_requests',
                    default => 'http_error',
                },
                $e->getStatusCode(),
            ),
            default => null,
        };
    }
}
