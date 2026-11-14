<?php

declare(strict_types=1);

namespace App\Domain\Household\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class HouseholdStateConflict extends ApiException
{
    public function __construct(string $message, string $errorCode = 'household_state_conflict')
    {
        parent::__construct($message, 409, $errorCode);
    }
}
