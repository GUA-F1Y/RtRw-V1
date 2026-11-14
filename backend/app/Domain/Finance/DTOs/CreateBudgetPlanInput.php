<?php

declare(strict_types=1);

namespace App\Domain\Finance\DTOs;

final readonly class CreateBudgetPlanInput
{
    /**
     * @param  list<array{category_id: string, planned_amount: int, notes?: string|null}>  $lines
     */
    public function __construct(
        public int $year,
        public int $month,
        public ?string $rtId = null,
        public ?string $notes = null,
        public array $lines = [],
    ) {}
}
