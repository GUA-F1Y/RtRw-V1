<?php

declare(strict_types=1);

namespace App\Domain\Household\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Household\Models\Household;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateHouseholdAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{
     *     address?: string|null,
     *     notes?: string|null,
     * }  $attributes
     */
    public function execute(Household $household, array $attributes, ?User $actor = null): Household
    {
        return DB::transaction(function () use ($household, $attributes, $actor): Household {
            /** @var Household $lockedHousehold */
            $lockedHousehold = Household::where('id', $household->id)
                ->lockForUpdate()
                ->firstOrFail();

            $changedFields = [];

            if (array_key_exists('address', $attributes) && $attributes['address'] !== null) {
                $lockedHousehold->address = (string) $attributes['address'];
                $changedFields[] = 'address';
            }

            if (array_key_exists('notes', $attributes)) {
                $lockedHousehold->notes = $attributes['notes'] !== null ? (string) $attributes['notes'] : null;
                $changedFields[] = 'notes';
            }

            $lockedHousehold->save();

            $this->auditLogger->record(
                actor: $actor,
                event: 'household.updated',
                description: "Household updated for KK: {$lockedHousehold->kk_number}",
                subject: $lockedHousehold,
                scope: ScopeRef::rt($lockedHousehold->organization_id, $lockedHousehold->rw_id, $lockedHousehold->rt_id),
                properties: [
                    'household_id' => $lockedHousehold->id,
                    'changed_fields' => $changedFields,
                ]
            );

            return $lockedHousehold;
        });
    }
}
