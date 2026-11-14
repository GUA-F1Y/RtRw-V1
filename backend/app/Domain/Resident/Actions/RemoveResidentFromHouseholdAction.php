<?php

declare(strict_types=1);

namespace App\Domain\Resident\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Household\Enums\HouseholdStatus;
use App\Domain\Household\Models\Household;
use App\Domain\Identity\Models\User;
use App\Domain\Resident\Enums\HouseholdMemberRelationship;
use App\Domain\Resident\Exceptions\HouseholdMembershipConflict;
use App\Domain\Resident\Models\HouseholdMember;
use App\Domain\Resident\Models\Resident;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class RemoveResidentFromHouseholdAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(
        string $residentId,
        ?string $leftReason = null,
        ?string $leftAt = null,
        ?User $actor = null
    ): HouseholdMember {
        return DB::transaction(function () use ($residentId, $leftReason, $leftAt, $actor): HouseholdMember {
            /** @var Resident $resident */
            $resident = Resident::where('id', $residentId)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var HouseholdMember|null $membership */
            $membership = HouseholdMember::where('resident_id', $resident->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if ($membership === null) {
                throw new HouseholdMembershipConflict('Resident does not have an active household membership to remove.');
            }

            /** @var Household $household */
            $household = Household::where('id', $membership->household_id)
                ->lockForUpdate()
                ->firstOrFail();

            $wasHead = $membership->relationship === HouseholdMemberRelationship::KepalaKeluarga;
            $departureDate = $leftAt !== null ? Carbon::parse($leftAt)->toDateString() : Carbon::now()->toDateString();

            // Deactivate membership
            $membership->is_active = false;
            $membership->left_at = $departureDate;
            $membership->left_reason = $leftReason ?? 'REMOVED';
            $membership->save();

            // Clear current_household_id
            $resident->current_household_id = null;
            $resident->save();

            // Handle household state after departure
            $remainingActiveCount = HouseholdMember::where('household_id', $household->id)
                ->where('is_active', true)
                ->count();

            if ($remainingActiveCount === 0) {
                $household->status = HouseholdStatus::Dissolved;
                $household->save();

                $this->auditLogger->record(
                    actor: $actor,
                    event: 'household.dissolved',
                    description: "Household {$household->kk_number} dissolved after last member removed",
                    subject: $household,
                    scope: ScopeRef::rt($household->organization_id, $household->rw_id, $household->rt_id),
                    properties: [
                        'household_id' => $household->id,
                    ]
                );
            } elseif ($wasHead) {
                $household->status = HouseholdStatus::HeadVacant;
                $household->save();

                $this->auditLogger->record(
                    actor: $actor,
                    event: 'household.head_vacated',
                    description: "Household {$household->kk_number} transitioned to HEAD_VACANT after head removal",
                    subject: $household,
                    scope: ScopeRef::rt($household->organization_id, $household->rw_id, $household->rt_id),
                    properties: [
                        'household_id' => $household->id,
                        'departed_head_id' => $resident->id,
                    ]
                );
            }

            $this->auditLogger->record(
                actor: $actor,
                event: 'household.member_removed',
                description: "Resident {$resident->name} removed from household {$household->kk_number}",
                subject: $household,
                scope: ScopeRef::rt($household->organization_id, $household->rw_id, $household->rt_id),
                properties: [
                    'household_id' => $household->id,
                    'resident_id' => $resident->id,
                    'was_head' => $wasHead,
                    'left_reason' => $membership->left_reason,
                ]
            );

            return $membership;
        });
    }
}
