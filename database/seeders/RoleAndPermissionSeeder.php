<?php

namespace Database\Seeders;

use App\Enums\TestingInstitutionKind;
use App\Enums\UserRole;
use App\Models\Room;
use App\Models\TestingInstitution;
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
            'Calibration',
            'DamageReport',
            'Repair',
            'EquipmentMovement',
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
        $adminIpsrsPermissions[] = 'ViewAny:Calibration';
        $adminIpsrsPermissions = array_merge($adminIpsrsPermissions, [
            'ViewAny:DamageReport',
            'View:DamageReport',
            'Create:DamageReport',
            'Update:DamageReport',
            'ViewAny:Repair',
            'View:Repair',
            'ViewAny:EquipmentMovement',
            'View:EquipmentMovement',
        ]);
        $roles[UserRole::AdminIpsrs->value]->syncPermissions($adminIpsrsPermissions);

        // 3. teknisi: Lihat semua alat; catat kalibrasi; lapor kerusakan, kelola perbaikan & mutasi (PRD §3)
        $roles[UserRole::Teknisi->value]->syncPermissions([
            'ViewAny:Equipment',
            'View:Equipment',
            'ViewAny:Calibration',
            'Create:Calibration',
            'Update:Calibration',
            'ViewAny:DamageReport',
            'View:DamageReport',
            'Create:DamageReport',
            'Update:DamageReport',
            'ViewAny:Repair',
            'View:Repair',
            'Create:Repair',
            'Update:Repair',
            'ViewAny:EquipmentMovement',
            'View:EquipmentMovement',
            'Create:EquipmentMovement',
            'ViewAny:SparePart',
            'View:SparePart',
        ]);

        // 4. kepala_ruangan: Lihat alat di ruangannya saja; buat & ubah laporan kerusakan open di ruangannya (PRD §3)
        $roles[UserRole::KepalaRuangan->value]->syncPermissions([
            'ViewAny:Equipment',
            'View:Equipment',
            'ViewAny:DamageReport',
            'View:DamageReport',
            'Create:DamageReport',
            'Update:DamageReport',
        ]);

        // 5. manajemen: Lihat dashboard, jadwal, dan laporan (read-only) (PRD §3)
        $roles[UserRole::Manajemen->value]->syncPermissions([
            'ViewAny:Calibration',
            'ViewAny:DamageReport',
            'View:DamageReport',
            'ViewAny:Repair',
            'View:Repair',
            'ViewAny:EquipmentMovement',
            'View:EquipmentMovement',
        ]);

        // 6. badan_kalibrasi: Lihat daftar alat & jadwal kalibrasi; catat & ubah kalibrasi lembaganya sendiri
        $roles[UserRole::BadanKalibrasi->value]->syncPermissions([
            'ViewAny:Equipment',
            'View:Equipment',
            'ViewAny:Calibration',
            'View:Calibration',
            'Create:Calibration',
            'Update:Calibration',
        ]);

        // 7. magang: Lihat alat, kalibrasi, laporan kerusakan, perbaikan, mutasi (tanpa biaya/stok); buat laporan kerusakan
        $roles[UserRole::Magang->value]->syncPermissions([
            'ViewAny:Equipment',
            'View:Equipment',
            'ViewAny:Calibration',
            'View:Calibration',
            'ViewAny:DamageReport',
            'View:DamageReport',
            'Create:DamageReport',
            'ViewAny:Repair',
            'View:Repair',
            'ViewAny:EquipmentMovement',
            'View:EquipmentMovement',
        ]);

        // Ruangan default untuk akun demo kepala ruangan
        $icuRoom = Room::firstOrCreate(
            ['name' => 'Ruang ICU'],
            [
                'building' => 'Gedung Utama',
                'floor' => 'Lantai 2',
            ],
        );

        // Lembaga penguji default untuk akun demo badan kalibrasi
        $bpafk = TestingInstitution::firstOrCreate(
            ['name' => 'BPAFK Surabaya'],
            [
                'kind' => TestingInstitutionKind::Bpafk,
                'accreditation_number' => 'LK-045-IDN',
            ],
        );

        $demoUsers = [
            [
                'name' => 'Super Admin SIGAP',
                'email' => 'superadmin@sigap-alkes.test',
                'role' => UserRole::SuperAdmin,
                'room_id' => null,
                'testing_institution_id' => null,
            ],
            [
                'name' => 'Budi Santoso (Admin IPSRS)',
                'email' => 'admin@sigap-alkes.test',
                'role' => UserRole::AdminIpsrs,
                'room_id' => null,
                'testing_institution_id' => null,
            ],
            [
                'name' => 'Andi Pratama (Teknisi Elektromedis)',
                'email' => 'teknisi@sigap-alkes.test',
                'role' => UserRole::Teknisi,
                'room_id' => null,
                'testing_institution_id' => null,
            ],
            [
                'name' => 'Ns. Siti Aminah (Kepala Ruangan ICU)',
                'email' => 'kepalaruangan@sigap-alkes.test',
                'role' => UserRole::KepalaRuangan,
                'room_id' => $icuRoom->id,
                'testing_institution_id' => null,
            ],
            [
                'name' => 'dr. Hendra Wijaya (Manajemen RS)',
                'email' => 'manajemen@sigap-alkes.test',
                'role' => UserRole::Manajemen,
                'room_id' => null,
                'testing_institution_id' => null,
            ],
            [
                'name' => 'Petugas BPAFK Surabaya (Badan Kalibrasi)',
                'email' => 'kalibrasi@sigap-alkes.test',
                'role' => UserRole::BadanKalibrasi,
                'room_id' => null,
                'testing_institution_id' => $bpafk->id,
            ],
            [
                'name' => 'Mahasiswa Magang Elektromedis',
                'email' => 'magang@sigap-alkes.test',
                'role' => UserRole::Magang,
                'room_id' => null,
                'testing_institution_id' => null,
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
                    'testing_institution_id' => $demo['testing_institution_id'],
                ],
            );

            $user->syncRoles([$demo['role']->value]);
        }
    }
}
