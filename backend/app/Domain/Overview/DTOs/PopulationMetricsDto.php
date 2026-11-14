<?php

declare(strict_types=1);

namespace App\Domain\Overview\DTOs;

final readonly class PopulationMetricsDto
{
    public function __construct(
        public int $total,
        public int $active,
        public int $prospective,
        public int $inactive,
        public int $moved,
        public int $deceased,
    ) {}
}
