<?php

declare(strict_types=1);

namespace App\Domain\Finance\DTOs;

final readonly class CancelExpenditureInput
{
    public function __construct(
        public string $requestId,
        public ?string $cancellationReason = null,
    ) {}
}
