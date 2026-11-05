<?php

use App\Enums\EquipmentStatus;
use App\Models\Calibration;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Room;
use App\Models\SparePart;
use App\Models\TestingInstitution;
use App\Models\User;
use Database\Seeders\ClearDummyDataSeeder;
use Database\Seeders\DummyDataSeeder;

it('seeds realistic dummy data across all statuses and clears it cleanly while keeping demo users', function () {
    $this->seed(DummyDataSeeder::class);

    expect(Room::count())->toBe(6)
        ->and(EquipmentType::count())->toBe(10)
        ->and(TestingInstitution::count())->toBe(3)
        ->and(SparePart::count())->toBe(8)
        ->and(Equipment::count())->toBe(30)
        ->and(Calibration::count())->toBeGreaterThan(30)
        ->and(User::count())->toBe(5);

    // Pastikan seluruh 6 status alat kesehatan terwakili
    foreach (EquipmentStatus::cases() as $status) {
        expect(Equipment::where('status', $status)->count())->toBeGreaterThan(0);
    }

    // Pastikan idempotent jika dijalankan dua kali berturut-turut
    $this->seed(DummyDataSeeder::class);
    expect(Equipment::count())->toBe(30);

    // Hapus data dummy dengan ClearDummyDataSeeder
    $this->seed(ClearDummyDataSeeder::class);

    expect(Equipment::count())->toBe(0)
        ->and(Calibration::count())->toBe(0)
        ->and(EquipmentType::count())->toBe(0)
        ->and(TestingInstitution::count())->toBe(0)
        ->and(SparePart::count())->toBe(0)
        ->and(Room::count())->toBe(1)
        ->and(Room::first()->name)->toBe('Ruang ICU')
        ->and(User::count())->toBe(5);
});
