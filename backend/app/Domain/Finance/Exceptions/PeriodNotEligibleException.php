<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class PeriodNotEligibleException extends ApiException
{
    public function __construct(
        string $message = 'Only completed calendar months prior to the current month can be closed.',
        string $errorCode = 'period_not_eligible',
        array $errors = []
    ) {
        parent::__construct($message, 422, $errorCode, $errors);
    }
}
