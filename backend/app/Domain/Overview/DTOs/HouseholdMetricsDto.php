<?php

declare(strict_types=1);

namespace App\Domain\Overview\DTOs;

final readonly class HouseholdMetricsDto
{
    public function __construct(
        public int $active,
        public int $headAssigned,
        public int $headVacant,
        public int $moved,
        public int $dissolved,
    ) {}
}
