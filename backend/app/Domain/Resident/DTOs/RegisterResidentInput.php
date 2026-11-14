<?php

declare(strict_types=1);

namespace App\Domain\Resident\DTOs;

use App\Domain\Resident\Enums\ResidentBloodType;
use App\Domain\Resident\Enums\ResidentEducation;
use App\Domain\Resident\Enums\ResidentGender;
use App\Domain\Resident\Enums\ResidentMaritalStatus;
use App\Domain\Resident\Enums\ResidentStatus;
use App\Domain\Resident\Enums\ResidencyType;

final readonly class RegisterResidentInput
{
    public function __construct(
        public string $nik,
        public string $name,
        public ResidentGender $gender,
        public string $birthPlace,
        public string $birthDate,
        public string $religion,
        public ResidentMaritalStatus $maritalStatus,
        public string $organizationId,
        public string $rwId,
        public string $rtId,
        public ResidentBloodType $bloodType = ResidentBloodType::Unknown,
        public ?string $occupation = null,
        public ResidentEducation $education = ResidentEducation::None,
        public ?string $phone = null,
        public ResidencyType $residencyType = ResidencyType::Permanent,
        public ResidentStatus $status = ResidentStatus::Prospective,
        public ?string $userId = null,
    ) {}
}
