<?php

use App\Enums\EquipmentStatus;
use App\Filament\Pages\AppSettings;
use App\Filament\Pages\CalibrationSchedule;
use App\Filament\Pages\OperationalReport;
use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Resources\SpareParts\SparePartResource;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Room;
use App\Models\SparePart;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders v2 secondary pages cleanly across Daftar Alat, Jadwal Kalibrasi, Kerusakan, Stok, Laporan, and Pengaturan', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $superAdmin = User::where('email', 'superadmin@sigap-alkes.test')->firstOrFail();

    $room = Room::create(['name' => 'Ruang OK Uji', 'building' => 'Gedung C', 'floor' => '3']);
    $type = EquipmentType::create(['name' => 'Suction Uji', 'requires_calibration' => true, 'calibration_interval_months' => 12, 'useful_life_years' => 5]);
    $eq = Equipment::create([
        'inventory_code' => 'C-001',
        'name' => 'Suction Pump OK',
        'equipment_type_id' => $type->id,
        'room_id' => $room->id,
        'is_active' => true,
        'acquisition_date' => now()->subYear()->toDateString(),
        'acquisition_cost' => 12_000_000,
        'residual_value' => 1_200_000,
    ]);
    $eq->forceFill(['status' => EquipmentStatus::Overdue])->saveQuietly();

    SparePart::create([
        'name' => 'Filter Udara HEPA',
        'unit' => 'pcs',
        'stock' => 0,
        'min_stock' => 5,
    ]);

    $this->actingAs($superAdmin);

    // §4.2 Daftar Alat with status bar header
    $this->get(EquipmentResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Distribusi Status Alat Aktif');

    // §4.3 Detail Alat with identity card + KPI strip
    $this->get(EquipmentResource::getUrl('view', ['record' => $eq]))
        ->assertOk()
        ->assertSee('Suction Pump OK')
        ->assertSee('Uptime 30 hari')
        ->assertSee('Nilai Aset');

    // §4.4 Jadwal Kalibrasi grouped view + toggle to table
    Livewire::test(CalibrationSchedule::class)
        ->assertSee('Tampilan Kelompok')
        ->assertSee('Terlambat')
        ->call('setViewMode', 'table')
        ->assertSet('viewMode', 'table');

    // §4.5 Laporan Kerusakan status tabs
    $this->get(DamageReportResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Terbuka')
        ->assertSee('Diproses')
        ->assertSee('Selesai');

    // §4.6 Stok Suku Cadang with 30-day usage & Habis status
    $this->get(SparePartResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Pemakaian 30 hari')
        ->assertSee('Habis');

    // §4.7 Laporan Operasional with v2 cards & ASPAK chip
    $this->get(OperationalReport::getUrl())
        ->assertOk()
        ->assertSee('Format sementara');

    // §4.8 Pengaturan Aplikasi vertical tabs & SatuSehat status chip
    $this->get(AppSettings::getUrl())
        ->assertOk()
        ->assertSee('Status Koneksi')
        ->assertSee('Belum diatur');
});

it('renders public QR page and error pages with Plus Jakarta Sans, v2 tokens, and IPSRS phone on overdue status', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    app(SettingsService::class)->set('general.hospital_phone', '(031) 5999999');

    $room = Room::create(['name' => 'Ruang IGD QR', 'building' => 'Gedung A', 'floor' => '1']);
    $type = EquipmentType::create(['name' => 'Defibrilator QR', 'requires_calibration' => true, 'calibration_interval_months' => 12, 'useful_life_years' => 5]);
    $eq = Equipment::create([
        'inventory_code' => 'QR-V2-01',
        'name' => 'Defibrilator Zoll',
        'equipment_type_id' => $type->id,
        'room_id' => $room->id,
        'is_active' => true,
        'acquisition_date' => now()->subYear()->toDateString(),
        'acquisition_cost' => 80_000_000,
        'residual_value' => 8_000_000,
    ]);
    $eq->forceFill(['status' => EquipmentStatus::Overdue])->saveQuietly();

    $this->get(route('public.equipment.show', ['qr_token' => $eq->qr_token]))
        ->assertOk()
        ->assertSee('plus-jakarta-sans')
        ->assertSee('#F4F7F7')
        ->assertSee('Jangan gunakan alat ini. Hubungi IPSRS.')
        ->assertSee('(031) 5999999')
        ->assertDontSee('80.000.000');

    // Public 404 error page uses Plus Jakarta Sans & v2 tokens
    $this->get('/alat/not-a-valid-uuid')
        ->assertNotFound()
        ->assertSee('plus-jakarta-sans')
        ->assertSee('#F4F7F7');
});
