<?php

declare(strict_types=1);

namespace App\Domain\Resident\Enums;

enum ResidentGender: string
{
    case Male = 'MALE';
    case Female = 'FEMALE';
}
