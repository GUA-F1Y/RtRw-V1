<?php

declare(strict_types=1);

namespace App\Domain\Finance\Enums;

enum CashAccountStatus: string
{
    case Active = 'ACTIVE';
    case Closed = 'CLOSED';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Closed => 'Ditutup',
        };
    }

    public function isOperable(): bool
    {
        return $this === self::Active;
    }
}
