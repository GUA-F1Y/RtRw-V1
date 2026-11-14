<?php

declare(strict_types=1);

namespace App\Domain\Household\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Household\Enums\HouseholdStatus;
use App\Domain\Household\Exceptions\HouseholdStateConflict;
use App\Domain\Household\Models\Household;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Rt;
use App\Domain\Resident\Enums\ResidencyMovementType;
use App\Domain\Resident\Enums\ResidentStatus;
use App\Domain\Resident\Exceptions\ResidentScopeMismatch;
use App\Domain\Resident\Models\HouseholdMember;
use App\Domain\Resident\Models\ResidencyMovement;
use App\Domain\Resident\Models\Resident;
use App\Domain\Resident\Models\ResidentStatusHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class MoveHouseholdAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(
        string $householdId,
        string $newAddress,
        ?string $newRtId = null,
        ?string $notes = null,
        bool $isExternalMove = false,
        ?User $actor = null
    ): Household {
        return DB::transaction(function () use (
            $householdId,
            $newAddress,
            $newRtId,
            $notes,
            $isExternalMove,
            $actor
        ): Household {
            /** @var Household $household */
            $household = Household::where('id', $householdId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($household->status !== HouseholdStatus::Active && $household->status !== HouseholdStatus::HeadVacant) {
                throw new HouseholdStateConflict("Cannot move household with status '{$household->status->value}'.");
            }

            $effectiveDate = Carbon::now();
            $recordedBy = $actor?->id ?? $this->resolveFallbackActorId();

            // Scenario 1: External relocation out of organization
            if ($isExternalMove) {
                $fromOrgId = $household->organization_id;
                $fromRwId = $household->rw_id;
                $fromRtId = $household->rt_id;
                $fromHouseholdId = $household->id;

                // Deactivate members and update residents to MOVED
                $activeMembers = HouseholdMember::where('household_id', $household->id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->get();

                foreach ($activeMembers as $member) {
                    $member->is_active = false;
                    $member->left_at = $effectiveDate->toDateString();
                    $member->left_reason = 'MOVE_OUT';
                    $member->save();

                    /** @var Resident $res */
                    $res = Resident::where('id', $member->resident_id)->lockForUpdate()->firstOrFail();
                    $prevStatus = $res->status;
                    $res->status = ResidentStatus::Moved;
                    $res->current_household_id = null;
                    $res->save();

                    ResidentStatusHistory::create([
                        'resident_id' => $res->id,
                        'previous_status' => $prevStatus,
                        'new_status' => ResidentStatus::Moved,
                        'reason' => $notes ?? 'Household moved out of community',
                        'effective_at' => $effectiveDate,
                        'recorded_by' => $recordedBy,
                    ]);

                    ResidencyMovement::create([
                        'resident_id' => $res->id,
                        'movement_type' => ResidencyMovementType::MoveOutExternal,
                        'from_organization_id' => $fromOrgId,
                        'from_rw_id' => $fromRwId,
                        'from_rt_id' => $fromRtId,
                        'from_household_id' => $fromHouseholdId,
                        'to_organization_id' => null,
                        'to_rw_id' => null,
                        'to_rt_id' => null,
                        'to_household_id' => null,
                        'effective_date' => $effectiveDate->toDateString(),
                        'notes' => $notes,
                        'recorded_by' => $recordedBy,
                    ]);
                }

                $household->status = HouseholdStatus::Moved;
                $household->address = $newAddress;
                if ($notes !== null) {
                    $household->notes = $notes;
                }
                $household->save();

                $this->auditLogger->record(
                    actor: $actor,
                    event: 'household.moved_external',
                    description: "Household {$household->kk_number} moved externally",
                    subject: $household,
                    scope: ScopeRef::rt($household->organization_id, $household->rw_id, $household->rt_id),
                    properties: [
                        'household_id' => $household->id,
                        'status' => HouseholdStatus::Moved->value,
                    ]
                );

                return $household;
            }

            // Scenario 2: Transfer to a different RT within the same organization
            if ($newRtId !== null && $newRtId !== $household->rt_id) {
                /** @var Rt|null $targetRt */
                $targetRt = Rt::with('rw')->find($newRtId);
                if ($targetRt === null || $targetRt->rw === null) {
                    throw new ResidentScopeMismatch("Target RT '{$newRtId}' does not exist or is invalid.");
                }

                if ($targetRt->rw->organization_id !== $household->organization_id) {
                    throw new ResidentScopeMismatch('Target RT does not belong to the same organization.');
                }

                $fromOrgId = $household->organization_id;
                $fromRwId = $household->rw_id;
                $fromRtId = $household->rt_id;

                // Temporarily detach resident pointers to satisfy composite FK during RT update
                $linkedResidentIds = Resident::where('current_household_id', $household->id)->pluck('id')->all();
                Resident::whereIn('id', $linkedResidentIds)->update(['current_household_id' => null]);

                // Update household location
                $household->rw_id = $targetRt->rw_id;
                $household->rt_id = $targetRt->id;
                $household->address = $newAddress;
                if ($notes !== null) {
                    $household->notes = $notes;
                }
                $household->save();

                // Reattach residents with updated RT scope and append movement records
                foreach ($linkedResidentIds as $resId) {
                    /** @var Resident $res */
                    $res = Resident::where('id', $resId)->lockForUpdate()->firstOrFail();
                    $res->rw_id = $targetRt->rw_id;
                    $res->rt_id = $targetRt->id;
                    $res->current_household_id = $household->id;
                    $res->save();

                    ResidencyMovement::create([
                        'resident_id' => $res->id,
                        'movement_type' => ResidencyMovementType::TransferInternal,
                        'from_organization_id' => $fromOrgId,
                        'from_rw_id' => $fromRwId,
                        'from_rt_id' => $fromRtId,
                        'from_household_id' => $household->id,
                        'to_organization_id' => $targetRt->rw->organization_id,
                        'to_rw_id' => $targetRt->rw_id,
                        'to_rt_id' => $targetRt->id,
                        'to_household_id' => $household->id,
                        'effective_date' => $effectiveDate->toDateString(),
                        'notes' => $notes ?? 'Household relocated to new RT',
                        'recorded_by' => $recordedBy,
                    ]);
                }

                $this->auditLogger->record(
                    actor: $actor,
                    event: 'household.relocated_rt',
                    description: "Household {$household->kk_number} relocated from RT {$fromRtId} to RT {$targetRt->id}",
                    subject: $household,
                    scope: ScopeRef::rt($household->organization_id, $household->rw_id, $household->rt_id),
                    properties: [
                        'household_id' => $household->id,
                        'from_rt_id' => $fromRtId,
                        'to_rt_id' => $targetRt->id,
                        'address' => $newAddress,
                    ]
                );

                return $household;
            }

            // Scenario 3: Physical address update within same RT
            $household->address = $newAddress;
            if ($notes !== null) {
                $household->notes = $notes;
            }
            $household->save();

            $this->auditLogger->record(
                actor: $actor,
                event: 'household.address_updated',
                description: "Household {$household->kk_number} address updated to {$newAddress}",
                subject: $household,
                scope: ScopeRef::rt($household->organization_id, $household->rw_id, $household->rt_id),
                properties: [
                    'household_id' => $household->id,
                    'address' => $newAddress,
                ]
            );

            return $household;
        });
    }

    private function resolveFallbackActorId(): string
    {
        $firstUser = User::first();
        if ($firstUser !== null) {
            return $firstUser->id;
        }

        throw new \RuntimeException('No user found to record movement.');
    }
}
