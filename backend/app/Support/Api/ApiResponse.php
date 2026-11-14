<?php

declare(strict_types=1);

namespace App\Support\Api;

use App\Support\Http\RequestContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    /**
     * @param  mixed  $data
     * @param  array<string, mixed>  $meta
     * @param  array<string, string>  $headers
     */
    public static function success(
        mixed $data = null,
        array $meta = [],
        int $status = 200,
        array $headers = []
    ): JsonResponse {
        $requestId = app(RequestContext::class)->requestId();
        if ($requestId !== null) {
            $meta['request_id'] = $requestId;
        }

        $payload = [
            'data' => $data,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return new JsonResponse($payload, $status, $headers);
    }

    /**
     * Standard envelope for paginated collections.
     *
     * @param  LengthAwarePaginator  $paginator
     * @param  callable|null  $transform
     */
    public static function paginated(LengthAwarePaginator $paginator, ?callable $transform = null): JsonResponse
    {
        $items = $transform !== null
            ? $paginator->getCollection()->map($transform)->values()
            : $paginator->items();

        return self::success($items, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    public static function noContent(): JsonResponse
    {
        return new JsonResponse(null, 204);
    }
}
