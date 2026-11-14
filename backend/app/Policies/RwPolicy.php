<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Rw;

final class RwPolicy
{
    public function __construct(private readonly AccessResolver $access) {}

    public function view(User $user, Rw $rw): bool
    {
        if ($this->access->allows($user, PermissionKey::RwView, $rw->scopeRef())) {
            return true;
        }

        foreach ($this->access->scopesGranting($user, PermissionKey::RtView) as $scope) {
            if ($scope->organizationId === $rw->organization_id && $scope->rwId === $rw->id) {
                return true;
            }
        }

        return false;
    }

    public function manage(User $user, Rw $rw): bool
    {
        return $this->access->allows($user, PermissionKey::RwManage, $rw->scopeRef());
    }

    public function createRt(User $user, Rw $rw): bool
    {
        return $this->access->allows($user, PermissionKey::RtManage, $rw->scopeRef());
    }
}
