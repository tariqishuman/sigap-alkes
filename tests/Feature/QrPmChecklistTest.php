<?php

use App\Enums\CalibrationResult;
use App\Enums\EquipmentStatus;
use App\Enums\TestingInstitutionKind;
use App\Enums\UserRole;
use App\Models\Calibration;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\PreventiveMaintenance;
use App\Models\Room;
use App\Models\TestingInstitution;
use App\Models\User;
use App\Services\PreventiveMaintenanceService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->room = Room::create(['name' => 'Ruang ICU', 'building' => 'Gedung Utama', 'floor' => 'Lt 2']);

    $this->pmType = EquipmentType::create([
        'name' => 'Ventilator QR PM',
        'requires_calibration' => true,
        'requires_installation' => true,
        'calibration_interval_months' => 12,
        'pm_interval_months' => 3,
        'pm_checklist' => ['Filter HEPA rahasia', 'Sensor O2', 'Alarm apnea'],
    ]);

    $this->noPmType = EquipmentType::create([
        'name' => 'Tensimeter Tanpa PM',
        'requires_calibration' => false,
        'requires_installation' => false,
        'calibration_interval_months' => 12,
        'pm_interval_months' => null,
        'pm_checklist' => null,
    ]);

    $this->institution = TestingInstitution::create([
        'name' => 'BPAFK Surabaya',
        'kind' => TestingInstitutionKind::Bpafk,
    ]);

    $this->teknisi = User::factory()->create(['name' => 'Teknisi Rahasia PM']);
    $this->teknisi->assignRole(UserRole::Teknisi->value);

    $this->admin = User::factory()->create(['name' => 'Admin IPSRS PM']);
    $this->admin->assignRole(UserRole::AdminIpsrs->value);

    $this->kepala = User::factory()->create(['name' => 'Kepala Ruangan', 'room_id' => $this->room->id]);
    $this->kepala->assignRole(UserRole::KepalaRuangan->value);

    $this->magang = User::factory()->create(['name' => 'Magang']);
    $this->magang->assignRole(UserRole::Magang->value);

    $this->manajemen = User::factory()->create(['name' => 'Manajemen']);
    $this->manajemen->assignRole(UserRole::Manajemen->value);

    $this->badan = User::factory()->create(['name' => 'Badan Kalibrasi', 'testing_institution_id' => $this->institution->id]);
    $this->badan->assignRole(UserRole::BadanKalibrasi->value);

    $this->equipment = Equipment::create([
        'inventory_code' => 'QR-PM-001',
        'name' => 'Ventilator Mindray #9',
        'equipment_type_id' => $this->pmType->id,
        'room_id' => $this->room->id,
        'acquisition_date' => '2025-01-01',
        'acquisition_cost' => '300000000.00',
        'is_active' => true,
    ]);
    $this->equipment->forceFill(['status' => EquipmentStatus::FitForUse])->saveQuietly();
});

it('shows read-only PM dates and Catat pemeliharaan button on public QR page without leaking checklist or technician name', function () {
    app(PreventiveMaintenanceService::class)->record([
        'equipment_id' => $this->equipment->id,
        'performed_at' => '2026-10-01',
        'checklist_results' => [
            ['item' => 'Filter HEPA rahasia', 'status' => 'good', 'note' => null],
            ['item' => 'Sensor O2', 'status' => 'good', 'note' => null],
            ['item' => 'Alarm apnea', 'status' => 'good', 'note' => null],
        ],
    ], $this->teknisi);

    $this->get(route('public.equipment.show', ['qr_token' => $this->equipment->qr_token]))
        ->assertOk()
        ->assertSee('Pemeliharaan terakhir')
        ->assertSee('Berikutnya')
        ->assertSee('Catat pemeliharaan')
        ->assertDontSee('Filter HEPA rahasia')
        ->assertDontSee('Teknisi Rahasia PM');

    // Equipment without PM interval hides PM info and button
    $noPmEq = Equipment::create([
        'inventory_code' => 'QR-NOPM-001',
        'name' => 'Tensimeter Biasa',
        'equipment_type_id' => $this->noPmType->id,
        'room_id' => $this->room->id,
        'acquisition_date' => '2025-01-01',
        'acquisition_cost' => '4000000.00',
        'is_active' => true,
    ]);

    $this->get(route('public.equipment.show', ['qr_token' => $noPmEq->qr_token]))
        ->assertOk()
        ->assertDontSee('Pemeliharaan terakhir')
        ->assertDontSee('Catat pemeliharaan');
});

it('redirects guests to login with intended URL and denies roles other than teknisi and admin_ipsrs', function () {
    $pmUrl = route('public.equipment.pm.form', ['qr_token' => $this->equipment->qr_token]);

    $this->get($pmUrl)->assertRedirect('/admin/login');
    expect(session('url.intended'))->toBe($pmUrl);

    foreach ([$this->kepala, $this->magang, $this->manajemen, $this->badan] as $deniedUser) {
        $this->actingAs($deniedUser)
            ->get($pmUrl)
            ->assertForbidden()
            ->assertSee('Tidak dapat mencatat pemeliharaan preventif');
    }

    // Teknisi & Admin IPSRS can access mobile form
    $this->actingAs($this->teknisi)->get($pmUrl)->assertOk()->assertSee('Catat Pemeliharaan Preventif');
    $this->actingAs($this->admin)->get($pmUrl)->assertOk()->assertSee('Catat Pemeliharaan Preventif');
});

it('rejects mobile PM submission without selected condition and records qr_scan with optional private photo', function () {
    Storage::fake('local');
    $storeUrl = route('public.equipment.pm.store', ['qr_token' => $this->equipment->qr_token]);

    // Missing condition on item 2 -> rejected
    $this->actingAs($this->teknisi)
        ->post($storeUrl, [
            'checklist' => [
                ['item' => 'Filter HEPA rahasia', 'status' => 'good', 'note' => ''],
                ['item' => 'Sensor O2', 'status' => null, 'note' => ''],
                ['item' => 'Alarm apnea', 'status' => 'good', 'note' => ''],
            ],
        ])
        ->assertRedirect(route('public.equipment.pm.form', ['qr_token' => $this->equipment->qr_token]))
        ->assertSessionHasErrors('checklist_results.1.status');

    expect(PreventiveMaintenance::count())->toBe(0);

    // Valid submission with 1 unhealthy item + private photo -> saved with recorded_via = qr_scan and offers damage report CTA
    $photo = UploadedFile::fake()->image('sensor.jpg', 300, 300);

    $this->actingAs($this->teknisi)
        ->post($storeUrl, [
            'checklist' => [
                ['item' => 'Filter HEPA rahasia', 'status' => 'good', 'note' => ''],
                ['item' => 'Sensor O2', 'status' => 'needs_attention', 'note' => 'Pembacaan O2 melambat', 'photo' => $photo],
                ['item' => 'Alarm apnea', 'status' => 'good', 'note' => ''],
            ],
            'notes' => 'Perlu pengecekan sensor O2 lebih lanjut.',
        ])
        ->assertOk()
        ->assertSee('Tersimpan via scan QR')
        ->assertSee('Buat laporan kerusakan');

    $pm = PreventiveMaintenance::sole();
    expect($pm->recorded_via)->toBe('qr_scan')
        ->and($pm->hasUnhealthyItems())->toBeTrue()
        ->and($pm->checklist_results[1]['photo'] ?? null)->not->toBeNull();

    Storage::disk('local')->assertExists($pm->checklist_results[1]['photo']);
});

it('includes Kalibrasi berlaku s.d. date on black-and-white QR label PDF template', function () {
    Calibration::create([
        'equipment_id' => $this->equipment->id,
        'testing_institution_id' => $this->institution->id,
        'tested_at' => '2026-06-01',
        'result' => CalibrationResult::Pass,
        'certificate_number' => 'CERT-LBL-001',
        'valid_until' => '2027-06-01',
        'recorded_by' => $this->teknisi->id,
    ]);

    $html = view('pdf.equipment-qr-labels', [
        'items' => [[
            'equipment' => $this->equipment->fresh(['room', 'equipmentType', 'latestValidCalibration']),
            'qr_data_uri' => 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=',
            'public_url' => route('public.equipment.show', ['qr_token' => $this->equipment->qr_token]),
        ]],
        'appName' => 'SIGAP-Alkes',
        'hospitalName' => 'RS Pendidikan ITS',
        'labelFooterText' => 'Scan QR untuk cek status alat',
    ])->render();

    expect($html)->toContain('Kalibrasi berlaku s.d.')
        ->and($html)->toContain('2027');
});
