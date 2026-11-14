<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class CategoryTypeMismatchException extends ApiException
{
    public function __construct(string $message = 'Category type does not match transaction type.', string $errorCode = 'category_type_mismatch')
    {
        parent::__construct($message, 422, $errorCode);
    }
}
