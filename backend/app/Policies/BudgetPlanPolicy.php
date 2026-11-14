<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\Finance\Models\BudgetPlan;
use App\Domain\Identity\Models\User;

final class BudgetPlanPolicy
{
    public function __construct(
        private readonly AccessResolver $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->scopesGranting($user, PermissionKey::FinanceBudgetView) !== [];
    }

    public function view(User $user, BudgetPlan $plan): bool
    {
        return $this->access->allows($user, PermissionKey::FinanceBudgetView, $plan->scopeRef());
    }

    public function create(User $user, ScopeRef|BudgetPlan|null $target = null): bool
    {
        if ($target === null) {
            return $this->access->scopesGranting($user, PermissionKey::FinanceBudgetManage) !== [];
        }

        $scope = $target instanceof BudgetPlan ? $target->scopeRef() : $target;

        return $this->access->allows($user, PermissionKey::FinanceBudgetManage, $scope);
    }

    public function update(User $user, BudgetPlan $plan): bool
    {
        return $this->access->allows($user, PermissionKey::FinanceBudgetManage, $plan->scopeRef());
    }

    public function publish(User $user, BudgetPlan $plan): bool
    {
        return $this->access->allows($user, PermissionKey::FinanceBudgetPublish, $plan->scopeRef());
    }

    public function amend(User $user, BudgetPlan $plan): bool
    {
        return $this->access->allows($user, PermissionKey::FinanceBudgetPublish, $plan->scopeRef());
    }
}
