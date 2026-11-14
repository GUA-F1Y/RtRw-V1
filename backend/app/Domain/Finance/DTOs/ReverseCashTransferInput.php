<?php

declare(strict_types=1);

namespace App\Domain\Finance\DTOs;

final readonly class ReverseCashTransferInput
{
    public function __construct(
        public string $transferId,
        public string $reversalReason,
        public ?string $transferDate = null,
    ) {}
}
