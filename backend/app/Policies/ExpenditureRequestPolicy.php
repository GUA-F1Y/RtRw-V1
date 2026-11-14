<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\Finance\Models\ExpenditureRequest;
use App\Domain\Identity\Models\User;

final class ExpenditureRequestPolicy
{
    public function __construct(
        private readonly AccessResolver $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->scopesGranting($user, PermissionKey::FinanceExpenditureView) !== [];
    }

    public function view(User $user, ExpenditureRequest $request): bool
    {
        return $this->access->allows($user, PermissionKey::FinanceExpenditureView, $request->scopeRef());
    }

    public function create(User $user, ScopeRef|ExpenditureRequest|null $target = null): bool
    {
        if ($target === null) {
            return $this->access->scopesGranting($user, PermissionKey::FinanceExpenditureRequest) !== [];
        }

        $scope = $target instanceof ExpenditureRequest ? $target->scopeRef() : $target;

        return $this->access->allows($user, PermissionKey::FinanceExpenditureRequest, $scope);
    }

    public function submit(User $user, ExpenditureRequest $request): bool
    {
        return $this->access->allows($user, PermissionKey::FinanceExpenditureRequest, $request->scopeRef());
    }

    public function approve(User $user, ExpenditureRequest $request): bool
    {
        // Enforce segregation of duties
        if ($request->requested_by === $user->id) {
            return false;
        }

        return $this->access->allows($user, PermissionKey::FinanceExpenditureApprove, $request->scopeRef());
    }

    public function reject(User $user, ExpenditureRequest $request): bool
    {
        // Enforce segregation of duties
        if ($request->requested_by === $user->id) {
            return false;
        }

        return $this->access->allows($user, PermissionKey::FinanceExpenditureApprove, $request->scopeRef());
    }

    public function cancel(User $user, ExpenditureRequest $request): bool
    {
        if ($request->requested_by === $user->id) {
            return true;
        }

        return $this->access->allows($user, PermissionKey::FinanceExpenditureApprove, $request->scopeRef());
    }

    public function post(User $user, ExpenditureRequest $request): bool
    {
        return $this->access->allows($user, PermissionKey::FinanceExpenditurePost, $request->scopeRef());
    }
}
