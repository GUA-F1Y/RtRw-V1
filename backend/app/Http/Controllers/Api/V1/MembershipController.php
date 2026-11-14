<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Enums\ScopeLevel;
use App\Domain\Authorization\Models\Role;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\Authorization\Services\ScopedQuery;
use App\Domain\Authorization\Services\ScopeResolver;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Membership;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\AssignMembershipRequest;
use App\Http\Resources\MembershipResource;
use App\Support\Api\ApiResponse;
use App\Support\Api\Exceptions\ApiException;
use App\Support\Api\Exceptions\BusinessRuleViolation;
use App\Support\Api\Exceptions\ConflictException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MembershipController extends Controller
{
    public function __construct(
        private readonly ScopedQuery $scopedQuery,
        private readonly ScopeResolver $scopeResolver,
        private readonly AccessResolver $access
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Membership::query()
            ->active()
            ->with(['user', 'role', 'organization', 'rw', 'rt']);

        $query = $this->scopedQuery->constrain(
            $query,
            $request->user(),
            PermissionKey::MembershipView,
            [
                'organization' => 'organization_id',
                'rw' => 'rw_id',
                'rt' => 'rt_id',
            ]
        );

        $paginator = $query->paginate(perPage: (int) $request->query('per_page', 15));

        return ApiResponse::paginated($paginator, fn (Membership $m) => new MembershipResource($m));
    }

    public function store(AssignMembershipRequest $request, AuditLogger $audit): JsonResponse
    {
        $scopeLevel = ScopeLevel::from($request->validated('scope_type'));
        $scopeId = (string) $request->validated('scope_id');

        $targetScope = $this->scopeResolver->resolve($scopeLevel, $scopeId);
        if ($targetScope === null) {
            throw new ApiException('The target organizational scope was not found.', 404, 'scope_not_found');
        }

        // Server-side authorization check: caller must have MembershipManage at target scope
        if (! $this->access->allows($request->user(), PermissionKey::MembershipManage, $targetScope)) {
            throw new AuthorizationException('You are not authorized to assign roles in this scope.');
        }

        /** @var Role $role */
        $role = Role::query()->findOrFail($request->validated('role_id'));
        if ($role->scope_level !== $scopeLevel) {
            throw new BusinessRuleViolation(
                "Role '{$role->name}' cannot be assigned at {$scopeLevel->value} scope."
            );
        }

        $userId = (string) $request->validated('user_id');

        // Check if an active membership already exists
        $existing = Membership::query()
            ->active()
            ->where('user_id', $userId)
            ->where('role_id', $role->id)
            ->where('scope_level', $scopeLevel->value)
            ->where('organization_id', $targetScope->organizationId)
            ->where('rw_id', $targetScope->rwId)
            ->where('rt_id', $targetScope->rtId)
            ->exists();

        if ($existing) {
            throw new ConflictException('User already holds this role in this scope.');
        }

        /** @var Membership $membership */
        $membership = DB::transaction(function () use ($userId, $role, $scopeLevel, $targetScope, $request, $audit): Membership {
            $membership = Membership::query()->create([
                'user_id' => $userId,
                'role_id' => $role->id,
                'scope_level' => $scopeLevel,
                'organization_id' => $targetScope->organizationId,
                'rw_id' => $targetScope->rwId,
                'rt_id' => $targetScope->rtId,
                'granted_by' => $request->user()->id,
            ]);

            $audit->record(
                actor: $request->user(),
                event: 'membership.granted',
                description: "Assigned role '{$role->name}' to user {$userId}.",
                subject: $membership,
                scope: $targetScope,
                properties: ['role' => $role->key, 'scope_level' => $scopeLevel->value]
            );

            return $membership;
        });

        // Invalidate access cache
        /** @var User $targetUser */
        $targetUser = User::query()->find($userId);
        if ($targetUser) {
            $this->access->forget($targetUser);
        }

        $membership->load(['role', 'organization', 'rw', 'rt']);

        return ApiResponse::success(new MembershipResource($membership), status: 201);
    }

    public function destroy(Membership $membership, AuditLogger $audit, Request $request): JsonResponse
    {
        $this->authorize('manage', $membership);

        if (! $membership->isActive()) {
            throw new ConflictException('This membership is already revoked.');
        }

        DB::transaction(function () use ($membership, $request, $audit): void {
            $membership->update(['revoked_at' => now()]);

            $audit->record(
                actor: $request->user(),
                event: 'membership.revoked',
                description: "Revoked membership {$membership->id} (role {$membership->role?->name}).",
                subject: $membership,
                scope: $membership->scopeRef()
            );
        });

        if ($membership->user) {
            $this->access->forget($membership->user);
        }

        return ApiResponse::success(['message' => 'Membership revoked successfully.']);
    }
}
