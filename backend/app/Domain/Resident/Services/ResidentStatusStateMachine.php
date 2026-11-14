<?php

declare(strict_types=1);

namespace App\Domain\Resident\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Household\Enums\HouseholdStatus;
use App\Domain\Household\Models\Household;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountSuspensionService;
use App\Domain\Resident\Enums\HouseholdMemberRelationship;
use App\Domain\Resident\Enums\ResidentStatus;
use App\Domain\Resident\Exceptions\InvalidResidentStatusTransition;
use App\Domain\Resident\Models\HouseholdMember;
use App\Domain\Resident\Models\Resident;
use App\Domain\Resident\Models\ResidentStatusHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ResidentStatusStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        ResidentStatus::Prospective->value => [
            ResidentStatus::Active->value,
        ],
        ResidentStatus::Active->value => [
            ResidentStatus::Inactive->value,
            ResidentStatus::Moved->value,
            ResidentStatus::Deceased->value,
        ],
        ResidentStatus::Inactive->value => [
            ResidentStatus::Active->value,
            ResidentStatus::Deceased->value,
        ],
        ResidentStatus::Moved->value => [
            ResidentStatus::Active->value,
        ],
        ResidentStatus::Deceased->value => [],
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AccountSuspensionService $accountSuspensionService,
    ) {}

    public function canTransition(ResidentStatus $from, ResidentStatus $to): bool
    {
        $allowed = self::ALLOWED_TRANSITIONS[$from->value] ?? [];

        return in_array($to->value, $allowed, true);
    }

    public function assertCanTransition(ResidentStatus $from, ResidentStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw new InvalidResidentStatusTransition($from->value, $to->value);
        }
    }

    /**
     * Atomically executes a resident status transition with all business side-effects.
     */
    public function transition(
        Resident $resident,
        ResidentStatus $newStatus,
        ?string $reason = null,
        ?string $referenceLetterNumber = null,
        ?string $attachmentFileId = null,
        ?User $actor = null,
        ?Carbon $effectiveAt = null
    ): Resident {
        return DB::transaction(function () use (
            $resident,
            $newStatus,
            $reason,
            $referenceLetterNumber,
            $attachmentFileId,
            $actor,
            $effectiveAt
        ): Resident {
            // 1. Lock resident row
            /** @var Resident $lockedResident */
            $lockedResident = Resident::where('id', $resident->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = $lockedResident->status;

            // 2. Verify legal transition
            $this->assertCanTransition($currentStatus, $newStatus);

            $effective = $effectiveAt ?? Carbon::now();

            // 3. Execute domain side effects
            if ($newStatus === ResidentStatus::Deceased) {
                $this->handleDeathSideEffects($lockedResident, $effective, $actor);
            } elseif ($newStatus === ResidentStatus::Moved) {
                $this->handleMovedSideEffects($lockedResident, $effective, $actor);
            }

            // 4. Update resident status
            $lockedResident->status = $newStatus;
            $lockedResident->save();

            // 5. Append resident_status_history (immutable ledger)
            ResidentStatusHistory::create([
                'resident_id' => $lockedResident->id,
                'previous_status' => $currentStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
                'effective_at' => $effective,
                'reference_letter_number' => $referenceLetterNumber,
                'attachment_file_id' => $attachmentFileId,
                'recorded_by' => $actor?->id ?? $this->resolveFallbackActorId(),
            ]);

            // 6. Record audit
            $this->auditLogger->record(
                actor: $actor,
                event: $newStatus === ResidentStatus::Deceased ? 'resident.marked_deceased' : 'resident.status_changed',
                description: "Resident status transitioned from {$currentStatus->value} to {$newStatus->value} for NIK: {$lockedResident->nik}",
                subject: $lockedResident,
                scope: ScopeRef::rt($lockedResident->organization_id, $lockedResident->rw_id, $lockedResident->rt_id),
                properties: [
                    'resident_id' => $lockedResident->id,
                    'previous_status' => $currentStatus->value,
                    'new_status' => $newStatus->value,
                    'reason' => $reason,
                    'reference_letter_number' => $referenceLetterNumber,
                ]
            );

            return $lockedResident;
        });
    }

    /**
     * Side effects for civil death (ACTIVE/INACTIVE -> DECEASED)
     */
    private function handleDeathSideEffects(Resident $resident, Carbon $effectiveAt, ?User $actor): void
    {
        // A. Deactivate active household membership if any
        $activeMembership = HouseholdMember::where('resident_id', $resident->id)
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();

        if ($activeMembership !== null) {
            $householdId = $activeMembership->household_id;
            $wasHead = $activeMembership->relationship === HouseholdMemberRelationship::KepalaKeluarga;

            $activeMembership->is_active = false;
            $activeMembership->left_at = $effectiveAt->toDateString();
            $activeMembership->left_reason = 'DECEASED';
            $activeMembership->save();

            // Clear current household pointer
            $resident->current_household_id = null;

            // B. Handle household vacancy/dissolution
            /** @var Household $household */
            $household = Household::where('id', $householdId)
                ->lockForUpdate()
                ->firstOrFail();

            $remainingActiveCount = HouseholdMember::where('household_id', $householdId)
                ->where('is_active', true)
                ->count();

            if ($remainingActiveCount === 0) {
                $household->status = HouseholdStatus::Dissolved;
                $household->save();

                $this->auditLogger->record(
                    actor: $actor,
                    event: 'household.dissolved',
                    description: "Household {$household->kk_number} dissolved as all members are deceased/departed",
                    subject: $household,
                    scope: ScopeRef::rt($household->organization_id, $household->rw_id, $household->rt_id),
                    properties: [
                        'household_id' => $household->id,
                        'deceased_resident_id' => $resident->id,
                        'status' => HouseholdStatus::Dissolved->value,
                    ]
                );
            } elseif ($wasHead) {
                $household->status = HouseholdStatus::HeadVacant;
                $household->save();

                $this->auditLogger->record(
                    actor: $actor,
                    event: 'household.head_vacated',
                    description: "Household {$household->kk_number} transitioned to HEAD_VACANT due to death of head",
                    subject: $household,
                    scope: ScopeRef::rt($household->organization_id, $household->rw_id, $household->rt_id),
                    properties: [
                        'household_id' => $household->id,
                        'deceased_resident_id' => $resident->id,
                        'status' => HouseholdStatus::HeadVacant->value,
                    ]
                );
            }
        } else {
            $resident->current_household_id = null;
        }

        // C. Suspend linked user account and revoke tokens via Identity domain capability
        if ($resident->user_id !== null) {
            $this->accountSuspensionService->suspendOnCivilDeath($resident->user_id, $resident->id, $actor);
        }
    }

    /**
     * Side effects for moving out of active domicile
     */
    private function handleMovedSideEffects(Resident $resident, Carbon $effectiveAt, ?User $actor): void
    {
        $activeMembership = HouseholdMember::where('resident_id', $resident->id)
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();

        if ($activeMembership !== null) {
            $householdId = $activeMembership->household_id;
            $wasHead = $activeMembership->relationship === HouseholdMemberRelationship::KepalaKeluarga;

            $activeMembership->is_active = false;
            $activeMembership->left_at = $effectiveAt->toDateString();
            $activeMembership->left_reason = 'MOVED';
            $activeMembership->save();

            $resident->current_household_id = null;

            /** @var Household $household */
            $household = Household::where('id', $householdId)
                ->lockForUpdate()
                ->firstOrFail();

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
        } else {
            $resident->current_household_id = null;
        }
    }

    private function resolveFallbackActorId(): string
    {
        // When no actor passed (e.g. system CLI or background task), find first user or throw
        $firstUser = User::first();
        if ($firstUser !== null) {
            return $firstUser->id;
        }

        throw new \RuntimeException('No user found to record status transition in immutable ledger.');
    }
}
