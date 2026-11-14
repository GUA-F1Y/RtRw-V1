<?php

declare(strict_types=1);

namespace App\Domain\Resident\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class InvalidResidentStatusTransition extends ApiException
{
    public function __construct(string $from, string $to, string $message = '')
    {
        $defaultMessage = $message !== '' ? $message : "Cannot transition resident status from {$from} to {$to}.";
        parent::__construct($defaultMessage, 422, 'invalid_resident_status_transition');
    }
}
