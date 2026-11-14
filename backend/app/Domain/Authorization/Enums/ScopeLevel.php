<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Enums;

/**
 * The organizational level at which a role can be granted and a membership applies.
 */
enum ScopeLevel: string
{
    case Organization = 'organization';
    case Rw = 'rw';
    case Rt = 'rt';
}
