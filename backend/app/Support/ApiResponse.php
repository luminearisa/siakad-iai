<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ApiResponse
{
    /**
     * Return a standardized success JSON response.
     */
    public static function success(
        mixed $data = null,
        string $message = 'Data retrieved successfully.',
        int $code = 200,
        ?array $meta = null
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $code);
    }

    /**
     * Return a standardized error JSON response.
     */
    public static function error(
        string $message = 'An error occurred.',
        int $code = 400,
        mixed $errors = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ];

        return response()->json($response, $code);
    }

    /**
     * Return a standardized paginated JSON response.
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        string $message = 'Data retrieved successfully.',
        ?string $resourceClass = null,
        int $code = 200
    ): JsonResponse {
        $items = $paginator->items();

        if ($resourceClass !== null && class_exists($resourceClass)) {
            $items = $resourceClass::collection($items)->resolve();
        }

        return self::success(
            data: $items,
            message: $message,
            code: $code,
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ]
        );
    }
}
