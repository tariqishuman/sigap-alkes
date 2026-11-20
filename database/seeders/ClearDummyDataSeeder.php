<?php

namespace Database\Seeders;

use App\Enums\TestingInstitutionKind;
use App\Models\Calibration;
use App\Models\CalibrationReminder;
use App\Models\DamageReport;
use App\Models\DamageReportNote;
use App\Models\Equipment;
use App\Models\EquipmentMovement;
use App\Models\EquipmentType;
use App\Models\PreventiveMaintenance;
use App\Models\PreventiveMaintenanceReminder;
use App\Models\Repair;
use App\Models\RepairSparePart;
use App\Models\Room;
use App\Models\SparePart;
use App\Models\SparePartMovement;
use App\Models\StatusHistory;
use App\Models\TestingInstitution;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class ClearDummyDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            CalibrationReminder::query()->delete();
            Calibration::query()->delete();
            PreventiveMaintenanceReminder::query()->delete();
            PreventiveMaintenance::query()->delete();
            RepairSparePart::query()->delete();
            Repair::query()->delete();
            DamageReportNote::query()->delete();
            DamageReport::query()->delete();
            EquipmentMovement::query()->delete();
            StatusHistory::query()->delete();
            Equipment::query()->delete();
            SparePartMovement::query()->delete();
            SparePart::query()->delete();
            EquipmentType::query()->delete();

            // Pastikan BPAFK Surabaya tetap ada untuk akun demo badan kalibrasi
            $bpafk = TestingInstitution::firstOrCreate(
                ['name' => 'BPAFK Surabaya'],
                [
                    'kind' => TestingInstitutionKind::Bpafk,
                    'accreditation_number' => 'LK-045-IDN',
                ],
            );

            User::query()
                ->whereNotNull('testing_institution_id')
                ->where('testing_institution_id', '!=', $bpafk->id)
                ->update(['testing_institution_id' => null]);

            TestingInstitution::query()
                ->where('id', '!=', $bpafk->id)
                ->delete();

            // Pastikan Ruang ICU tetap ada untuk akun demo kepala ruangan
            $icuRoom = Room::firstOrCreate(
                ['name' => 'Ruang ICU'],
                [
                    'building' => 'Gedung Utama',
                    'floor' => 'Lantai 2',
                ],
            );

            // Lepaskan referensi room_id pada user jika menunjuk ke ruangan selain Ruang ICU
            User::query()
                ->whereNotNull('room_id')
                ->where('room_id', '!=', $icuRoom->id)
                ->update(['room_id' => null]);

            Room::query()
                ->where('id', '!=', $icuRoom->id)
                ->delete();

            // Bersihkan notifikasi & log aktivitas dari data dummy
            DB::table('notifications')->delete();
            Activity::query()->delete();
        });
    }
}
