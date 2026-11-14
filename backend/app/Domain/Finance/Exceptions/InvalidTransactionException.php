<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class InvalidTransactionException extends ApiException
{
    public function __construct(string $message, string $errorCode = 'invalid_transaction')
    {
        parent::__construct($message, 422, $errorCode);
    }
}
