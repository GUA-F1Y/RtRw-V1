<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Rt;

final class RtPolicy
{
    public function __construct(private readonly AccessResolver $access) {}

    public function view(User $user, Rt $rt): bool
    {
        return $this->access->allows($user, PermissionKey::RtView, $rt->scopeRef());
    }

    public function manage(User $user, Rt $rt): bool
    {
        return $this->access->allows($user, PermissionKey::RtManage, $rt->scopeRef());
    }
}
