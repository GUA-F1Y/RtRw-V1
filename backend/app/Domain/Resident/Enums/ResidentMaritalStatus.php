<?php

declare(strict_types=1);

namespace App\Domain\Resident\Enums;

enum ResidentMaritalStatus: string
{
    case Single = 'SINGLE';
    case Married = 'MARRIED';
    case Divorced = 'DIVORCED';
    case Widowed = 'WIDOWED';
}
