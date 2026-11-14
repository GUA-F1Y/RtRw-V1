<?php

declare(strict_types=1);

namespace App\Domain\Household\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Household\Enums\HouseholdStatus;
use App\Domain\Household\Exceptions\HouseholdStateConflict;
use App\Domain\Household\Models\Household;
use App\Domain\Identity\Models\User;
use App\Domain\Resident\Models\HouseholdMember;
use App\Domain\Resident\Models\Resident;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class DissolveHouseholdAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(string $householdId, ?string $reason = null, ?User $actor = null): Household
    {
        return DB::transaction(function () use ($householdId, $reason, $actor): Household {
            /** @var Household $household */
            $household = Household::where('id', $householdId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($household->status !== HouseholdStatus::Active && $household->status !== HouseholdStatus::HeadVacant) {
                throw new HouseholdStateConflict("Cannot dissolve household with status '{$household->status->value}'.");
            }

            $dissolutionDate = Carbon::now()->toDateString();
            $dissolutionReason = $reason ?? 'HOUSEHOLD_DISSOLVED';

            // 1. Deactivate all active memberships
            $activeMemberships = HouseholdMember::where('household_id', $household->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get();

            foreach ($activeMemberships as $membership) {
                $membership->is_active = false;
                $membership->left_at = $dissolutionDate;
                $membership->left_reason = $dissolutionReason;
                $membership->save();
            }

            // 2. Clear current_household_id on all linked residents
            Resident::where('current_household_id', $household->id)
                ->update(['current_household_id' => null]);

            // 3. Transition status to DISSOLVED
            $household->status = HouseholdStatus::Dissolved;
            $household->save();

            $this->auditLogger->record(
                actor: $actor,
                event: 'household.dissolved',
                description: "Household {$household->kk_number} dissolved. Reason: {$dissolutionReason}",
                subject: $household,
                scope: ScopeRef::rt($household->organization_id, $household->rw_id, $household->rt_id),
                properties: [
                    'household_id' => $household->id,
                    'kk_number' => $household->kk_number,
                    'reason' => $dissolutionReason,
                    'members_deactivated_count' => $activeMemberships->count(),
                ]
            );

            return $household;
        });
    }
}
