<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Room;
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

        $models = [
            'User',
            'Role',
            'Room',
            'EquipmentType',
            'TestingInstitution',
            'SparePart',
            'Equipment',
        ];

        $actions = [
            'ViewAny',
            'View',
            'Create',
            'Update',
            'Delete',
            'DeleteAny',
            'Restore',
            'RestoreAny',
            'ForceDelete',
            'ForceDeleteAny',
            'Replicate',
            'Reorder',
        ];

        foreach ($models as $model) {
            foreach ($actions as $action) {
                Permission::findOrCreate("{$action}:{$model}", 'web');
            }
        }

        $roles = [];
        foreach (UserRole::cases() as $roleEnum) {
            $roles[$roleEnum->value] = Role::findOrCreate($roleEnum->value, 'web');
        }

        // 1. super_admin: Semua hak akses
        $roles[UserRole::SuperAdmin->value]->syncPermissions(Permission::all());

        // 2. admin_ipsrs: CRUD semua data master, alat, akun pengguna; cetak label QR (PRD §3)
        $adminIpsrsModels = ['User', 'Room', 'EquipmentType', 'TestingInstitution', 'SparePart'];
        $adminIpsrsPermissions = [];
        foreach ($adminIpsrsModels as $model) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $action) {
                $adminIpsrsPermissions[] = "{$action}:{$model}";
            }
        }
        foreach (['ViewAny', 'View', 'Create', 'Update'] as $action) {
            $adminIpsrsPermissions[] = "{$action}:Equipment";
        }
        $roles[UserRole::AdminIpsrs->value]->syncPermissions($adminIpsrsPermissions);

        // 3. teknisi: Lihat semua alat (PRD §3)
        $roles[UserRole::Teknisi->value]->syncPermissions([
            'ViewAny:Equipment',
            'View:Equipment',
        ]);

        // 4. kepala_ruangan: Lihat alat di ruangannya saja (PRD §3)
        $roles[UserRole::KepalaRuangan->value]->syncPermissions([
            'ViewAny:Equipment',
            'View:Equipment',
        ]);

        // 5. manajemen: Lihat dashboard dan laporan; ekspor (PRD §3)
        $roles[UserRole::Manajemen->value]->syncPermissions([]);

        // Ruangan default untuk akun demo kepala ruangan
        $icuRoom = Room::firstOrCreate(
            ['name' => 'Ruang ICU'],
            [
                'building' => 'Gedung Utama',
                'floor' => 'Lantai 2',
            ],
        );

        $demoUsers = [
            [
                'name' => 'Super Admin SIGAP',
                'email' => 'superadmin@sigap-alkes.test',
                'role' => UserRole::SuperAdmin,
                'room_id' => null,
            ],
            [
                'name' => 'Budi Santoso (Admin IPSRS)',
                'email' => 'admin@sigap-alkes.test',
                'role' => UserRole::AdminIpsrs,
                'room_id' => null,
            ],
            [
                'name' => 'Andi Pratama (Teknisi Elektromedis)',
                'email' => 'teknisi@sigap-alkes.test',
                'role' => UserRole::Teknisi,
                'room_id' => null,
            ],
            [
                'name' => 'Ns. Siti Aminah (Kepala Ruangan ICU)',
                'email' => 'kepalaruangan@sigap-alkes.test',
                'role' => UserRole::KepalaRuangan,
                'room_id' => $icuRoom->id,
            ],
            [
                'name' => 'dr. Hendra Wijaya (Manajemen RS)',
                'email' => 'manajemen@sigap-alkes.test',
                'role' => UserRole::Manajemen,
                'room_id' => null,
            ],
        ];

        foreach ($demoUsers as $demo) {
            $user = User::updateOrCreate(
                ['email' => $demo['email']],
                [
                    'name' => $demo['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'room_id' => $demo['room_id'],
                ],
            );

            $user->syncRoles([$demo['role']->value]);
        }
    }
}
