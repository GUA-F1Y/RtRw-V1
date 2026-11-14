<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class FinanceScopeMismatch extends ApiException
{
    public function __construct(string $message, string $errorCode = 'finance_scope_mismatch')
    {
        parent::__construct($message, 422, $errorCode);
    }
}
