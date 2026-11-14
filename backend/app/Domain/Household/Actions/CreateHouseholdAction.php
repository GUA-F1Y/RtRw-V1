<?php

declare(strict_types=1);

namespace App\Domain\Household\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Household\DTOs\CreateHouseholdInput;
use App\Domain\Household\Enums\HouseholdStatus;
use App\Domain\Household\Exceptions\HouseholdStateConflict;
use App\Domain\Household\Models\Household;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Rt;
use App\Domain\Resident\Enums\HouseholdMemberRelationship;
use App\Domain\Resident\Enums\ResidentStatus;
use App\Domain\Resident\Exceptions\HouseholdMembershipConflict;
use App\Domain\Resident\Exceptions\ResidentScopeMismatch;
use App\Domain\Resident\Models\HouseholdMember;
use App\Domain\Resident\Models\Resident;
use App\Support\Api\Exceptions\ApiException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CreateHouseholdAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CreateHouseholdInput $input, ?User $actor = null): Household
    {
        // 1. Validate KK format
        if (! preg_match('/^[0-9]{16}$/', $input->kkNumber)) {
            throw new ApiException('KK number must be exactly 16 numeric digits.', 422, 'invalid_kk_format');
        }

        // 2. Resolve RT from database
        /** @var Rt|null $rt */
        $rt = Rt::with('rw')->find($input->rtId);
        if ($rt === null || $rt->rw === null) {
            throw new ResidentScopeMismatch("RT '{$input->rtId}' does not exist or has invalid hierarchy.");
        }

        $organizationId = $rt->rw->organization_id;
        $rwId = $rt->rw_id;

        // 3. Check active KK uniqueness
        $activeExists = Household::where('organization_id', $organizationId)
            ->where('kk_number', $input->kkNumber)
            ->where('status', HouseholdStatus::Active)
            ->exists();

        if ($activeExists) {
            throw new HouseholdStateConflict("An active household with KK '{$input->kkNumber}' already exists in this organization.");
        }

        try {
            return DB::transaction(function () use ($input, $rt, $organizationId, $rwId, $actor): Household {
                $status = $input->initialHeadResidentId !== null
                    ? HouseholdStatus::Active
                    : HouseholdStatus::HeadVacant;

                /** @var Household $household */
                $household = Household::create([
                    'organization_id' => $organizationId,
                    'rw_id' => $rwId,
                    'rt_id' => $rt->id,
                    'kk_number' => $input->kkNumber,
                    'address' => $input->address,
                    'status' => $status,
                    'notes' => $input->notes,
                ]);

                if ($input->initialHeadResidentId !== null) {
                    /** @var Resident|null $headResident */
                    $headResident = Resident::where('id', $input->initialHeadResidentId)
                        ->lockForUpdate()
                        ->first();

                    if ($headResident === null) {
                        throw new ApiException("Resident '{$input->initialHeadResidentId}' does not exist.", 422, 'resident_not_found');
                    }

                    if ($headResident->rt_id !== $rt->id) {
                        throw new ResidentScopeMismatch('Initial head resident must belong to the same RT as the household.');
                    }

                    if ($headResident->status !== ResidentStatus::Active) {
                        throw new HouseholdMembershipConflict("Resident with status '{$headResident->status->value}' cannot be appointed as household head. Must be active.");
                    }

                    $hasActiveMembership = HouseholdMember::where('resident_id', $headResident->id)
                        ->where('is_active', true)
                        ->exists();

                    if ($hasActiveMembership) {
                        throw new HouseholdMembershipConflict('Resident already belongs to an active household.');
                    }

                    HouseholdMember::create([
                        'household_id' => $household->id,
                        'resident_id' => $headResident->id,
                        'relationship' => HouseholdMemberRelationship::KepalaKeluarga,
                        'is_active' => true,
                        'joined_at' => Carbon::now()->toDateString(),
                    ]);

                    $headResident->current_household_id = $household->id;
                    $headResident->save();
                }

                $this->auditLogger->record(
                    actor: $actor,
                    event: 'household.created',
                    description: "Household created with KK: {$household->kk_number}",
                    subject: $household,
                    scope: ScopeRef::rt($household->organization_id, $household->rw_id, $household->rt_id),
                    properties: [
                        'household_id' => $household->id,
                        'kk_number' => $household->kk_number,
                        'status' => $household->status->value,
                        'initial_head_id' => $input->initialHeadResidentId,
                    ]
                );

                return $household;
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'households_active_kk_unique')) {
                throw new HouseholdStateConflict("An active household with KK '{$input->kkNumber}' already exists in this organization.");
            }
            throw $e;
        }
    }
}
