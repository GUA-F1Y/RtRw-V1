<?php

declare(strict_types=1);

namespace App\Domain\Resident\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class DuplicateNikException extends ApiException
{
    public function __construct(string $nik, string $message = '')
    {
        $defaultMessage = $message !== '' ? $message : "A resident with NIK '{$nik}' already exists in the system.";
        parent::__construct($defaultMessage, 409, 'duplicate_nik');
    }
}
