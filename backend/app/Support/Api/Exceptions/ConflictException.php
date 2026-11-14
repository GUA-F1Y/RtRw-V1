<?php

declare(strict_types=1);

namespace App\Support\Api\Exceptions;

/** 409: the request is valid but conflicts with current state (e.g. duplicate). */
final class ConflictException extends ApiException
{
    public function __construct(string $message = 'The request conflicts with the current state of the resource.')
    {
        parent::__construct($message, 409, 'conflict');
    }
}
