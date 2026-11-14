<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class CashAccountClosedException extends ApiException
{
    public function __construct(string $message = 'Cannot perform operations on a closed cash account.', string $errorCode = 'cash_account_closed')
    {
        parent::__construct($message, 422, $errorCode);
    }
}
