<?php

declare(strict_types=1);

namespace App\Domain\Household\Enums;

enum HouseholdStatus: string
{
    case Active = 'ACTIVE';
    case HeadVacant = 'HEAD_VACANT';
    case Moved = 'MOVED';
    case Dissolved = 'DISSOLVED';
    case Archived = 'ARCHIVED';
}
