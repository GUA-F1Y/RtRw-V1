<?php

declare(strict_types=1);

namespace App\Domain\Finance\DTOs;

use App\Domain\Finance\Enums\TransactionType;

final readonly class RecordFinancialTransactionInput
{
    public function __construct(
        public string $cashAccountId,
        public string $categoryId,
        public TransactionType $type,
        public int $amount,
        public string $transactionDate,
        public string $description,
        public ?string $referenceNumber = null,
        public ?string $evidenceFileId = null,
    ) {}
}
