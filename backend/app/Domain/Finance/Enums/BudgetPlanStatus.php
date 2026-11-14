<?php

declare(strict_types=1);

namespace App\Domain\Finance\Enums;

enum BudgetPlanStatus: string
{
    case Draft = 'DRAFT';
    case Published = 'PUBLISHED';
    case Superseded = 'SUPERSEDED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Published => 'Diterbitkan',
            self::Superseded => 'Digantikan',
        };
    }
}
