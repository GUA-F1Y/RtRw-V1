<?php

declare(strict_types=1);

namespace App\Domain\Finance\DTOs;

final readonly class CreateExpenditureRequestInput
{
    public function __construct(
        public string $budgetLineId,
        public string $cashAccountId,
        public int $proposedAmount,
        public string $plannedDate,
        public string $title,
        public ?string $description = null,
        public ?string $referenceNumber = null,
        public ?string $evidenceFileId = null,
        public bool $submit = true,
    ) {}
}
