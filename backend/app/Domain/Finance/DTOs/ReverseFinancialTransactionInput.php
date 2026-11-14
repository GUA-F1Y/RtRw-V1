<?php

declare(strict_types=1);

namespace App\Domain\Finance\DTOs;

final readonly class ReverseFinancialTransactionInput
{
    public function __construct(
        public string $transactionId,
        public string $reversalReason,
        public ?string $transactionDate = null,
    ) {}
}
