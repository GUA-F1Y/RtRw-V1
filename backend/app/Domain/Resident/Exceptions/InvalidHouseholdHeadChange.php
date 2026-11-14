<?php

declare(strict_types=1);

namespace App\Domain\Resident\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class InvalidHouseholdHeadChange extends ApiException
{
    public function __construct(string $message, string $errorCode = 'invalid_household_head_change')
    {
        parent::__construct($message, 422, $errorCode);
    }
}
