<?php

declare(strict_types=1);

namespace App\Domain\Household\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Household\Enums\HouseholdStatus;
use App\Domain\Household\Exceptions\HouseholdStateConflict;
use App\Domain\Household\Models\Household;
use App\Domain\Identity\Models\User;
use App\Domain\Resident\Enums\HouseholdMemberRelationship;
use App\Domain\Resident\Enums\ResidentStatus;
use App\Domain\Resident\Exceptions\InvalidHouseholdHeadChange;
use App\Domain\Resident\Models\HouseholdMember;
use App\Domain\Resident\Models\Resident;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class ChangeHouseholdHeadAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(
        string $householdId,
        string $newHeadResidentId,
        ?HouseholdMemberRelationship $oldHeadNewRelationship = null,
        ?User $actor = null
    ): HouseholdMember {
        try {
            return DB::transaction(function () use (
                $householdId,
                $newHeadResidentId,
                $oldHeadNewRelationship,
                $actor
            ): HouseholdMember {
                /** @var Household $household */
                $household = Household::where('id', $householdId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($household->status !== HouseholdStatus::Active && $household->status !== HouseholdStatus::HeadVacant) {
                    throw new HouseholdStateConflict("Cannot change head for household with status '{$household->status->value}'.");
                }

                // 1. Identify and lock new head's active membership
                /** @var HouseholdMember|null $newHeadMember */
                $newHeadMember = HouseholdMember::where('household_id', $household->id)
                    ->where('resident_id', $newHeadResidentId)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if ($newHeadMember === null) {
                    throw new InvalidHouseholdHeadChange('New head must already be an active member of this household.');
                }

                // 2. Validate new head resident eligibility
                /** @var Resident $newHeadResident */
                $newHeadResident = Resident::where('id', $newHeadResidentId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($newHeadResident->status !== ResidentStatus::Active) {
                    throw new InvalidHouseholdHeadChange("Only active residents can be appointed as household head. Resident status is '{$newHeadResident->status->value}'.");
                }

                // 3. Identify and lock current active head if one exists
                /** @var HouseholdMember|null $oldHeadMember */
                $oldHeadMember = HouseholdMember::where('household_id', $household->id)
                    ->where('relationship', HouseholdMemberRelationship::KepalaKeluarga)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if ($oldHeadMember !== null && $oldHeadMember->id === $newHeadMember->id) {
                    return $newHeadMember; // Already the active head
                }

                // 4. Demote old head if present
                if ($oldHeadMember !== null) {
                    $demotedRelationship = $oldHeadNewRelationship ?? HouseholdMemberRelationship::FamiliLain;
                    if ($demotedRelationship === HouseholdMemberRelationship::KepalaKeluarga) {
                        throw new InvalidHouseholdHeadChange('Demoted head cannot retain KEPALA_KELUARGA relationship.');
                    }
                    $oldHeadMember->relationship = $demotedRelationship;
                    $oldHeadMember->save();
                }

                // 5. Promote new head
                $newHeadMember->relationship = HouseholdMemberRelationship::KepalaKeluarga;
                $newHeadMember->save();

                // 6. Transition HEAD_VACANT -> ACTIVE if appropriate
                if ($household->status === HouseholdStatus::HeadVacant) {
                    $household->status = HouseholdStatus::Active;
                    $household->save();
                }

                $this->auditLogger->record(
                    actor: $actor,
                    event: 'household.head_changed',
                    description: "Household {$household->kk_number} appointed new head: {$newHeadResident->name}",
                    subject: $household,
                    scope: ScopeRef::rt($household->organization_id, $household->rw_id, $household->rt_id),
                    properties: [
                        'household_id' => $household->id,
                        'new_head_id' => $newHeadResident->id,
                        'old_head_id' => $oldHeadMember?->resident_id,
                    ]
                );

                return $newHeadMember;
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'household_single_active_head_unique')) {
                throw new InvalidHouseholdHeadChange('Concurrent update conflict: household can have only one active head.');
            }
            throw $e;
        }
    }
}
