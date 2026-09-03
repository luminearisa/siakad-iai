<?php

namespace App\Support\Traits;

use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

trait HasApiResponse
{
    /**
     * Return a standardized success response.
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Success',
        int $code = 200,
        ?array $meta = null
    ): JsonResponse {
        return ApiResponse::success($data, $message, $code, $meta);
    }

    /**
     * Return a standardized error response.
     */
    protected function errorResponse(
        string $message = 'An error occurred.',
        int $code = 400,
        mixed $errors = null
    ): JsonResponse {
        return ApiResponse::error($message, $code, $errors);
    }

    /**
     * Return a standardized paginated response.
     */
    protected function paginatedResponse(
        LengthAwarePaginator $paginator,
        string $message = 'Data retrieved successfully.',
        ?string $resourceClass = null,
        int $code = 200
    ): JsonResponse {
        return ApiResponse::paginated($paginator, $message, $resourceClass, $code);
    }
}
