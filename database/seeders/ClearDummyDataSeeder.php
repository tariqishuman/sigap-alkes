<?php

namespace Database\Seeders;

use App\Models\Calibration;
use App\Models\CalibrationReminder;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Room;
use App\Models\SparePart;
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
            StatusHistory::query()->delete();
            Equipment::query()->delete();
            SparePart::query()->delete();
            TestingInstitution::query()->delete();
            EquipmentType::query()->delete();

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
