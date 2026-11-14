<?php

declare(strict_types=1);

namespace App\Domain\Finance\DTOs;

final readonly class UpdateCashAccountInput
{
    public function __construct(
        public ?string $name = null,
        public ?string $accountNumber = null,
        public ?string $description = null,
    ) {}
}
