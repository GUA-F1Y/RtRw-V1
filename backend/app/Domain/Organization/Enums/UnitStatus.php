<?php

declare(strict_types=1);

namespace App\Domain\Organization\Enums;

enum UnitStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
