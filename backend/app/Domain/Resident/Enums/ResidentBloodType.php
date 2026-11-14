<?php

declare(strict_types=1);

namespace App\Domain\Resident\Enums;

enum ResidentBloodType: string
{
    case A = 'A';
    case B = 'B';
    case Ab = 'AB';
    case O = 'O';
    case APos = 'A_POS';
    case ANeg = 'A_NEG';
    case BPos = 'B_POS';
    case BNeg = 'B_NEG';
    case AbPos = 'AB_POS';
    case AbNeg = 'AB_NEG';
    case OPos = 'O_POS';
    case ONeg = 'O_NEG';
    case Unknown = 'UNKNOWN';
}
