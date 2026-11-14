<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class InsufficientBudgetException extends ApiException
{
    public function __construct(string $message = 'Available budget is insufficient for this expenditure.', string $errorCode = 'insufficient_budget')
    {
        parent::__construct($message, 409, $errorCode);
    }
}
