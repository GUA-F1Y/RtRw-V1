<?php

declare(strict_types=1);

namespace App\Domain\Resident\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Household\Enums\HouseholdStatus;
use App\Domain\Household\Models\Household;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Rt;
use App\Domain\Resident\DTOs\RelocateResidentInput;
use App\Domain\Resident\Enums\HouseholdMemberRelationship;
use App\Domain\Resident\Enums\ResidencyMovementType;
use App\Domain\Resident\Enums\ResidentStatus;
use App\Domain\Resident\Exceptions\HouseholdMembershipConflict;
use App\Domain\Resident\Exceptions\HouseholdStateConflict;
use App\Domain\Resident\Exceptions\InvalidResidentStatusTransition;
use App\Domain\Resident\Exceptions\ResidentScopeMismatch;
use App\Domain\Resident\Models\HouseholdMember;
use App\Domain\Resident\Models\ResidencyMovement;
use App\Domain\Resident\Models\Resident;
use App\Domain\Resident\Services\ResidentStatusStateMachine;
use App\Support\Api\Exceptions\ApiException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class RelocateResidentAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ResidentStatusStateMachine $statusStateMachine,
    ) {}

    public function execute(RelocateResidentInput $input, ?User $actor = null): ResidencyMovement
    {
        return DB::transaction(function () use ($input, $actor): ResidencyMovement {
            /** @var Resident $resident */
            $resident = Resident::where('id', $input->residentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($resident->status === ResidentStatus::Deceased) {
                throw new InvalidResidentStatusTransition(
                    ResidentStatus::Deceased->value,
                    $resident->status->value,
                    'Cannot relocate a deceased resident.'
                );
            }

            $effectiveDate = $input->effectiveDate !== null
                ? Carbon::parse($input->effectiveDate)
                : Carbon::now();

            $recordedBy = $actor?->id ?? $this->resolveFallbackActorId();

            return match ($input->movementType) {
                ResidencyMovementType::TransferInternal => $this->handleTransferInternal(
                    $resident,
                    $input,
                    $effectiveDate,
                    $recordedBy,
                    $actor
                ),
                ResidencyMovementType::MoveOutExternal => $this->handleMoveOutExternal(
                    $resident,
                    $input,
                    $effectiveDate,
                    $recordedBy,
                    $actor
                ),
                ResidencyMovementType::MoveInExternal => $this->handleMoveInExternal(
                    $resident,
                    $input,
                    $effectiveDate,
                    $recordedBy,
                    $actor
                ),
                ResidencyMovementType::HouseholdSplit => $this->handleHouseholdSplit(
                    $resident,
                    $input,
                    $effectiveDate,
                    $recordedBy,
                    $actor
                ),
            };
        });
    }

    /**
     * Scenario A: Relocation between RTs in the same organization
     */
    private function handleTransferInternal(
        Resident $resident,
        RelocateResidentInput $input,
        Carbon $effectiveDate,
        string $recordedBy,
        ?User $actor
    ): ResidencyMovement {
        if ($input->toRtId === null) {
            throw new ApiException('Target RT ID (toRtId) is required for internal transfer.', 422, 'target_rt_required');
        }

        if ($resident->rt_id === $input->toRtId) {
            throw new ApiException('Internal transfer requires moving to a different RT.', 422, 'same_rt_transfer');
        }

        /** @var Rt|null $targetRt */
        $targetRt = Rt::with('rw')->find($input->toRtId);
        if ($targetRt === null) {
            throw new ResidentScopeMismatch("Target RT '{$input->toRtId}' does not exist.");
        }

        if ($targetRt->rw === null || $targetRt->rw->organization_id !== $resident->organization_id) {
            throw new ResidentScopeMismatch('Target RT does not belong to the same organization.');
        }

        if ($resident->current_household_id === null) {
            throw new HouseholdMembershipConflict('Resident must belong to a household to execute an internal transfer.');
        }

        if ($input->toHouseholdId === null) {
            throw new ApiException('Target household ID (toHouseholdId) is required for internal transfer.', 422, 'target_household_required');
        }

        /** @var Household|null $targetHousehold */
        $targetHousehold = Household::where('id', $input->toHouseholdId)
            ->where('rt_id', $targetRt->id)
            ->first();

        if ($targetHousehold === null) {
            throw new ResidentScopeMismatch("Target household '{$input->toHouseholdId}' does not exist in target RT.");
        }

        if ($resident->current_household_id === $targetHousehold->id) {
            throw new ApiException('Source and target households must be different for internal transfer.', 422, 'same_household_transfer');
        }

        // Trust ONLY current resident state for "from" scope
        $fromOrgId = $resident->organization_id;
        $fromRwId = $resident->rw_id;
        $fromRtId = $resident->rt_id;
        $fromHouseholdId = $resident->current_household_id;

        // Deactivate active household membership in old RT
        $this->deactivateActiveHouseholdMembership($resident, $effectiveDate, 'TRANSFER_RT', $actor);

        // Update resident scope to new RT and new household
        $resident->rw_id = $targetRt->rw_id;
        $resident->rt_id = $targetRt->id;
        $resident->current_household_id = $targetHousehold->id;
        $resident->save();

        HouseholdMember::create([
            'household_id' => $targetHousehold->id,
            'resident_id' => $resident->id,
            'relationship' => HouseholdMemberRelationship::FamiliLain,
            'is_active' => true,
            'joined_at' => $effectiveDate->toDateString(),
        ]);

        /** @var ResidencyMovement $movement */
        $movement = ResidencyMovement::create([
            'resident_id' => $resident->id,
            'movement_type' => ResidencyMovementType::TransferInternal,
            'from_organization_id' => $fromOrgId,
            'from_rw_id' => $fromRwId,
            'from_rt_id' => $fromRtId,
            'from_household_id' => $fromHouseholdId,
            'to_organization_id' => $targetRt->rw->organization_id,
            'to_rw_id' => $targetRt->rw_id,
            'to_rt_id' => $targetRt->id,
            'to_household_id' => $targetHousehold->id,
            'effective_date' => $effectiveDate->toDateString(),
            'reference_letter_number' => $input->referenceLetterNumber,
            'attachment_file_id' => $input->attachmentFileId,
            'notes' => $input->notes,
            'recorded_by' => $recordedBy,
        ]);

        $this->auditLogger->record(
            actor: $actor,
            event: 'resident.transferred_rt',
            description: "Resident transferred from RT {$fromRtId} to RT {$targetRt->id}",
            subject: $resident,
            scope: ScopeRef::rt($resident->organization_id, $resident->rw_id, $resident->rt_id),
            properties: [
                'movement_id' => $movement->id,
                'from_rt_id' => $fromRtId,
                'to_rt_id' => $targetRt->id,
            ]
        );

        return $movement;
    }

    /**
     * Scenario B: Resident leaves the organization
     */
    private function handleMoveOutExternal(
        Resident $resident,
        RelocateResidentInput $input,
        Carbon $effectiveDate,
        string $recordedBy,
        ?User $actor
    ): ResidencyMovement {
        if ($resident->current_household_id === null) {
            throw new HouseholdMembershipConflict('Resident must belong to a household to execute an external move out.');
        }

        $fromOrgId = $resident->organization_id;
        $fromRwId = $resident->rw_id;
        $fromRtId = $resident->rt_id;
        $fromHouseholdId = $resident->current_household_id;

        // Transition status to MOVED using state machine (handles household deactivation)
        $this->statusStateMachine->transition(
            resident: $resident,
            newStatus: ResidentStatus::Moved,
            reason: $input->notes ?? 'External move out from community',
            referenceLetterNumber: $input->referenceLetterNumber,
            attachmentFileId: $input->attachmentFileId,
            actor: $actor,
            effectiveAt: $effectiveDate
        );

        // Reload resident state
        $resident->refresh();

        /** @var ResidencyMovement $movement */
        $movement = ResidencyMovement::create([
            'resident_id' => $resident->id,
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
            'reference_letter_number' => $input->referenceLetterNumber,
            'attachment_file_id' => $input->attachmentFileId,
            'notes' => $input->notes,
            'recorded_by' => $recordedBy,
        ]);

        $this->auditLogger->record(
            actor: $actor,
            event: 'resident.moved_out_external',
            description: "Resident moved out externally from organization: {$resident->nik}",
            subject: $resident,
            scope: ScopeRef::rt($fromOrgId, $fromRwId, $fromRtId),
            properties: [
                'movement_id' => $movement->id,
                'from_rt_id' => $fromRtId,
            ]
        );

        return $movement;
    }

    /**
     * Scenario C: Resident enters the organization from outside
     */
    private function handleMoveInExternal(
        Resident $resident,
        RelocateResidentInput $input,
        Carbon $effectiveDate,
        string $recordedBy,
        ?User $actor
    ): ResidencyMovement {
        if ($input->toRtId === null) {
            throw new ApiException('Target RT ID (toRtId) is required for external move in.', 422, 'target_rt_required');
        }

        if ($input->toHouseholdId === null) {
            throw new ApiException('Target household ID (toHouseholdId) is required for external move in.', 422, 'target_household_required');
        }

        /** @var Rt|null $targetRt */
        $targetRt = Rt::with('rw')->find($input->toRtId);
        if ($targetRt === null) {
            throw new ResidentScopeMismatch("Target RT '{$input->toRtId}' does not exist.");
        }

        /** @var Household|null $targetHousehold */
        $targetHousehold = Household::where('id', $input->toHouseholdId)
            ->where('rt_id', $targetRt->id)
            ->first();

        if ($targetHousehold === null) {
            throw new ResidentScopeMismatch("Target household '{$input->toHouseholdId}' does not exist in target RT.");
        }

        // Update resident scope to target RT and target household
        $resident->organization_id = $targetRt->rw->organization_id;
        $resident->rw_id = $targetRt->rw_id;
        $resident->rt_id = $targetRt->id;
        $resident->current_household_id = $targetHousehold->id;
        $resident->save();

        HouseholdMember::create([
            'household_id' => $targetHousehold->id,
            'resident_id' => $resident->id,
            'relationship' => HouseholdMemberRelationship::FamiliLain,
            'is_active' => true,
            'joined_at' => $effectiveDate->toDateString(),
        ]);

        // If status was MOVED, transition back to ACTIVE
        if ($resident->status === ResidentStatus::Moved) {
            $this->statusStateMachine->transition(
                resident: $resident,
                newStatus: ResidentStatus::Active,
                reason: $input->notes ?? 'External move in re-entry',
                referenceLetterNumber: $input->referenceLetterNumber,
                attachmentFileId: $input->attachmentFileId,
                actor: $actor,
                effectiveAt: $effectiveDate
            );
        }

        $resident->refresh();

        /** @var ResidencyMovement $movement */
        $movement = ResidencyMovement::create([
            'resident_id' => $resident->id,
            'movement_type' => ResidencyMovementType::MoveInExternal,
            'from_organization_id' => null,
            'from_rw_id' => null,
            'from_rt_id' => null,
            'from_household_id' => null,
            'to_organization_id' => $targetRt->rw->organization_id,
            'to_rw_id' => $targetRt->rw_id,
            'to_rt_id' => $targetRt->id,
            'to_household_id' => $targetHousehold->id,
            'effective_date' => $effectiveDate->toDateString(),
            'reference_letter_number' => $input->referenceLetterNumber,
            'attachment_file_id' => $input->attachmentFileId,
            'notes' => $input->notes,
            'recorded_by' => $recordedBy,
        ]);

        $this->auditLogger->record(
            actor: $actor,
            event: 'resident.moved_in_external',
            description: "Resident moved in externally to RT {$targetRt->id}: {$resident->nik}",
            subject: $resident,
            scope: ScopeRef::rt($resident->organization_id, $resident->rw_id, $resident->rt_id),
            properties: [
                'movement_id' => $movement->id,
                'to_rt_id' => $targetRt->id,
            ]
        );

        return $movement;
    }

    /**
     * Scenario D: Household split within same RT
     */
    private function handleHouseholdSplit(
        Resident $resident,
        RelocateResidentInput $input,
        Carbon $effectiveDate,
        string $recordedBy,
        ?User $actor
    ): ResidencyMovement {
        if ($input->toHouseholdId === null) {
            throw new ApiException('Target household ID (toHouseholdId) is required for household split.', 422, 'target_household_required');
        }

        if ($resident->current_household_id === null) {
            throw new HouseholdMembershipConflict('Resident does not currently belong to a household to split from.');
        }

        if ($resident->current_household_id === $input->toHouseholdId) {
            throw new HouseholdMembershipConflict('Source and target households must be different for a split.');
        }

        /** @var Household|null $targetHousehold */
        $targetHousehold = Household::where('id', $input->toHouseholdId)
            ->where('rt_id', $resident->rt_id)
            ->first();

        if ($targetHousehold === null) {
            throw new ResidentScopeMismatch('Target household must belong to the same RT for a household split.');
        }

        $fromHouseholdId = $resident->current_household_id;

        // Deactivate old membership
        $this->deactivateActiveHouseholdMembership($resident, $effectiveDate, 'HOUSEHOLD_SPLIT', $actor);

        // Assign to new household
        $resident->current_household_id = $targetHousehold->id;
        $resident->save();

        $targetHasActiveHead = HouseholdMember::where('household_id', $targetHousehold->id)
            ->where('relationship', HouseholdMemberRelationship::KepalaKeluarga)
            ->where('is_active', true)
            ->exists();

        $relationship = $targetHasActiveHead
            ? HouseholdMemberRelationship::FamiliLain
            : HouseholdMemberRelationship::KepalaKeluarga;

        HouseholdMember::create([
            'household_id' => $targetHousehold->id,
            'resident_id' => $resident->id,
            'relationship' => $relationship,
            'is_active' => true,
            'joined_at' => $effectiveDate->toDateString(),
        ]);

        if (! $targetHasActiveHead && $targetHousehold->status === HouseholdStatus::HeadVacant) {
            $targetHousehold->status = HouseholdStatus::Active;
            $targetHousehold->save();
        }

        /** @var ResidencyMovement $movement */
        $movement = ResidencyMovement::create([
            'resident_id' => $resident->id,
            'movement_type' => ResidencyMovementType::HouseholdSplit,
            'from_organization_id' => $resident->organization_id,
            'from_rw_id' => $resident->rw_id,
            'from_rt_id' => $resident->rt_id,
            'from_household_id' => $fromHouseholdId,
            'to_organization_id' => $targetHousehold->organization_id,
            'to_rw_id' => $targetHousehold->rw_id,
            'to_rt_id' => $targetHousehold->rt_id,
            'to_household_id' => $targetHousehold->id,
            'effective_date' => $effectiveDate->toDateString(),
            'reference_letter_number' => $input->referenceLetterNumber,
            'attachment_file_id' => $input->attachmentFileId,
            'notes' => $input->notes,
            'recorded_by' => $recordedBy,
        ]);

        $this->auditLogger->record(
            actor: $actor,
            event: 'household.split',
            description: "Resident separated into new household via split: {$resident->nik}",
            subject: $resident,
            scope: ScopeRef::rt($resident->organization_id, $resident->rw_id, $resident->rt_id),
            properties: [
                'movement_id' => $movement->id,
                'from_household_id' => $fromHouseholdId,
                'to_household_id' => $targetHousehold->id,
            ]
        );

        return $movement;
    }

    private function deactivateActiveHouseholdMembership(
        Resident $resident,
        Carbon $effectiveDate,
        string $reason,
        ?User $actor
    ): void {
        $activeMembership = HouseholdMember::where('resident_id', $resident->id)
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();

        if ($activeMembership !== null) {
            $householdId = $activeMembership->household_id;
            $wasHead = $activeMembership->relationship === HouseholdMemberRelationship::KepalaKeluarga;

            $activeMembership->is_active = false;
            $activeMembership->left_at = $effectiveDate->toDateString();
            $activeMembership->left_reason = $reason;
            $activeMembership->save();

            $resident->current_household_id = null;

            /** @var Household|null $household */
            $household = Household::where('id', $householdId)->lockForUpdate()->first();
            if ($household !== null) {
                $remainingActiveCount = HouseholdMember::where('household_id', $householdId)
                    ->where('is_active', true)
                    ->count();

                if ($remainingActiveCount === 0) {
                    $household->status = HouseholdStatus::Moved;
                    $household->save();
                } elseif ($wasHead) {
                    $household->status = HouseholdStatus::HeadVacant;
                    $household->save();
                }
            }
        } else {
            $resident->current_household_id = null;
        }
    }

    private function resolveFallbackActorId(): string
    {
        $firstUser = User::first();
        if ($firstUser !== null) {
            return $firstUser->id;
        }

        throw new \RuntimeException('No user found to record residency movement.');
    }
}
