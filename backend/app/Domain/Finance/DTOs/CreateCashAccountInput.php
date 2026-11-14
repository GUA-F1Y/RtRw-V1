<?php

declare(strict_types=1);

namespace App\Domain\Finance\DTOs;

final readonly class CreateCashAccountInput
{
    public function __construct(
        public string $rwId,
        public string $name,
        public ?string $rtId = null,
        public ?string $accountNumber = null,
        public ?string $description = null,
        public int $openingBalance = 0,
    ) {}
}
