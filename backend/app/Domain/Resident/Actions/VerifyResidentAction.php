<?php

declare(strict_types=1);

namespace App\Domain\Resident\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Resident\Enums\ResidentStatus;
use App\Domain\Resident\Exceptions\InvalidResidentStatusTransition;
use App\Domain\Resident\Models\Resident;
use App\Domain\Resident\Services\ResidentStatusStateMachine;
use Illuminate\Support\Facades\DB;

final class VerifyResidentAction
{
    public function __construct(
        private readonly ResidentStatusStateMachine $statusStateMachine,
    ) {}

    public function execute(
        string $residentId,
        ?string $notes = null,
        ?string $referenceLetterNumber = null,
        ?string $attachmentFileId = null,
        ?User $actor = null
    ): Resident {
        return DB::transaction(function () use (
            $residentId,
            $notes,
            $referenceLetterNumber,
            $attachmentFileId,
            $actor
        ): Resident {
            /** @var Resident $resident */
            $resident = Resident::where('id', $residentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($resident->status !== ResidentStatus::Prospective) {
                throw new InvalidResidentStatusTransition(
                    $resident->status->value,
                    ResidentStatus::Active->value,
                    "Only prospective residents can be verified through this action. Current status is {$resident->status->value}."
                );
            }

            return $this->statusStateMachine->transition(
                resident: $resident,
                newStatus: ResidentStatus::Active,
                reason: $notes ?? 'Resident verified by officer',
                referenceLetterNumber: $referenceLetterNumber,
                attachmentFileId: $attachmentFileId,
                actor: $actor
            );
        });
    }
}
