<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\Authorization\Models\Permission;
use App\Domain\Authorization\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            // Seed permissions
            $permissionModels = [];
            foreach (PermissionKey::cases() as $permissionKey) {
                $permission = Permission::query()->firstOrCreate(
                    ['key' => $permissionKey->value],
                    ['description' => $permissionKey->description()]
                );
                $permissionModels[$permissionKey->value] = $permission;
            }

            // Seed roles and attach permissions
            foreach (RoleKey::cases() as $roleKey) {
                /** @var Role $role */
                $role = Role::query()->firstOrCreate(
                    ['key' => $roleKey->value],
                    [
                        'name' => $roleKey->label(),
                        'scope_level' => $roleKey->scopeLevel(),
                        'description' => sprintf('%s role at %s level', $roleKey->label(), $roleKey->scopeLevel()->value),
                    ]
                );

                $permissionIds = [];
                foreach ($roleKey->permissions() as $perm) {
                    if (isset($permissionModels[$perm->value])) {
                        $permissionIds[] = $permissionModels[$perm->value]->id;
                    }
                }

                $role->permissions()->sync($permissionIds);
            }
        });
    }
}
