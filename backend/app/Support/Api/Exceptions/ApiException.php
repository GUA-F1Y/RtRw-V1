<?php

declare(strict_types=1);

namespace App\Support\Api\Exceptions;

use RuntimeException;

/**
 * An expected, client-facing failure. Rendered as the standard error envelope and
 * never reported as a server error.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, string>  $headers
     */
    public function __construct(
        string $message,
        public readonly int $status = 400,
        public readonly string $errorCode = 'bad_request',
        public readonly array $errors = [],
        public readonly array $headers = [],
    ) {
        parent::__construct($message);
    }
}
