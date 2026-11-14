<?php

declare(strict_types=1);

namespace App\Domain\Household\DTOs;

final readonly class CreateHouseholdInput
{
    public function __construct(
        public string $kkNumber,
        public string $address,
        public string $rtId,
        public ?string $notes = null,
        public ?string $initialHeadResidentId = null,
    ) {}
}
