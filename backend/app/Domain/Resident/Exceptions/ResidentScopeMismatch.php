<?php

declare(strict_types=1);

namespace App\Domain\Resident\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class ResidentScopeMismatch extends ApiException
{
    public function __construct(string $message, string $errorCode = 'resident_scope_mismatch')
    {
        parent::__construct($message, 422, $errorCode);
    }
}
