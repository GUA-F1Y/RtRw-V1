<?php

declare(strict_types=1);

namespace App\Domain\Finance\Queries;

use App\Domain\Finance\Models\CashAccount;

final class GetCashAccountQuery
{
    public function execute(CashAccount $account): CashAccount
    {
        return $account->loadMissing(['rw', 'rt']);
    }
}
