<?php

declare(strict_types=1);

namespace App\Domain\Finance\DTOs;

final readonly class RecordCashTransferInput
{
    public function __construct(
        public string $sourceCashAccountId,
        public string $destinationCashAccountId,
        public int $amount,
        public string $transferDate,
        public string $description,
        public ?string $referenceNumber = null,
        public ?string $evidenceFileId = null,
    ) {}
}
