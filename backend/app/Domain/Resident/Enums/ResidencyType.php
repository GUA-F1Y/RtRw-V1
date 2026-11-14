<?php

declare(strict_types=1);

namespace App\Domain\Resident\Enums;

enum ResidencyType: string
{
    case Permanent = 'PERMANENT';
    case Temporary = 'TEMPORARY';
    case Seasonal = 'SEASONAL';
}
