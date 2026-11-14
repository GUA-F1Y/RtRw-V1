<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class TransactionAlreadyReversedException extends ApiException
{
    public function __construct(string $message = 'Transaction has already been reversed.', string $errorCode = 'transaction_already_reversed')
    {
        parent::__construct($message, 409, $errorCode);
    }
}
