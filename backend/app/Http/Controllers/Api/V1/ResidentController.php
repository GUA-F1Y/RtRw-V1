<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Authorization\Enums\PrivacyTier;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Organization\Models\Rt;
use App\Domain\Resident\Actions\RecordResidentStatusAction;
use App\Domain\Resident\Actions\RegisterResidentAction;
use App\Domain\Resident\Actions\RelocateResidentAction;
use App\Domain\Resident\Actions\RemoveResidentFromHouseholdAction;
use App\Domain\Resident\Actions\UpdateResidentProfileAction;
use App\Domain\Resident\Actions\VerifyResidentAction;
use App\Domain\Resident\DTOs\RegisterResidentInput;
use App\Domain\Resident\DTOs\RelocateResidentInput;
use App\Domain\Resident\Enums\ResidencyMovementType;
use App\Domain\Resident\Enums\ResidencyType;
use App\Domain\Resident\Enums\ResidentBloodType;
use App\Domain\Resident\Enums\ResidentEducation;
use App\Domain\Resident\Enums\ResidentGender;
use App\Domain\Resident\Enums\ResidentMaritalStatus;
use App\Domain\Resident\Enums\ResidentStatus;
use App\Domain\Resident\Exceptions\ResidentScopeMismatch;
use App\Domain\Resident\Models\Resident;
use App\Domain\Resident\Queries\GetResidentQuery;
use App\Domain\Resident\Queries\ListResidentsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Resident\ChangeResidentStatusRequest;
use App\Http\Requests\Resident\GetResidentRequest;
use App\Http\Requests\Resident\ListResidentsRequest;
use App\Http\Requests\Resident\RegisterResidentRequest;
use App\Http\Requests\Resident\RelocateResidentRequest;
use App\Http\Requests\Resident\RemoveResidentFromHouseholdRequest;
use App\Http\Requests\Resident\UpdateResidentProfileRequest;
use App\Http\Requests\Resident\VerifyResidentRequest;
use App\Http\Resources\ResidencyMovementResource;
use App\Http\Resources\ResidentResource;
use App\Policies\ResidentPolicy;
use App\Support\Api\ApiResponse;
use App\Support\Api\Contracts\QueryContract;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class ResidentController extends Controller
{
    public function __construct(
        private readonly ListResidentsQuery $listQuery,
        private readonly GetResidentQuery $getQuery,
        private readonly ResidentPolicy $residentPolicy
    ) {}

    public function index(ListResidentsRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('viewAny', Resident::class);

        $sort = QueryContract::parseSort($request->query('sort'), QueryContract::RESIDENT);
        $includeTokens = QueryContract::parseIncludes($request->query('include'), QueryContract::RESIDENT) ?? [];
        $perPage = QueryContract::resolvePerPage($request->query('per_page'));

        $paginator = $this->listQuery->execute(
            $user,
            $request->validated(),
            $sort,
            $includeTokens,
            $perPage
        );

        return ApiResponse::paginated($paginator, function (Resident $resident) use ($user): ResidentResource {
            $tier = $this->residentPolicy->representationTier($user, $resident) ?? PrivacyTier::Directory;

            return ResidentResource::at($resident, $tier);
        });
    }

    public function show(Resident $resident, GetResidentRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('view', $resident);

        $tier = $this->residentPolicy->representationTier($user, $resident);
        if ($tier === null) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $includeTokens = QueryContract::parseIncludes($request->query('include'), QueryContract::RESIDENT) ?? [];
        $resident = $this->getQuery->execute($resident, $user, $includeTokens);

        return ApiResponse::success(ResidentResource::at($resident, $tier));
    }

    public function store(RegisterResidentRequest $request, RegisterResidentAction $action): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        /** @var Rt|null $rt */
        $rt = Rt::with('rw')->find($validated['rt_id']);
        if ($rt === null || $rt->rw === null) {
            throw new ResidentScopeMismatch("RT '{$validated['rt_id']}' does not exist or has invalid hierarchy.");
        }

        $targetScope = ScopeRef::rt($rt->rw->organization_id, $rt->rw_id, $rt->id);
        $this->authorize('register', [Resident::class, $targetScope]);

        $input = new RegisterResidentInput(
            nik: $validated['nik'],
            name: $validated['name'],
            gender: ResidentGender::from($validated['gender']),
            birthPlace: $validated['birth_place'],
            birthDate: $validated['birth_date'],
            religion: $validated['religion'],
            maritalStatus: ResidentMaritalStatus::from($validated['marital_status']),
            organizationId: $rt->rw->organization_id,
            rwId: $rt->rw_id,
            rtId: $rt->id,
            bloodType: isset($validated['blood_type'])
                ? ResidentBloodType::from($validated['blood_type'])
                : ResidentBloodType::Unknown,
            occupation: $validated['occupation'] ?? null,
            education: isset($validated['education'])
                ? ResidentEducation::from($validated['education'])
                : ResidentEducation::None,
            phone: $validated['phone'] ?? null,
            residencyType: isset($validated['residency_type'])
                ? ResidencyType::from($validated['residency_type'])
                : ResidencyType::Permanent,
            status: ResidentStatus::Prospective,
            userId: null,
        );

        $resident = $action->execute($input, $user);
        $tier = $this->residentPolicy->representationTier($user, $resident) ?? PrivacyTier::Normal;

        return ApiResponse::success(ResidentResource::at($resident, $tier), status: 201);
    }

    public function update(
        Resident $resident,
        UpdateResidentProfileRequest $request,
        UpdateResidentProfileAction $action
    ): JsonResponse {
        $user = $request->user();
        $this->authorize('update', $resident);

        $updatedResident = $action->execute($resident, $request->validated(), $user);
        $tier = $this->residentPolicy->representationTier($user, $updatedResident) ?? PrivacyTier::Normal;

        return ApiResponse::success(ResidentResource::at($updatedResident, $tier));
    }

    public function verify(
        Resident $resident,
        VerifyResidentRequest $request,
        VerifyResidentAction $action
    ): JsonResponse {
        $user = $request->user();
        $this->authorize('verify', $resident);

        $verifiedResident = $action->execute(
            residentId: $resident->id,
            notes: $request->validated('notes'),
            referenceLetterNumber: $request->validated('reference_letter_number'),
            attachmentFileId: $request->validated('attachment_file_id'),
            actor: $user
        );

        $tier = $this->residentPolicy->representationTier($user, $verifiedResident) ?? PrivacyTier::Normal;

        return ApiResponse::success(ResidentResource::at($verifiedResident, $tier));
    }

    public function relocate(
        Resident $resident,
        RelocateResidentRequest $request,
        RelocateResidentAction $action
    ): JsonResponse {
        $user = $request->user();
        $targetRtId = $request->validated('target_rt_id');
        $targetScope = null;

        if ($targetRtId !== null) {
            /** @var Rt|null $targetRt */
            $targetRt = Rt::with('rw')->find($targetRtId);
            if ($targetRt === null || $targetRt->rw === null) {
                throw new ResidentScopeMismatch("Target RT '{$targetRtId}' does not exist or has invalid hierarchy.");
            }
            $targetScope = ScopeRef::rt($targetRt->rw->organization_id, $targetRt->rw_id, $targetRt->id);
        }

        $this->authorize('relocate', [$resident, $targetScope]);

        $input = new RelocateResidentInput(
            residentId: $resident->id,
            movementType: ResidencyMovementType::from($request->validated('movement_type')),
            toRtId: $targetRtId,
            toHouseholdId: $request->validated('target_household_id'),
            effectiveDate: $request->validated('effective_date'),
            referenceLetterNumber: $request->validated('reference_letter_number'),
            attachmentFileId: $request->validated('attachment_file_id'),
            notes: $request->validated('notes'),
        );

        $movement = $action->execute($input, $user);
        $tier = $this->residentPolicy->representationTier($user, $resident) ?? PrivacyTier::Normal;

        return ApiResponse::success(ResidencyMovementResource::at($movement, $tier));
    }

    public function status(
        Resident $resident,
        ChangeResidentStatusRequest $request,
        RecordResidentStatusAction $action
    ): JsonResponse {
        $user = $request->user();
        $this->authorize('changeStatus', $resident);

        $newStatus = match ($request->validated('intent')) {
            'DEACTIVATE' => ResidentStatus::Inactive,
            'REACTIVATE' => ResidentStatus::Active,
            'MARK_DECEASED' => ResidentStatus::Deceased,
        };

        $effectiveAt = $request->validated('effective_at')
            ? Carbon::parse($request->validated('effective_at'))
            : null;

        $updatedResident = $action->execute(
            residentId: $resident->id,
            newStatus: $newStatus,
            reason: $request->validated('reason'),
            referenceLetterNumber: $request->validated('reference_letter_number'),
            attachmentFileId: $request->validated('attachment_file_id'),
            actor: $user,
            effectiveAt: $effectiveAt
        );

        $tier = $this->residentPolicy->representationTier($user, $updatedResident) ?? PrivacyTier::Normal;

        return ApiResponse::success(ResidentResource::at($updatedResident, $tier));
    }

    public function removeFromHousehold(
        Resident $resident,
        RemoveResidentFromHouseholdRequest $request,
        RemoveResidentFromHouseholdAction $action
    ): JsonResponse {
        $user = $request->user();
        $this->authorize('removeFromHousehold', $resident);

        $action->execute(
            residentId: $resident->id,
            leftReason: $request->validated('left_reason'),
            leftAt: $request->validated('left_at'),
            actor: $user
        );

        $tier = $this->residentPolicy->representationTier($user, $resident) ?? PrivacyTier::Normal;

        return ApiResponse::success(ResidentResource::at($resident->fresh(), $tier));
    }
}
