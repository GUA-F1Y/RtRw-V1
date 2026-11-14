<?php

declare(strict_types=1);

namespace App\Domain\Resident\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class HouseholdMembershipConflict extends ApiException
{
    public function __construct(string $message, string $errorCode = 'household_membership_conflict')
    {
        parent::__construct($message, 409, $errorCode);
    }
}
