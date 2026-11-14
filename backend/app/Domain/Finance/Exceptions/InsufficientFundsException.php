<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class InsufficientFundsException extends ApiException
{
    public function __construct(
        string $message = 'Source cash account has insufficient funds to cover the transfer amount.',
        string $errorCode = 'insufficient_funds',
        array $errors = []
    ) {
        parent::__construct($message, 422, $errorCode, $errors);
    }
}
