<?php

declare(strict_types=1);

namespace App\Domain\Resident\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Rt;
use App\Domain\Resident\DTOs\RegisterResidentInput;
use App\Domain\Resident\Enums\ResidentStatus;
use App\Domain\Resident\Exceptions\DuplicateNikException;
use App\Domain\Resident\Exceptions\ResidentScopeMismatch;
use App\Domain\Resident\Models\Resident;
use App\Domain\Resident\Models\ResidentStatusHistory;
use App\Support\Api\Exceptions\ApiException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class RegisterResidentAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(RegisterResidentInput $input, ?User $actor = null): Resident
    {
        // 1. Validate NIK format
        if (! preg_match('/^[0-9]{16}$/', $input->nik)) {
            throw new ApiException('NIK must be exactly 16 numeric digits.', 422, 'invalid_nik_format');
        }

        // 2. Resolve and validate organizational hierarchy from database
        /** @var Rt|null $rt */
        $rt = Rt::with('rw')->find($input->rtId);
        if ($rt === null) {
            throw new ResidentScopeMismatch("RT '{$input->rtId}' does not exist.");
        }

        if ($rt->rw_id !== $input->rwId || $rt->rw === null || $rt->rw->organization_id !== $input->organizationId) {
            throw new ResidentScopeMismatch('Organizational hierarchy mismatch between RT, RW, and Organization.');
        }

        // 3. Check duplicate NIK prior to insert
        if (Resident::where('nik', $input->nik)->exists()) {
            throw new DuplicateNikException($input->nik);
        }

        // 4. Validate user_id if provided
        if ($input->userId !== null) {
            $userExists = User::where('id', $input->userId)->exists();
            if (! $userExists) {
                throw new ApiException("User '{$input->userId}' does not exist.", 422, 'user_not_found');
            }
            if (Resident::where('user_id', $input->userId)->exists()) {
                throw new ApiException("User '{$input->userId}' is already linked to another resident.", 409, 'user_already_linked');
            }
        }

        try {
            return DB::transaction(function () use ($input, $actor): Resident {
                /** @var Resident $resident */
                $resident = Resident::create([
                    'organization_id' => $input->organizationId,
                    'rw_id' => $input->rwId,
                    'rt_id' => $input->rtId,
                    'user_id' => $input->userId,
                    'current_household_id' => null,
                    'nik' => $input->nik,
                    'name' => $input->name,
                    'gender' => $input->gender,
                    'birth_place' => $input->birthPlace,
                    'birth_date' => $input->birthDate,
                    'religion' => $input->religion,
                    'marital_status' => $input->maritalStatus,
                    'blood_type' => $input->bloodType,
                    'occupation' => $input->occupation,
                    'education' => $input->education,
                    'phone' => $input->phone,
                    'residency_type' => $input->residencyType,
                    'status' => ResidentStatus::Prospective,
                ]);

                // Append initial resident status history
                ResidentStatusHistory::create([
                    'resident_id' => $resident->id,
                    'previous_status' => ResidentStatus::Prospective,
                    'new_status' => ResidentStatus::Prospective,
                    'reason' => 'Initial civil registration',
                    'effective_at' => Carbon::now(),
                    'recorded_by' => $actor?->id ?? $this->resolveFallbackActorId(),
                ]);

                // Audit logging
                $this->auditLogger->record(
                    actor: $actor,
                    event: 'resident.registered',
                    description: "Resident registered: {$resident->name} (NIK: {$resident->nik})",
                    subject: $resident,
                    scope: ScopeRef::rt($resident->organization_id, $resident->rw_id, $resident->rt_id),
                    properties: [
                        'resident_id' => $resident->id,
                        'nik' => $resident->nik,
                        'name' => $resident->name,
                        'status' => $resident->status->value,
                    ]
                );

                return $resident;
            });
        } catch (QueryException $e) {
            // Convert PostgreSQL unique constraint violation on NIK to domain exception
            if (str_contains($e->getMessage(), 'residents_nik_unique')) {
                throw new DuplicateNikException($input->nik);
            }
            throw $e;
        }
    }

    private function resolveFallbackActorId(): string
    {
        $firstUser = User::first();
        if ($firstUser !== null) {
            return $firstUser->id;
        }

        throw new \RuntimeException('No user found to record resident registration.');
    }
}
