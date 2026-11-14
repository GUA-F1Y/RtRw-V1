<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\ScopedQuery;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function __construct(private readonly ScopedQuery $scopedQuery) {}

    public function index(Request $request): JsonResponse
    {
        $query = Organization::query()->withCount('rws');

        $query = $this->scopedQuery->constrain(
            $query,
            $request->user(),
            PermissionKey::RwView,
            [
                'organization' => 'id',
                'rw' => fn ($q, $rwId) => $q->whereHas('rws', fn ($sub) => $sub->where('id', $rwId)),
                'rt' => fn ($q, $rtId) => $q->whereHas('rws.rts', fn ($sub) => $sub->where('id', $rtId)),
            ]
        );

        $paginator = $query->paginate(perPage: (int) $request->query('per_page', 15));

        return ApiResponse::paginated($paginator, fn (Organization $org) => new OrganizationResource($org));
    }

    public function show(Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        $organization->loadCount('rws');

        return ApiResponse::success(new OrganizationResource($organization));
    }
}
