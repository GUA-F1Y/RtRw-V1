<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class InvalidTransferException extends ApiException
{
    public function __construct(
        string $message = 'Invalid cash transfer operation.',
        string $errorCode = 'invalid_transfer',
        array $errors = []
    ) {
        parent::__construct($message, 422, $errorCode, $errors);
    }
}
