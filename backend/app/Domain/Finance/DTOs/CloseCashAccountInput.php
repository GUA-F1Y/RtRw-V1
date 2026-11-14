<?php

declare(strict_types=1);

namespace App\Domain\Finance\DTOs;

final readonly class CloseCashAccountInput
{
    public function __construct(
        public string $reason,
    ) {}
}
