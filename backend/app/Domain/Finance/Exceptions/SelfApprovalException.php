<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class SelfApprovalException extends ApiException
{
    public function __construct(string $message = 'Requesters are strictly forbidden from approving their own expenditure proposals.', string $errorCode = 'self_approval_denied')
    {
        parent::__construct($message, 403, $errorCode);
    }
}
