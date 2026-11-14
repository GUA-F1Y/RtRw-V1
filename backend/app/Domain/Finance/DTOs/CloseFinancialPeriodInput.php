<?php

declare(strict_types=1);

namespace App\Domain\Finance\DTOs;

final readonly class CloseFinancialPeriodInput
{
    public function __construct(
        public string $cashAccountId,
        public int $year,
        public int $month,
        public ?int $countedCashBalance = null,
        public ?string $reconciliationNotes = null,
    ) {}
}
