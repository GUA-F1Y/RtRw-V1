<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class InvalidExpenditureRequestException extends ApiException
{
    public function __construct(string $message = 'Invalid expenditure request operation.', string $errorCode = 'invalid_expenditure_request', int $statusCode = 422)
    {
        parent::__construct($message, $statusCode, $errorCode);
    }
}
