<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class BudgetValidationException extends ApiException
{
    public function __construct(string $message = 'Budget validation failed.', string $errorCode = 'budget_validation_failed', int $statusCode = 422)
    {
        parent::__construct($message, $statusCode, $errorCode);
    }
}
