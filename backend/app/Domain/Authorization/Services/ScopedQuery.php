<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Services;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Enums\ScopeLevel;
use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Restricts list queries to the rows a user may see, using the same grants the
 * policies use, so a list endpoint can never leak what a detail endpoint would deny.
 *
 * Each model tells how its rows map to the hierarchy via $columns:
 *   'organization' | 'rw' | 'rt' => column name, a Closure(Builder, string $id), or
 *   null when that scope level can never match the model.
 */
final class ScopedQuery
{
    public function __construct(private readonly AccessResolver $access) {}

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array{organization: string|Closure|null, rw: string|Closure|null, rt: string|Closure|null}  $columns
     * @return Builder<TModel>
     */
    public function constrain(Builder $query, User $user, PermissionKey $permission, array $columns): Builder
    {
        $scopes = $this->access->scopesGranting($user, $permission);

        return $query->where(function (Builder $outer) use ($scopes, $columns): void {
            $matched = false;

            foreach ($scopes as $scope) {
                [$key, $id] = match ($scope->level()) {
                    ScopeLevel::Organization => ['organization', $scope->organizationId],
                    ScopeLevel::Rw => ['rw', $scope->rwId],
                    ScopeLevel::Rt => ['rt', $scope->rtId],
                };

                $target = $columns[$key];

                if ($target === null) {
                    continue;
                }

                $matched = true;

                if ($target instanceof Closure) {
                    $outer->orWhere(fn (Builder $q) => $target($q, $id));
                } else {
                    $outer->orWhere($target, $id);
                }
            }

            if (! $matched) {
                // No applicable grant: match nothing.
                $outer->whereRaw('1 = 0');
            }
        });
    }
}
