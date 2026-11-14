<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class TransferAlreadyReversedException extends ApiException
{
    public function __construct(
        string $message = 'This cash transfer has already been reversed and cannot be reversed again.',
        string $errorCode = 'transfer_already_reversed',
        array $errors = []
    ) {
        parent::__construct($message, 409, $errorCode, $errors);
    }
}
