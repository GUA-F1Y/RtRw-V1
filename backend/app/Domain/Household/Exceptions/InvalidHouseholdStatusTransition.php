<?php

declare(strict_types=1);

namespace App\Domain\Household\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class InvalidHouseholdStatusTransition extends ApiException
{
    public function __construct(string $from, string $to, string $message = '')
    {
        $defaultMessage = $message !== '' ? $message : "Cannot transition household status from {$from} to {$to}.";
        parent::__construct($defaultMessage, 422, 'invalid_household_status_transition');
    }
}
