<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\ScopedQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(private readonly ScopedQuery $scopedQuery) {}

    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()
            ->with(['causer'])
            ->latest('id');

        $query = $this->scopedQuery->constrain(
            $query,
            $request->user(),
            PermissionKey::AuditView,
            [
                'organization' => 'organization_id',
                'rw' => 'rw_id',
                'rt' => 'rt_id',
            ]
        );

        $paginator = $query->paginate(perPage: (int) $request->query('per_page', 20));

        return ApiResponse::paginated($paginator, fn (AuditLog $log) => new AuditLogResource($log));
    }
}
