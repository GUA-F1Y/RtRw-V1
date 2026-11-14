<?php

declare(strict_types=1);

namespace App\Domain\Finance\DTOs;

final readonly class ApproveExpenditureInput
{
    public function __construct(
        public string $requestId,
        public ?int $approvedAmount = null,
        public ?string $decisionReason = null,
    ) {}
}
