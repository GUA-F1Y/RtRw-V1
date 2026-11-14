<?php

declare(strict_types=1);

namespace App\Domain\Household\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Household\Enums\HouseholdStatus;
use App\Domain\Household\Exceptions\InvalidHouseholdStatusTransition;
use App\Domain\Household\Models\Household;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class HouseholdStatusStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        HouseholdStatus::Active->value => [
            HouseholdStatus::HeadVacant->value,
            HouseholdStatus::Moved->value,
            HouseholdStatus::Dissolved->value,
        ],
        HouseholdStatus::HeadVacant->value => [
            HouseholdStatus::Active->value,
            HouseholdStatus::Dissolved->value,
            HouseholdStatus::Moved->value,
        ],
        HouseholdStatus::Moved->value => [
            HouseholdStatus::Archived->value,
        ],
        HouseholdStatus::Dissolved->value => [
            HouseholdStatus::Archived->value,
        ],
        HouseholdStatus::Archived->value => [],
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function canTransition(HouseholdStatus $from, HouseholdStatus $to): bool
    {
        $allowed = self::ALLOWED_TRANSITIONS[$from->value] ?? [];

        return in_array($to->value, $allowed, true);
    }

    public function assertCanTransition(HouseholdStatus $from, HouseholdStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw new InvalidHouseholdStatusTransition($from->value, $to->value);
        }
    }

    public function transition(
        Household $household,
        HouseholdStatus $newStatus,
        ?string $reason = null,
        ?User $actor = null
    ): Household {
        return DB::transaction(function () use ($household, $newStatus, $reason, $actor): Household {
            /** @var Household $lockedHousehold */
            $lockedHousehold = Household::where('id', $household->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = $lockedHousehold->status;

            $this->assertCanTransition($currentStatus, $newStatus);

            $lockedHousehold->status = $newStatus;
            $lockedHousehold->save();

            $this->auditLogger->record(
                actor: $actor,
                event: 'household.status_changed',
                description: "Household {$lockedHousehold->kk_number} status transitioned from {$currentStatus->value} to {$newStatus->value}",
                subject: $lockedHousehold,
                scope: ScopeRef::rt($lockedHousehold->organization_id, $lockedHousehold->rw_id, $lockedHousehold->rt_id),
                properties: [
                    'household_id' => $lockedHousehold->id,
                    'previous_status' => $currentStatus->value,
                    'new_status' => $newStatus->value,
                    'reason' => $reason,
                ]
            );

            return $lockedHousehold;
        });
    }
}
