<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class ClosedPeriodException extends ApiException
{
    public function __construct(
        string $message = 'Cannot record or reverse transactions in a closed financial accounting period.',
        string $errorCode = 'period_closed',
        array $errors = []
    ) {
        parent::__construct($message, 409, $errorCode, $errors);
    }
}
