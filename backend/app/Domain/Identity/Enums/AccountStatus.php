<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum AccountStatus: string
{
    /** Invited but not yet able to use the system. */
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
}
