<?php

use App\Filament\Pages\AppSettings;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Room;
use App\Models\Setting;
use App\Models\User;
use App\Services\DamageReportService;
use App\Services\DepreciationCalculator;
use App\Services\RepairService;
use App\Services\SettingsService;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(AppSettingsSeeder::class);
});

it('restricts AppSettings page exclusively to super_admin', function () {
    $superAdmin = User::where('email', 'superadmin@sigap-alkes.test')->firstOrFail();

    $this->actingAs($superAdmin)
        ->get(AppSettings::getUrl())
        ->assertOk()
        ->assertSee('Pengaturan Aplikasi');

    $otherEmails = [
        'admin@sigap-alkes.test',
        'teknisi@sigap-alkes.test',
        'kepalaruangan@sigap-alkes.test',
        'manajemen@sigap-alkes.test',
        'kalibrasi@sigap-alkes.test',
        'magang@sigap-alkes.test',
    ];

    foreach ($otherEmails as $email) {
        $this->flushSession();
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user)
            ->get(AppSettings::getUrl())
            ->assertForbidden();
    }
});

it('encrypts SatuSehat client secret in the database and never exposes it in HTML', function () {
    $superAdmin = User::where('email', 'superadmin@sigap-alkes.test')->firstOrFail();
    $plainSecret = 'SUPER-SECRET-SATUSEHAT-KEY-9999';

    $this->actingAs($superAdmin);

    Livewire::test(AppSettings::class)
        ->fillForm([
            'app_name' => 'SIGAP-Alkes Custom',
            'hospital_name' => 'RS Uji Coba',
            'timezone' => 'Asia/Jakarta',
            'reminder_days' => '30, 7, 0',
            'reminder_send_time' => '07:30',
            'reminder_email_enabled' => true,
            'replace_threshold_percent' => 45,
            'qr_label_footer_text' => 'Scan QR alat',
            'satusehat_enabled' => true,
            'satusehat_environment' => 'sandbox',
            'satusehat_auth_url' => 'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1',
            'satusehat_fhir_base_url' => 'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1',
            'satusehat_organization_id' => 'ORG-123',
            'satusehat_client_id' => 'CLIENT-ABC',
            'satusehat_client_secret' => $plainSecret,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $row = Setting::where('key', 'satusehat.client_secret')->firstOrFail();
    $rawDbValue = DB::table('settings')->where('key', 'satusehat.client_secret')->value('value');

    expect($row->is_encrypted)->toBeTrue()
        ->and($rawDbValue)->not->toContain($plainSecret)
        ->and(app(SettingsService::class)->getDecrypted('satusehat.client_secret'))->toBe($plainSecret);

    // Reloading the page must never expose the plaintext secret in HTML
    $html = $this->get(AppSettings::getUrl())->assertOk()->getContent();
    expect($html)
        ->not->toContain($plainSecret)
        ->toContain('••••');
});

it('tests SatuSehat connection without leaking the access token', function () {
    $settings = app(SettingsService::class);
    $settings->setMany([
        'satusehat.auth_url' => 'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1',
        'satusehat.client_id' => 'client-test',
        'satusehat.client_secret' => 'secret-test',
    ]);

    Http::fake([
        'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1/accesstoken*' => Http::response([
            'access_token' => 'SECRET-ACCESS-TOKEN-XYZ',
            'expires_in' => 3600,
        ], 200),
    ]);

    $result = $settings->testSatuSehatConnection();

    expect($result['ok'])->toBeTrue()
        ->and($result['message'])->not->toContain('SECRET-ACCESS-TOKEN-XYZ');
});

it('renders 500 and 503 error pages safely even when the database connection fails', function () {
    $settings = app(SettingsService::class);
    $settings->clearCache();

    // Point default DB connection to a broken driver/config to simulate DB outage
    $originalDefault = config('database.default');
    config([
        'database.default' => 'broken_db',
        'database.connections.broken_db' => [
            'driver' => 'sqlite',
            'database' => '/nonexistent-path/that/cannot/be/opened.sqlite',
        ],
    ]);

    try {
        foreach (['500', '503'] as $code) {
            $html = View::make("errors.{$code}")->render();
            expect($html)
                ->toContain("Error {$code}")
                ->toContain('SIGAP-Alkes');
        }
    } finally {
        config(['database.default' => $originalDefault]);
        $settings->clearCache();
    }
});

it('uses replacement threshold from app settings in DepreciationCalculator and public base URL for QR', function () {
    $this->travelTo(now()->setDate(2026, 11, 20)->setTime(10, 0));

    $room = Room::where('name', 'Ruang ICU')->firstOrFail();
    $type = EquipmentType::create([
        'name' => 'Tensimeter Non-Kalibrasi',
        'requires_calibration' => false,
        'requires_installation' => false,
        'calibration_interval_months' => 12,
        'useful_life_years' => 5,
        'residual_value_percent' => 10,
    ]);

    // HP 30jt, NR 3jt, MM 5, 36 bulan -> NB = 13.800.000
    $equipment = Equipment::create([
        'inventory_code' => 'SET-001',
        'name' => 'Alat Uji Ambang Settings',
        'equipment_type_id' => $type->id,
        'room_id' => $room->id,
        'acquisition_date' => '2023-11-20',
        'acquisition_cost' => '30000000.00',
        'residual_value' => '3000000.00',
    ]);

    $teknisi = User::where('email', 'teknisi@sigap-alkes.test')->firstOrFail();
    $kepalaIcu = User::where('email', 'kepalaruangan@sigap-alkes.test')->firstOrFail();

    // Repair cost 4.140.000 = 30% of 13.800.000
    $report = app(DamageReportService::class)->report([
        'equipment_id' => $equipment->id,
        'complaint' => 'Rusak ringan',
    ], $kepalaIcu);

    app(RepairService::class)->start([
        'damage_report_id' => $report->id,
        'started_at' => now()->subHours(2),
        'finished_at' => now()->subHour(),
        'action' => 'Perbaikan',
        'labor_cost' => '4140000.00',
    ], $teknisi->id);

    $calculator = app(DepreciationCalculator::class);
    $settings = app(SettingsService::class);

    // Default threshold 50% -> false
    expect($calculator->shouldConsiderReplacement($equipment->fresh()))->toBeFalse();

    // Lower threshold via settings to 30% -> true
    $settings->set('asset.replace_threshold_percent', 30);
    expect($calculator->shouldConsiderReplacement($equipment->fresh()))->toBeTrue();

    // Public QR URL uses qr.public_base_url when configured
    $settings->set('qr.public_base_url', 'https://alkes.rs-its.test/');
    expect($settings->publicEquipmentUrl($equipment->qr_token))
        ->toBe("https://alkes.rs-its.test/alat/{$equipment->qr_token}");
});
