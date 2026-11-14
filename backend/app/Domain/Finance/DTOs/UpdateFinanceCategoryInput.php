<?php

declare(strict_types=1);

namespace App\Domain\Finance\DTOs;

final readonly class UpdateFinanceCategoryInput
{
    public function __construct(
        public ?string $name = null,
        public ?string $code = null,
        public ?string $description = null,
        public ?bool $isActive = null,
    ) {}
}
