<?php

declare(strict_types=1);

namespace App\Domain\Overview\DTOs;

final readonly class RtOverviewMetricDto
{
    public function __construct(
        public string $rtId,
        public string $rtCode,
        public int $residentCount,
        public int $householdCount,
        public int $headVacantHouseholdCount,
    ) {}
}
