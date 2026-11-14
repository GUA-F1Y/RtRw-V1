<?php

declare(strict_types=1);

namespace App\Domain\Finance\DTOs;

final readonly class UpdateBudgetPlanInput
{
    /**
     * @param  list<array{category_id: string, planned_amount: int, notes?: string|null}>  $lines
     */
    public function __construct(
        public ?string $notes = null,
        public array $lines = [],
    ) {}
}
