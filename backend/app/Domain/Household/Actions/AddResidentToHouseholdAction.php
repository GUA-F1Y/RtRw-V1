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
use App\Domain\Resident\Exceptions\HouseholdMembershipConflict;
use App\Domain\Resident\Exceptions\InvalidHouseholdHeadChange;
use App\Domain\Resident\Exceptions\ResidentScopeMismatch;
use App\Domain\Resident\Models\HouseholdMember;
use App\Domain\Resident\Models\Resident;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class AddResidentToHouseholdAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(
        string $householdId,
        string $residentId,
        HouseholdMemberRelationship $relationship,
        ?string $joinedAt = null,
        ?User $actor = null
    ): HouseholdMember {
        try {
            return DB::transaction(function () use (
                $householdId,
                $residentId,
                $relationship,
                $joinedAt,
                $actor
            ): HouseholdMember {
                /** @var Household $household */
                $household = Household::where('id', $householdId)
                    ->lockForUpdate()
                    ->firstOrFail();

                /** @var Resident $resident */
                $resident = Resident::where('id', $residentId)
                    ->lockForUpdate()
                    ->firstOrFail();

                // 1. Verify compatible household status
                if ($household->status !== HouseholdStatus::Active && $household->status !== HouseholdStatus::HeadVacant) {
                    throw new HouseholdStateConflict("Cannot add members to household with status '{$household->status->value}'.");
                }

                // 2. Verify scope alignment (same RT)
                if ($resident->rt_id !== $household->rt_id) {
                    throw new ResidentScopeMismatch("Resident RT ({$resident->rt_id}) does not match Household RT ({$household->rt_id}).");
                }

                // 3. Verify resident civil status permits membership
                if ($resident->status === ResidentStatus::Prospective || $resident->status === ResidentStatus::Deceased || $resident->status === ResidentStatus::Moved) {
                    throw new HouseholdMembershipConflict("Resident with status '{$resident->status->value}' cannot join a household.");
                }

                // 4. Verify resident does not already have an active household membership
                $existingActiveMembership = HouseholdMember::where('resident_id', $resident->id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if ($existingActiveMembership !== null) {
                    throw new HouseholdMembershipConflict('Resident already belongs to an active household.');
                }

                // 5. If appointed as head, verify candidate is active and no active head exists
                if ($relationship === HouseholdMemberRelationship::KepalaKeluarga) {
                    if ($resident->status !== ResidentStatus::Active) {
                        throw new InvalidHouseholdHeadChange("Only active residents can be appointed as household head. Resident status is '{$resident->status->value}'.");
                    }
                    $existingHead = HouseholdMember::where('household_id', $household->id)
                        ->where('relationship', HouseholdMemberRelationship::KepalaKeluarga)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if ($existingHead !== null) {
                        throw new InvalidHouseholdHeadChange('Household already has an active head. Use ChangeHouseholdHeadAction to change head.');
                    }

                    // If household was HEAD_VACANT, transition to ACTIVE
                    if ($household->status === HouseholdStatus::HeadVacant) {
                        $household->status = HouseholdStatus::Active;
                        $household->save();
                    }
                }

                $membershipDate = $joinedAt !== null ? Carbon::parse($joinedAt)->toDateString() : Carbon::now()->toDateString();

                /** @var HouseholdMember $membership */
                $membership = HouseholdMember::create([
                    'household_id' => $household->id,
                    'resident_id' => $resident->id,
                    'relationship' => $relationship,
                    'is_active' => true,
                    'joined_at' => $membershipDate,
                ]);

                // Update current_household_id pointer
                $resident->current_household_id = $household->id;
                $resident->save();

                $this->auditLogger->record(
                    actor: $actor,
                    event: 'household.member_added',
                    description: "Resident {$resident->name} added to household {$household->kk_number} as {$relationship->value}",
                    subject: $household,
                    scope: ScopeRef::rt($household->organization_id, $household->rw_id, $household->rt_id),
                    properties: [
                        'household_id' => $household->id,
                        'resident_id' => $resident->id,
                        'relationship' => $relationship->value,
                    ]
                );

                return $membership;
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'resident_single_active_household_unique')) {
                throw new HouseholdMembershipConflict('Resident already has an active household membership.');
            }
            if (str_contains($e->getMessage(), 'household_single_active_head_unique')) {
                throw new InvalidHouseholdHeadChange('Household already has an active head.');
            }
            throw $e;
        }
    }
}
