<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            /*
             * -------------------------------------------------------------
             * Demo organization
             * -------------------------------------------------------------
             */
            $organization = Organization::query()->updateOrCreate(
                ['slug' => 'demo-rtrw'],
                [
                    'name' => 'Kompleks Demo RT/RW',
                ]
            );

            /*
             * -------------------------------------------------------------
             * Demo RW
             * -------------------------------------------------------------
             */
            $rw01 = Rw::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'number' => 1,
                ],
                [
                    'name' => 'RW 01',
                ]
            );

            $rw02 = Rw::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'number' => 2,
                ],
                [
                    'name' => 'RW 02',
                ]
            );

            /*
             * -------------------------------------------------------------
             * Demo RT
             * -------------------------------------------------------------
             */
            $rt01 = Rt::query()->updateOrCreate(
                [
                    'rw_id' => $rw01->id,
                    'number' => 1,
                ],
                [
                    'name' => 'RT 01',
                ]
            );

            $rt02 = Rt::query()->updateOrCreate(
                [
                    'rw_id' => $rw01->id,
                    'number' => 2,
                ],
                [
                    'name' => 'RT 02',
                ]
            );

            $rt03 = Rt::query()->updateOrCreate(
                [
                    'rw_id' => $rw02->id,
                    'number' => 1,
                ],
                [
                    'name' => 'RT 01',
                ]
            );

            /*
             * -------------------------------------------------------------
             * Resolve roles from RoleKey safely
             * -------------------------------------------------------------
             */
            $adminRole = $this->resolveRole('Organization Admin');
            $ketuaRwRole = $this->resolveRole('Ketua RW');
            $sekretarisRwRole = $this->resolveRole('Sekretaris RW');
            $bendaharaRwRole = $this->resolveRole('Bendahara RW');
            $ketuaRtRole = $this->resolveRole('Ketua RT');
            $sekretarisRtRole = $this->resolveRole('Sekretaris RT');
            $bendaharaRtRole = $this->resolveRole('Bendahara RT');

            /*
             * -------------------------------------------------------------
             * Organization Admin
             * -------------------------------------------------------------
             */
            $admin = $this->user(
                name: 'Organization Admin',
                email: 'admin@rtrw.test',
                password: 'Admin@12345',
            );

            $this->membership(
                user: $admin,
                role: $adminRole,
                organizationId: $organization->id,
            );

            /*
             * -------------------------------------------------------------
             * RW 01 officers
             * -------------------------------------------------------------
             */
            $ketuaRw = $this->user(
                name: 'Ketua RW 01',
                email: 'ketua.rw01@rtrw.test',
                password: 'Rw01@12345',
            );

            $this->membership(
                user: $ketuaRw,
                role: $ketuaRwRole,
                organizationId: $organization->id,
                rwId: $rw01->id,
            );

            $sekretarisRw = $this->user(
                name: 'Sekretaris RW 01',
                email: 'sekretaris.rw01@rtrw.test',
                password: 'Rw01@12345',
            );

            $this->membership(
                user: $sekretarisRw,
                role: $sekretarisRwRole,
                organizationId: $organization->id,
                rwId: $rw01->id,
            );

            $bendaharaRw = $this->user(
                name: 'Bendahara RW 01',
                email: 'bendahara.rw01@rtrw.test',
                password: 'Rw01@12345',
            );

            $this->membership(
                user: $bendaharaRw,
                role: $bendaharaRwRole,
                organizationId: $organization->id,
                rwId: $rw01->id,
            );

            /*
             * -------------------------------------------------------------
             * RT 01 officers
             * -------------------------------------------------------------
             */
            $ketuaRt = $this->user(
                name: 'Ketua RT 01 / RW 01',
                email: 'ketua.rt01.rw01@rtrw.test',
                password: 'Rt01@12345',
            );

            $this->membership(
                user: $ketuaRt,
                role: $ketuaRtRole,
                organizationId: $organization->id,
                rwId: $rw01->id,
                rtId: $rt01->id,
            );

            $sekretarisRt = $this->user(
                name: 'Sekretaris RT 01 / RW 01',
                email: 'sekretaris.rt01.rw01@rtrw.test',
                password: 'Rt01@12345',
            );

            $this->membership(
                user: $sekretarisRt,
                role: $sekretarisRtRole,
                organizationId: $organization->id,
                rwId: $rw01->id,
                rtId: $rt01->id,
            );

            $bendaharaRt = $this->user(
                name: 'Bendahara RT 01 / RW 01',
                email: 'bendahara.rt01.rw01@rtrw.test',
                password: 'Rt01@12345',
            );

            $this->membership(
                user: $bendaharaRt,
                role: $bendaharaRtRole,
                organizationId: $organization->id,
                rwId: $rw01->id,
                rtId: $rt01->id,
            );

            /*
             * -------------------------------------------------------------
             * Output
             * -------------------------------------------------------------
             */
            $this->command?->info('Demo organization seeded.');
            $this->command?->info('RW 01 + RT 01/02 and RW 02 + RT 01 created.');
            $this->command?->info('7 demo login accounts created/verified.');
        });
    }

    private function user(
        string $name,
        string $email,
        string $password,
    ): User {
        $user = User::query()->firstOrNew([
            'email' => mb_strtolower($email),
        ]);

        $user->name = $name;
        $user->status = 'active';

        /*
         * Jangan reset password setiap kali db:seed dijalankan.
         * Password hanya dibuat saat account pertama kali dibuat.
         */
        if (! $user->exists) {
            $user->password = Hash::make($password);
        }

        $user->save();

        return $user;
    }

    private function membership(
        User $user,
        Role $role,
        string $organizationId,
        ?string $rwId = null,
        ?string $rtId = null,
    ): Membership {
        return Membership::query()
            ->where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->where('organization_id', $organizationId)
            ->where('rw_id', $rwId)
            ->where('rt_id', $rtId)
            ->whereNull('revoked_at')
            ->firstOrCreate([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'scope_level' => $role->scope_level,
                'organization_id' => $organizationId,
                'rw_id' => $rwId,
                'rt_id' => $rtId,
            ]);
    }

    private function resolveRole(string $label): Role
    {
        $wanted = $this->normalize($label);

        foreach (RoleKey::cases() as $roleKey) {
            $candidates = [
                $roleKey->name,
                $roleKey->value,
                $roleKey->label(),
            ];

            foreach ($candidates as $candidate) {
                if ($this->normalize($candidate) === $wanted) {
                    return Role::query()
                        ->where('key', $roleKey->value)
                        ->firstOrFail();
                }
            }
        }

        throw new RuntimeException(
            sprintf('Role "%s" tidak ditemukan di RoleKey.', $label)
        );
    }

    private function normalize(string $value): string
    {
        return strtolower(
            preg_replace('/[^a-z0-9]+/i', '', $value) ?? ''
        );
    }
}