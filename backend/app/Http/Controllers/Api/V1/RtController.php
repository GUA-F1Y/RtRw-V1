<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\ScopedQuery;
use App\Domain\Organization\Enums\UnitStatus;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CreateRtRequest;
use App\Http\Resources\RtResource;
use App\Support\Api\ApiResponse;
use App\Support\Api\Exceptions\ConflictException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RtController extends Controller
{
    public function __construct(private readonly ScopedQuery $scopedQuery) {}

    public function index(Rw $rw, Request $request): JsonResponse
    {
        $this->authorize('view', $rw);

        $query = Rt::query()
            ->where('rw_id', $rw->id)
            ->orderBy('number');

        $query = $this->scopedQuery->constrain(
            $query,
            $request->user(),
            PermissionKey::RtView,
            [
                'organization' => fn ($q, $orgId) => $q->whereHas('rw', fn ($sub) => $sub->where('organization_id', $orgId)),
                'rw' => 'rw_id',
                'rt' => 'id',
            ]
        );

        $paginator = $query->paginate(perPage: (int) $request->query('per_page', 15));

        return ApiResponse::paginated($paginator, fn (Rt $rt) => new RtResource($rt));
    }

    public function show(Rt $rt): JsonResponse
    {
        $this->authorize('view', $rt);

        return ApiResponse::success(new RtResource($rt));
    }

    public function store(Rw $rw, CreateRtRequest $request, AuditLogger $audit): JsonResponse
    {
        $this->authorize('createRt', $rw);

        $number = (int) $request->validated('number');
        if (Rt::query()->where('rw_id', $rw->id)->where('number', $number)->exists()) {
            throw new ConflictException("RT {$number} already exists in RW {$rw->number}.");
        }

        /** @var Rt $rt */
        $rt = DB::transaction(function () use ($rw, $request, $number, $audit): Rt {
            $rt = Rt::query()->create([
                'rw_id' => $rw->id,
                'number' => $number,
                'name' => $request->validated('name'),
                'status' => UnitStatus::Active,
            ]);

            $audit->record(
                actor: $request->user(),
                event: 'rt.created',
                description: "Created RT {$rt->number} ({$rt->name}) under RW {$rw->number}.",
                subject: $rt,
                scope: $rt->scopeRef()
            );

            return $rt;
        });

        return ApiResponse::success(new RtResource($rt), status: 201);
    }
}
