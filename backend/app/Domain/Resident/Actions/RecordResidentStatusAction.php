<?php

declare(strict_types=1);

namespace App\Domain\Resident\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Resident\Enums\ResidentStatus;
use App\Domain\Resident\Models\Resident;
use App\Domain\Resident\Services\ResidentStatusStateMachine;
use Illuminate\Support\Carbon;

final class RecordResidentStatusAction
{
    public function __construct(
        private readonly ResidentStatusStateMachine $statusStateMachine,
    ) {}

    public function execute(
        string $residentId,
        ResidentStatus $newStatus,
        ?string $reason = null,
        ?string $referenceLetterNumber = null,
        ?string $attachmentFileId = null,
        ?User $actor = null,
        ?Carbon $effectiveAt = null
    ): Resident {
        /** @var Resident $resident */
        $resident = Resident::where('id', $residentId)->firstOrFail();

        return $this->statusStateMachine->transition(
            resident: $resident,
            newStatus: $newStatus,
            reason: $reason,
            referenceLetterNumber: $referenceLetterNumber,
            attachmentFileId: $attachmentFileId,
            actor: $actor,
            effectiveAt: $effectiveAt
        );
    }
}
