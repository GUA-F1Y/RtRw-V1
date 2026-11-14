<?php

declare(strict_types=1);

namespace App\Domain\Resident\DTOs;

use App\Domain\Resident\Enums\ResidencyMovementType;

final readonly class RelocateResidentInput
{
    public function __construct(
        public string $residentId,
        public ResidencyMovementType $movementType,
        public ?string $toRtId = null,
        public ?string $toHouseholdId = null,
        public ?string $effectiveDate = null,
        public ?string $referenceLetterNumber = null,
        public ?string $attachmentFileId = null,
        public ?string $notes = null,
    ) {}
}
