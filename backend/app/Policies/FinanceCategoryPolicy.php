<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\Finance\Models\FinanceCategory;
use App\Domain\Identity\Models\User;

final class FinanceCategoryPolicy
{
    public function __construct(
        private readonly AccessResolver $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->scopesGranting($user, PermissionKey::FinanceAccountView) !== []
            || $this->access->scopesGranting($user, PermissionKey::FinanceTransactionView) !== [];
    }

    public function view(User $user, FinanceCategory $category): bool
    {
        return $this->access->allows($user, PermissionKey::FinanceAccountView, $category->scopeRef())
            || $this->access->allows($user, PermissionKey::FinanceTransactionView, $category->scopeRef());
    }

    public function create(User $user, ScopeRef|FinanceCategory|null $target = null): bool
    {
        if ($target === null) {
            return false;
        }

        $scope = $target instanceof FinanceCategory ? $target->scopeRef() : $target;

        return $this->access->allows($user, PermissionKey::FinanceCategoryManage, $scope);
    }

    public function update(User $user, FinanceCategory $category): bool
    {
        return $this->access->allows($user, PermissionKey::FinanceCategoryManage, $category->scopeRef());
    }
}
