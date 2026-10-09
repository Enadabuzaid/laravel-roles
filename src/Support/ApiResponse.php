<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

final class ApiResponse
{
    /** @param array<string, mixed> $meta */
    public static function success(mixed $data = null, ?string $message = null, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => (object) $meta,
        ], $status);
    }

    /** @param array<string, mixed> $meta */
    public static function created(mixed $data = null, ?string $message = null, array $meta = []): JsonResponse
    {
        return self::success($data, $message, $meta, 201);
    }

    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  class-string<JsonResource>|null  $resource
     * @param  array<string, mixed>  $meta
     */
    public static function paginated(LengthAwarePaginator $paginator, ?string $resource = null, array $meta = [], ?string $message = null): JsonResponse
    {
        $items = $resource !== null
            ? $resource::collection($paginator->getCollection())->resolve()
            : $paginator->items();

        return self::success($items, $message, array_merge(['pagination' => [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]], $meta));
    }

    /** @param array<string, mixed> $errors */
    public static function error(string $message, string $code, int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => $code,
            'errors' => (object) $errors,
        ], $status);
    }
}
