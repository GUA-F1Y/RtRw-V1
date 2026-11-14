<?php

declare(strict_types=1);

namespace App\Domain\Overview\DTOs;

final readonly class OverviewScopeDto
{
    public function __construct(
        public string $level,
        public string $id,
        public string $name,
    ) {}
}
