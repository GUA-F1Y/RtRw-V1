<?php

declare(strict_types=1);

namespace App\Domain\Resident\Enums;

enum ResidentStatus: string
{
    case Prospective = 'PROSPECTIVE';
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
    case Moved = 'MOVED';
    case Deceased = 'DECEASED';
}
