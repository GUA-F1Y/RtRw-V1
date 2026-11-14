<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Models;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Enums\ScopeLevel;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * System reference data. Created by the seeder only, never by user input.
 */
#[Fillable(['key', 'name', 'scope_level', 'description'])]
#[UseFactory(RoleFactory::class)]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'scope_level' => ScopeLevel::class,
        ];
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * In-memory check on the (eager loaded) permission collection to avoid queries
     * inside authorization loops.
     */
    public function hasPermission(PermissionKey $permission): bool
    {
        return $this->permissions->contains('key', $permission->value);
    }
}
