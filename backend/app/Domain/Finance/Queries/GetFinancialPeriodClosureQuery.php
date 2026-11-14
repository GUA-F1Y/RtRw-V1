<?php

declare(strict_types=1);

namespace App\Domain\Finance\Queries;

use App\Domain\Finance\Models\FinancialPeriodClosure;

final class GetFinancialPeriodClosureQuery
{
    public function execute(FinancialPeriodClosure $closure): FinancialPeriodClosure
    {
        return $closure->loadMissing([
            'account',
            'closedBy:id,name,email',
            'organization',
            'rw',
            'rt',
        ]);
    }
}
