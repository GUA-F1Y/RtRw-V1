<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Authorization\Enums\ScopeLevel;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\Organization\Models\Membership;
use App\Domain\Overview\DTOs\OverviewScopeDto;
use App\Domain\Overview\Queries\GetOverviewQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Overview\GetOverviewRequest;
use App\Http\Resources\OverviewResource;
use App\Support\Api\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OverviewController extends Controller
{
    public function __construct(
        private readonly GetOverviewQuery $overviewQuery,
        private readonly AccessResolver $accessResolver
    ) {}

    public function show(GetOverviewRequest $request): JsonResponse
    {
        $user = $request->user();

        // 1. Resolve active memberships from request-scoped AccessResolver
        $memberships = $this->accessResolver->memberships($user);

        if ($memberships->isEmpty()) {
            throw new AuthorizationException('Actor has no active organizational membership.');
        }

        // 2. Derive primary active scope by authority priority (Organization > RW > RT)
        $priority = [
            ScopeLevel::Organization->value => 3,
            ScopeLevel::Rw->value => 2,
            ScopeLevel::Rt->value => 1,
        ];

        /** @var Membership $primary */
        $primary = $memberships->sortByDesc(fn (Membership $m) => $priority[$m->scope_level->value] ?? 0)->first();

        $scopeRef = $primary->scopeRef();

        // 3. Authorize actor against resolved scope
        Gate::authorize('view-overview', $scopeRef);

        // 4. Resolve scope presentation DTO
        $scopeName = match ($primary->scope_level) {
            ScopeLevel::Organization => $primary->organization?->name ?? 'Organization',
            ScopeLevel::Rw => $primary->rw?->name ?? 'RW',
            ScopeLevel::Rt => $primary->rt?->name ?? 'RT',
        };

        $scopeId = match ($primary->scope_level) {
            ScopeLevel::Organization => (string) $primary->organization_id,
            ScopeLevel::Rw => (string) $primary->rw_id,
            ScopeLevel::Rt => (string) $primary->rt_id,
        };

        $scopeDto = new OverviewScopeDto(
            level: strtoupper($primary->scope_level->value),
            id: $scopeId,
            name: $scopeName,
        );

        // 5. Execute aggregate query
        $snapshot = $this->overviewQuery->execute($scopeRef, $scopeDto);

        // 6. Return standard serialized response
        return ApiResponse::success(new OverviewResource($snapshot));
    }
}
