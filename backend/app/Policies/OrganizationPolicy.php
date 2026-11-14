<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;

final class OrganizationPolicy
{
    public function __construct(private readonly AccessResolver $access) {}

    public function view(User $user, Organization $organization): bool
    {
        if ($this->access->allows($user, PermissionKey::RwView, $organization->scopeRef())) {
            return true;
        }

        foreach ($this->access->scopesGranting($user, PermissionKey::RwView) as $scope) {
            if ($scope->organizationId === $organization->id) {
                return true;
            }
        }

        foreach ($this->access->scopesGranting($user, PermissionKey::RtView) as $scope) {
            if ($scope->organizationId === $organization->id) {
                return true;
            }
        }

        return false;
    }

    public function manage(User $user, Organization $organization): bool
    {
        return $this->access->allows($user, PermissionKey::RwManage, $organization->scopeRef());
    }
}
