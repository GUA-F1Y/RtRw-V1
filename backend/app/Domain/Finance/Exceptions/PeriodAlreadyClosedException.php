<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class PeriodAlreadyClosedException extends ApiException
{
    public function __construct(
        string $message = 'This financial period has already been closed for this cash account.',
        string $errorCode = 'period_already_closed',
        array $errors = []
    ) {
        parent::__construct($message, 409, $errorCode, $errors);
    }
}
