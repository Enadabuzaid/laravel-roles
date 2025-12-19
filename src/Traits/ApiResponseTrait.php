<?php

namespace Enadstack\LaravelRoles\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponseTrait
{
    /**
     * Send a success response
     */
    protected function successResponse(mixed $data = null, ?string $message = null, int $statusCode = 200): JsonResponse
    {
        $response = [
            'success' => true,
        ];

        if ($message) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Send an error response
     */
    protected function errorResponse(string $message, int $statusCode = 400, array $errors = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Send a paginated response
     */
    protected function paginatedResponse($resource, ?string $message = null): JsonResponse
    {
        if ($resource instanceof ResourceCollection) {
            $resourceData = $resource->response()->getData(true);

            $response = [
                'success' => true,
            ];

            if ($message) {
                $response['message'] = $message;
            }

            $response['data'] = $resourceData['data'] ?? [];

            // Add pagination meta
            if (isset($resourceData['meta'])) {
                $response['meta'] = $resourceData['meta'];
            }

            if (isset($resourceData['links'])) {
                $response['links'] = $resourceData['links'];
            }

            return response()->json($response);
        }

        return $this->successResponse($resource, $message);
    }

    /**
     * Send a resource response
     */
    protected function resourceResponse(JsonResource $resource, ?string $message = null, int $statusCode = 200): JsonResponse
    {
        $response = [
            'success' => true,
        ];

        if ($message) {
            $response['message'] = $message;
        }

        $response['data'] = $resource;

        return response()->json($response, $statusCode);
    }

    /**
     * Send a created response
     */
    protected function createdResponse(JsonResource $resource, string $message = 'Resource created successfully'): JsonResponse
    {
        return $this->resourceResponse($resource, $message, 201);
    }

    /**
     * Send a deleted response
     */
    protected function deletedResponse(string $message = 'Resource deleted successfully'): JsonResponse
    {
        return $this->successResponse(null, $message, 200);
    }

    /**
     * Send a not found response
     */
    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }
}

