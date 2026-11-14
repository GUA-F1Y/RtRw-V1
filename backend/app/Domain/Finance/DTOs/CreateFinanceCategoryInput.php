<?php

declare(strict_types=1);

namespace App\Domain\Finance\DTOs;

use App\Domain\Finance\Enums\TransactionType;

final readonly class CreateFinanceCategoryInput
{
    public function __construct(
        public string $name,
        public TransactionType $type,
        public ?string $rwId = null,
        public ?string $rtId = null,
        public ?string $code = null,
        public ?string $description = null,
    ) {}
}
