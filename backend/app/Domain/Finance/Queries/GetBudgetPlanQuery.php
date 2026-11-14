<?php

declare(strict_types=1);

namespace App\Domain\Finance\Queries;

use App\Domain\Finance\Models\BudgetPlan;

final class GetBudgetPlanQuery
{
    public function execute(BudgetPlan $plan): BudgetPlan
    {
        return $plan->loadMissing([
            'lines.category',
            'creator:id,name,email',
            'publisher:id,name,email',
            'rw:id,number,name',
            'rt:id,number,name',
        ]);
    }
}
