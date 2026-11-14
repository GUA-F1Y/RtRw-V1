<?php

declare(strict_types=1);

namespace App\Domain\Finance\Queries;

use App\Domain\Finance\Models\CashTransfer;

final class GetCashTransferQuery
{
    public function execute(CashTransfer $transfer): CashTransfer
    {
        return $transfer->loadMissing([
            'sourceAccount.rt',
            'destinationAccount.rt',
            'recorder:id,name,email',
            'evidenceFile',
            'attachments.file',
            'reversal:id,original_transfer_id,amount,transfer_date,reversal_reason,created_at',
            'originalTransfer:id,amount,transfer_date,description',
        ]);
    }
}
