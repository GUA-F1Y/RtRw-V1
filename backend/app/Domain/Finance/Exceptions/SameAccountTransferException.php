<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class SameAccountTransferException extends ApiException
{
    public function __construct(
        string $message = 'Source and destination cash accounts must be different.',
        string $errorCode = 'same_account_transfer',
        array $errors = []
    ) {
        parent::__construct($message, 422, $errorCode, $errors);
    }
}
