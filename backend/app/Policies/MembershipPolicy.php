<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Membership;

final class MembershipPolicy
{
    public function __construct(private readonly AccessResolver $access) {}

    public function view(User $user, Membership $membership): bool
    {
        return $this->access->allows($user, PermissionKey::MembershipView, $membership->scopeRef());
    }

    public function manage(User $user, Membership $membership): bool
    {
        return $this->access->allows($user, PermissionKey::MembershipManage, $membership->scopeRef());
    }
}
