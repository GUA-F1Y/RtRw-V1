<?php

declare(strict_types=1);

namespace App\Domain\Resident\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Identity\Models\User;
use App\Domain\Resident\Enums\ResidentEducation;
use App\Domain\Resident\Enums\ResidentMaritalStatus;
use App\Domain\Resident\Models\Resident;
use Illuminate\Support\Facades\DB;

final class UpdateResidentProfileAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{
     *     occupation?: string|null,
     *     education?: ResidentEducation|string|null,
     *     phone?: string|null,
     *     marital_status?: ResidentMaritalStatus|string|null,
     * }  $attributes
     */
    public function execute(Resident $resident, array $attributes, ?User $actor = null): Resident
    {
        return DB::transaction(function () use ($resident, $attributes, $actor): Resident {
            /** @var Resident $lockedResident */
            $lockedResident = Resident::where('id', $resident->id)
                ->lockForUpdate()
                ->firstOrFail();

            $changedFields = [];

            if (array_key_exists('occupation', $attributes)) {
                $lockedResident->occupation = $attributes['occupation'];
                $changedFields[] = 'occupation';
            }

            if (array_key_exists('education', $attributes) && $attributes['education'] !== null) {
                $lockedResident->education = $attributes['education'] instanceof ResidentEducation
                    ? $attributes['education']
                    : ResidentEducation::from((string) $attributes['education']);
                $changedFields[] = 'education';
            }

            if (array_key_exists('phone', $attributes)) {
                $lockedResident->phone = $attributes['phone'];
                $changedFields[] = 'phone';
            }

            if (array_key_exists('marital_status', $attributes) && $attributes['marital_status'] !== null) {
                $lockedResident->marital_status = $attributes['marital_status'] instanceof ResidentMaritalStatus
                    ? $attributes['marital_status']
                    : ResidentMaritalStatus::from((string) $attributes['marital_status']);
                $changedFields[] = 'marital_status';
            }

            $lockedResident->save();

            $this->auditLogger->record(
                actor: $actor,
                event: 'resident.updated',
                description: "Resident profile updated for NIK: {$lockedResident->nik}",
                subject: $lockedResident,
                scope: ScopeRef::rt($lockedResident->organization_id, $lockedResident->rw_id, $lockedResident->rt_id),
                properties: [
                    'resident_id' => $lockedResident->id,
                    'changed_fields' => $changedFields,
                ]
            );

            return $lockedResident;
        });
    }
}
