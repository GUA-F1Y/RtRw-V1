<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\ScopedQuery;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rw;
use App\Http\Controllers\Controller;
use App\Http\Resources\RwResource;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RwController extends Controller
{
    public function __construct(private readonly ScopedQuery $scopedQuery) {}

    public function index(Organization $organization, Request $request): JsonResponse
    {
        $this->authorize('view', $organization);

        $query = Rw::query()
            ->where('organization_id', $organization->id)
            ->withCount('rts')
            ->orderBy('number');

        $query = $this->scopedQuery->constrain(
            $query,
            $request->user(),
            PermissionKey::RwView,
            [
                'organization' => 'organization_id',
                'rw' => 'id',
                'rt' => fn ($q, $rtId) => $q->whereHas('rts', fn ($sub) => $sub->where('id', $rtId)),
            ]
        );

        $paginator = $query->paginate(perPage: (int) $request->query('per_page', 15));

        return ApiResponse::paginated($paginator, fn (Rw $rw) => new RwResource($rw));
    }

    public function show(Rw $rw): JsonResponse
    {
        $this->authorize('view', $rw);

        $rw->load(['rts' => fn ($q) => $q->orderBy('number')])->loadCount('rts');

        return ApiResponse::success(new RwResource($rw));
    }
}
