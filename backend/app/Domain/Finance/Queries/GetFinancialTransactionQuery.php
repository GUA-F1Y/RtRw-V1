<?php

declare(strict_types=1);

namespace App\Domain\Finance\Queries;

use App\Domain\Finance\Models\FinancialTransaction;

final class GetFinancialTransactionQuery
{
    public function execute(FinancialTransaction $transaction): FinancialTransaction
    {
        return $transaction->loadMissing([
            'account',
            'category',
            'recorder:id,name,email',
            'evidenceFile',
            'attachments.file',
            'reversal:id,original_transaction_id,amount,transaction_date,reversal_reason,created_at',
            'originalTransaction:id,type,amount,transaction_date,description',
        ]);
    }
}
