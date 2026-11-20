<?php

use App\Enums\EquipmentStatus;
use App\Models\Calibration;
use App\Models\DamageReport;
use App\Models\Equipment;
use App\Models\EquipmentMovement;
use App\Models\EquipmentType;
use App\Models\Repair;
use App\Models\Room;
use App\Models\SparePart;
use App\Models\TestingInstitution;
use App\Models\User;
use App\Services\DepreciationCalculator;
use App\Services\UptimeCalculator;
use Database\Seeders\ClearDummyDataSeeder;
use Database\Seeders\DummyDataSeeder;

it('seeds deterministic 12-month dummy data across all statuses, replacement flags, and clears cleanly', function () {
    $this->seed(DummyDataSeeder::class);

    expect(Room::count())->toBe(6)
        ->and(EquipmentType::count())->toBe(10)
        ->and(TestingInstitution::count())->toBe(3)
        ->and(SparePart::count())->toBe(8)
        ->and(Equipment::count())->toBe(60)
        ->and(Calibration::count())->toBeGreaterThan(60)
        ->and(DamageReport::count())->toBeGreaterThan(10)
        ->and(Repair::count())->toBeGreaterThan(10)
        ->and(EquipmentMovement::count())->toBeGreaterThanOrEqual(6)
        ->and(User::count())->toBe(5);

    // Pastikan seluruh 6 status alat kesehatan terwakili
    foreach (EquipmentStatus::cases() as $status) {
        expect(Equipment::where('status', $status)->count())->toBeGreaterThan(0);
    }

    // Minimal 2 alat memiliki penanda "Pertimbangkan ganti"
    $depreciation = app(DepreciationCalculator::class);
    $replaceCount = Equipment::with(['equipmentType', 'repairs.repairSpareParts'])
        ->get()
        ->filter(fn (Equipment $eq): bool => $depreciation->shouldConsiderReplacement($eq))
        ->count();
    expect($replaceCount)->toBeGreaterThanOrEqual(2);

    // Seluruh 12 bulan terakhir terisi uptime bervariasi (tidak 100% semua)
    $series = app(UptimeCalculator::class)->monthlySeries(12);
    expect($series)->toHaveCount(12);
    $belowHundred = collect($series)->filter(fn (array $point): bool => $point['uptime_percent'] !== null && $point['uptime_percent'] < 100.0)->count();
    expect($belowHundred)->toBeGreaterThanOrEqual(10);

    foreach ($series as $point) {
        expect($point['uptime_percent'])->not->toBeNull()
            ->and($point['uptime_percent'])->toBeGreaterThanOrEqual(80.0)
            ->and($point['uptime_percent'])->toBeLessThanOrEqual(100.0);
    }

    // Pastikan idempotent jika dijalankan dua kali berturut-turut (tidak ada duplikat)
    $firstCount = Equipment::count();
    $firstCalCount = Calibration::count();
    $this->seed(DummyDataSeeder::class);
    expect(Equipment::count())->toBe($firstCount)
        ->and(Calibration::count())->toBe($firstCalCount);

    // Hapus data dummy dengan ClearDummyDataSeeder
    $this->seed(ClearDummyDataSeeder::class);

    expect(Equipment::count())->toBe(0)
        ->and(Calibration::count())->toBe(0)
        ->and(DamageReport::count())->toBe(0)
        ->and(Repair::count())->toBe(0)
        ->and(EquipmentMovement::count())->toBe(0)
        ->and(EquipmentType::count())->toBe(0)
        ->and(TestingInstitution::count())->toBe(0)
        ->and(SparePart::count())->toBe(0)
        ->and(Room::count())->toBe(1)
        ->and(Room::first()->name)->toBe('Ruang ICU')
        ->and(User::count())->toBe(5);
});
