<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $userPermissions = [
            'ViewAny:User',
            'View:User',
            'Create:User',
            'Update:User',
            'Delete:User',
            'DeleteAny:User',
        ];

        $rolePermissions = [
            'ViewAny:Role',
            'View:Role',
            'Create:Role',
            'Update:Role',
            'Delete:Role',
            'DeleteAny:Role',
        ];

        foreach (array_merge($userPermissions, $rolePermissions) as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $roles = [];
        foreach (UserRole::cases() as $roleEnum) {
            $roles[$roleEnum->value] = Role::findOrCreate($roleEnum->value, 'web');
        }

        // Super Admin gets all permissions
        $roles[UserRole::SuperAdmin->value]->syncPermissions(Permission::all());

        // Admin IPSRS can manage user accounts (PRD §3)
        $roles[UserRole::AdminIpsrs->value]->syncPermissions($userPermissions);

        $demoUsers = [
            [
                'name' => 'Super Admin SIGAP',
                'email' => 'superadmin@sigap-alkes.test',
                'role' => UserRole::SuperAdmin,
            ],
            [
                'name' => 'Budi Santoso (Admin IPSRS)',
                'email' => 'admin@sigap-alkes.test',
                'role' => UserRole::AdminIpsrs,
            ],
            [
                'name' => 'Andi Pratama (Teknisi Elektromedis)',
                'email' => 'teknisi@sigap-alkes.test',
                'role' => UserRole::Teknisi,
            ],
            [
                'name' => 'Ns. Siti Aminah (Kepala Ruangan)',
                'email' => 'kepalaruangan@sigap-alkes.test',
                'role' => UserRole::KepalaRuangan,
            ],
            [
                'name' => 'dr. Hendra Wijaya (Manajemen RS)',
                'email' => 'manajemen@sigap-alkes.test',
                'role' => UserRole::Manajemen,
            ],
        ];

        foreach ($demoUsers as $demo) {
            $user = User::updateOrCreate(
                ['email' => $demo['email']],
                [
                    'name' => $demo['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$demo['role']->value]);
        }
    }
}
