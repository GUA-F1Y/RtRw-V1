<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Services;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Membership;
use Illuminate\Database\Eloquent\Collection;

/**
 * The single place that answers "may this user do X at scope Y?".
 *
 * Authority comes exclusively from the user's ACTIVE memberships of an ACTIVE
 * account. Controllers never decide this; they delegate to policies, which delegate
 * here. Memberships are loaded once per user per instance (the instance is scoped to
 * the request/job) with their role permissions eager loaded, so deciding many
 * resources does not cause N+1 queries.
 */
final class AccessResolver
{
    /** @var array<string, Collection<int, Membership>> */
    private array $cache = [];

    public function allows(User $user, PermissionKey $permission, ScopeRef $target): bool
    {
        foreach ($this->memberships($user) as $membership) {
            if ($membership->role->hasPermission($permission) && $membership->scopeRef()->covers($target)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scopes at which the user holds $permission (used to filter list queries).
     *
     * @return list<ScopeRef>
     */
    public function scopesGranting(User $user, PermissionKey $permission): array
    {
        $scopes = [];

        foreach ($this->memberships($user) as $membership) {
            if ($membership->role->hasPermission($permission)) {
                $scopes[] = $membership->scopeRef();
            }
        }

        return $scopes;
    }

    /**
     * @return Collection<int, Membership>
     */
    public function memberships(User $user): Collection
    {
        return $this->cache[$user->getKey()] ??= $this->load($user);
    }

    /**
     * Must be called after memberships change within the same request/job.
     */
    public function forget(User $user): void
    {
        unset($this->cache[$user->getKey()]);
    }

    /**
     * Reset all cached memberships across all users (used at request boundaries).
     */
    public function flush(): void
    {
        $this->cache = [];
    }

    /**
     * @return Collection<int, Membership>
     */
    private function load(User $user): Collection
    {
        if (! $user->isActive()) {
            return new Collection;
        }

        return Membership::query()
            ->active()
            ->where('user_id', $user->getKey())
            ->with(['role.permissions', 'organization', 'rw', 'rt'])
            ->get();
    }
}
