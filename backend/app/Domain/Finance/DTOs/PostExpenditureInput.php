<?php

declare(strict_types=1);

namespace App\Domain\Finance\DTOs;

final readonly class PostExpenditureInput
{
    public function __construct(
        public string $requestId,
        public ?int $actualAmount = null,
        public ?string $transactionDate = null,
        public ?string $cashAccountId = null,
        public ?string $referenceNumber = null,
        public ?string $evidenceFileId = null,
    ) {}
}
