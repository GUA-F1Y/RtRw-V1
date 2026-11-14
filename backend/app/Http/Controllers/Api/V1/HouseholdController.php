<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Authorization\Enums\PrivacyTier;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Household\Actions\AddResidentToHouseholdAction;
use App\Domain\Household\Actions\ChangeHouseholdHeadAction;
use App\Domain\Household\Actions\CreateHouseholdAction;
use App\Domain\Household\Actions\DissolveHouseholdAction;
use App\Domain\Household\Actions\MoveHouseholdAction;
use App\Domain\Household\Actions\UpdateHouseholdAction;
use App\Domain\Household\DTOs\CreateHouseholdInput;
use App\Domain\Household\Models\Household;
use App\Domain\Household\Queries\GetHouseholdQuery;
use App\Domain\Household\Queries\ListHouseholdsQuery;
use App\Domain\Organization\Models\Rt;
use App\Domain\Resident\Enums\HouseholdMemberRelationship;
use App\Domain\Resident\Actions\RemoveResidentFromHouseholdAction;
use App\Domain\Resident\Exceptions\ResidentScopeMismatch;
use App\Domain\Resident\Models\Resident;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\AddHouseholdMemberRequest;
use App\Http\Requests\Household\ChangeHouseholdHeadRequest;
use App\Http\Requests\Household\CreateHouseholdRequest;
use App\Http\Requests\Household\DissolveHouseholdRequest;
use App\Http\Requests\Household\GetHouseholdRequest;
use App\Http\Requests\Household\ListHouseholdsRequest;
use App\Http\Requests\Household\MoveHouseholdRequest;
use App\Http\Requests\Household\RemoveHouseholdMemberRequest;
use App\Http\Requests\Household\UpdateHouseholdRequest;
use App\Http\Resources\HouseholdResource;
use App\Policies\HouseholdPolicy;
use App\Support\Api\ApiResponse;
use App\Support\Api\Contracts\QueryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class HouseholdController extends Controller
{
    public function __construct(
        private readonly ListHouseholdsQuery $listQuery,
        private readonly GetHouseholdQuery $getQuery,
        private readonly HouseholdPolicy $householdPolicy
    ) {}

    public function index(ListHouseholdsRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('viewAny', Household::class);

        $sort = QueryContract::parseSort($request->query('sort'), QueryContract::HOUSEHOLD);
        $includeTokens = QueryContract::parseIncludes($request->query('include'), QueryContract::HOUSEHOLD) ?? [];
        $perPage = QueryContract::resolvePerPage($request->query('per_page'));

        $paginator = $this->listQuery->execute(
            $user,
            $request->validated(),
            $sort,
            $includeTokens,
            $perPage
        );

        return ApiResponse::paginated($paginator, function (Household $household) use ($user): HouseholdResource {
            $tier = $this->householdPolicy->representationTier($user, $household) ?? PrivacyTier::Normal;

            return HouseholdResource::at($household, $tier);
        });
    }

    public function show(Household $household, GetHouseholdRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('view', $household);

        $tier = $this->householdPolicy->representationTier($user, $household);
        if ($tier === null) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $includeTokens = QueryContract::parseIncludes($request->query('include'), QueryContract::HOUSEHOLD) ?? [];
        $household = $this->getQuery->execute($household, $user, $includeTokens);

        return ApiResponse::success(HouseholdResource::at($household, $tier));
    }

    public function store(CreateHouseholdRequest $request, CreateHouseholdAction $action): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        /** @var Rt|null $rt */
        $rt = Rt::with('rw')->find($validated['rt_id']);
        if ($rt === null || $rt->rw === null) {
            throw new ResidentScopeMismatch("RT '{$validated['rt_id']}' does not exist or has invalid hierarchy.");
        }

        $targetScope = ScopeRef::rt($rt->rw->organization_id, $rt->rw_id, $rt->id);
        $this->authorize('create', [Household::class, $targetScope]);

        $input = new CreateHouseholdInput(
            kkNumber: $validated['kk_number'],
            address: $validated['address'],
            rtId: $rt->id,
            notes: $validated['notes'] ?? null,
            initialHeadResidentId: $validated['initial_head_resident_id'] ?? null,
        );

        $household = $action->execute($input, $user);
        $tier = $this->householdPolicy->representationTier($user, $household) ?? PrivacyTier::Normal;

        return ApiResponse::success(HouseholdResource::at($household, $tier), status: 201);
    }

    public function update(
        Household $household,
        UpdateHouseholdRequest $request,
        UpdateHouseholdAction $action
    ): JsonResponse {
        $user = $request->user();
        $this->authorize('update', $household);

        $updatedHousehold = $action->execute($household, $request->validated(), $user);
        $tier = $this->householdPolicy->representationTier($user, $updatedHousehold) ?? PrivacyTier::Normal;

        return ApiResponse::success(HouseholdResource::at($updatedHousehold, $tier));
    }

    public function addMember(
        Household $household,
        AddHouseholdMemberRequest $request,
        AddResidentToHouseholdAction $action
    ): JsonResponse {
        $user = $request->user();
        /** @var Resident $resident */
        $resident = Resident::findOrFail($request->validated('resident_id'));

        $this->authorize('addMember', [$household, $resident]);

        $action->execute(
            householdId: $household->id,
            residentId: $resident->id,
            relationship: HouseholdMemberRelationship::from($request->validated('relationship')),
            joinedAt: $request->validated('joined_at'),
            actor: $user
        );

        $tier = $this->householdPolicy->representationTier($user, $household) ?? PrivacyTier::Normal;

        return ApiResponse::success(HouseholdResource::at($household->fresh(), $tier));
    }

    public function removeMember(
        Household $household,
        Resident $resident,
        RemoveHouseholdMemberRequest $request,
        RemoveResidentFromHouseholdAction $action
    ): JsonResponse {
        $user = $request->user();

        if ($resident->current_household_id !== $household->id) {
            throw new ResidentScopeMismatch('Resident does not belong to the specified household.');
        }

        $this->authorize('removeMember', [$household, $resident]);

        $action->execute(
            residentId: $resident->id,
            leftReason: $request->validated('left_reason'),
            leftAt: $request->validated('left_at'),
            actor: $user
        );

        $tier = $this->householdPolicy->representationTier($user, $household) ?? PrivacyTier::Normal;

        return ApiResponse::success(HouseholdResource::at($household->fresh(), $tier));
    }

    public function changeHead(
        Household $household,
        ChangeHouseholdHeadRequest $request,
        ChangeHouseholdHeadAction $action
    ): JsonResponse {
        $user = $request->user();
        $this->authorize('changeHead', $household);

        $oldRelationship = $request->validated('old_head_new_relationship')
            ? HouseholdMemberRelationship::from($request->validated('old_head_new_relationship'))
            : null;

        $action->execute(
            householdId: $household->id,
            newHeadResidentId: $request->validated('new_head_resident_id'),
            oldHeadNewRelationship: $oldRelationship,
            actor: $user
        );

        $tier = $this->householdPolicy->representationTier($user, $household) ?? PrivacyTier::Normal;

        return ApiResponse::success(HouseholdResource::at($household->fresh(), $tier));
    }

    public function move(
        Household $household,
        MoveHouseholdRequest $request,
        MoveHouseholdAction $action
    ): JsonResponse {
        $user = $request->user();
        $targetRtId = $request->validated('target_rt_id');

        /** @var Rt|null $targetRt */
        $targetRt = Rt::with('rw')->find($targetRtId);
        if ($targetRt === null || $targetRt->rw === null) {
            throw new ResidentScopeMismatch("Target RT '{$targetRtId}' does not exist or has invalid hierarchy.");
        }

        $targetScope = ScopeRef::rt($targetRt->rw->organization_id, $targetRt->rw_id, $targetRt->id);
        $this->authorize('move', [$household, $targetScope]);

        $movedHousehold = $action->execute(
            householdId: $household->id,
            newAddress: $request->validated('new_address'),
            newRtId: $targetRt->id,
            notes: $request->validated('notes'),
            isExternalMove: false,
            actor: $user
        );

        $tier = $this->householdPolicy->representationTier($user, $movedHousehold) ?? PrivacyTier::Normal;

        return ApiResponse::success(HouseholdResource::at($movedHousehold, $tier));
    }

    public function dissolve(
        Household $household,
        DissolveHouseholdRequest $request,
        DissolveHouseholdAction $action
    ): JsonResponse {
        $user = $request->user();
        $this->authorize('dissolve', $household);

        $dissolvedHousehold = $action->execute(
            householdId: $household->id,
            reason: $request->validated('reason'),
            actor: $user
        );

        $tier = $this->householdPolicy->representationTier($user, $dissolvedHousehold) ?? PrivacyTier::Normal;

        return ApiResponse::success(HouseholdResource::at($dissolvedHousehold, $tier));
    }
}
