<?php

declare(strict_types=1);

namespace App\Domain\Finance\Queries;

use App\Domain\Finance\Models\ExpenditureRequest;

final class GetExpenditureRequestQuery
{
    public function execute(ExpenditureRequest $request): ExpenditureRequest
    {
        return $request->loadMissing([
            'budgetLine.category',
            'budgetLine.budgetPlan',
            'cashAccount',
            'category',
            'requester:id,name,email',
            'approver:id,name,email',
            'poster:id,name,email',
            'financialTransaction',
            'evidenceFile',
            'rw:id,number,name',
            'rt:id,number,name',
        ]);
    }
}
