<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use App\Support\Api\Exceptions\ApiException;

final class PeriodReconciliationException extends ApiException
{
    public function __construct(
        string $message = 'A discrepancy between counted physical cash and ledger closing balance requires an explanation in reconciliation notes.',
        string $errorCode = 'reconciliation_discrepancy_explanation_required',
        array $errors = []
    ) {
        parent::__construct($message, 422, $errorCode, $errors);
    }
}
