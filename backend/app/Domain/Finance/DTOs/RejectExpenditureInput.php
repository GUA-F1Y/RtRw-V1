<?php

declare(strict_types=1);

namespace App\Domain\Finance\DTOs;

final readonly class RejectExpenditureInput
{
    public function __construct(
        public string $requestId,
        public ?string $decisionReason = null,
    ) {}
}
