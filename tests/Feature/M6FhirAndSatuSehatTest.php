<?php

use App\Enums\CalibrationResult;
use App\Enums\DamageReportStatus;
use App\Enums\EquipmentStatus;
use App\Enums\TestingInstitutionKind;
use App\Models\Calibration;
use App\Models\DamageReport;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Repair;
use App\Models\Room;
use App\Models\SatuSehatSyncLog;
use App\Models\TestingInstitution;
use App\Models\User;
use App\Services\SatuSehatSyncService;
use App\Services\SettingsService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Cache::flush();

    $this->superAdmin = User::where('email', 'superadmin@sigap-alkes.test')->firstOrFail();
    $this->teknisi = User::where('email', 'teknisi@sigap-alkes.test')->firstOrFail();
    $this->token = $this->superAdmin->createToken('Test FHIR Client', ['fhir:read'])->plainTextToken;

    $this->room = Room::create([
        'name' => 'Ruang ICU FHIR',
        'building' => 'Gedung Utama',
        'floor' => 'Lantai 2',
    ]);

    $this->type = EquipmentType::create([
        'name' => 'Ventilator FHIR',
        'requires_calibration' => true,
        'calibration_interval_months' => 12,
        'useful_life_years' => 8,
    ]);

    $this->inst = TestingInstitution::create([
        'name' => 'BPAFK Surabaya',
        'kind' => TestingInstitutionKind::Bpafk,
        'accreditation_number' => 'LK-045-IDN',
    ]);
});

function makeFhirEquipment(Room $room, EquipmentType $type, string $code, EquipmentStatus $status, string $sn = 'SN-9001'): Equipment
{
    $eq = Equipment::create([
        'inventory_code' => $code,
        'name' => "Ventilator {$code}",
        'equipment_type_id' => $type->id,
        'room_id' => $room->id,
        'brand' => 'Mindray',
        'model' => 'SV300',
        'serial_number' => $sn,
        'acquisition_date' => now()->subYear()->toDateString(),
        'acquisition_cost' => 350_000_000,
        'residual_value' => 35_000_000,
        'is_active' => true,
    ]);
    $eq->forceFill(['status' => $status])->saveQuietly();

    return $eq->fresh();
}

describe('HL7 FHIR R4 Read-Only API (/api/fhir/r4)', function () {
    it('returns 401 OperationOutcome JSON when unauthenticated and 404 OperationOutcome for missing resources', function () {
        $this->get('/api/fhir/r4/Device')
            ->assertStatus(401)
            ->assertHeader('Content-Type', 'application/fhir+json')
            ->assertJsonPath('resourceType', 'OperationOutcome')
            ->assertJsonPath('issue.0.code', 'login');

        $this->withToken($this->token)
            ->get('/api/fhir/r4/Device/999999')
            ->assertStatus(404)
            ->assertHeader('Content-Type', 'application/fhir+json')
            ->assertJsonPath('resourceType', 'OperationOutcome')
            ->assertJsonPath('issue.0.code', 'not-found');
    });

    it('serves CapabilityStatement at /api/fhir/r4/metadata', function () {
        $this->withToken($this->token)
            ->get('/api/fhir/r4/metadata')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/fhir+json')
            ->assertJsonPath('resourceType', 'CapabilityStatement')
            ->assertJsonPath('fhirVersion', '4.0.1');
    });

    it('maps all 6 SIGAP equipment statuses to exact FHIR R4 Device status, statusReason, and extension', function (EquipmentStatus $sigapStatus, string $fhirStatus, string $reasonCode) {
        $eq = makeFhirEquipment($this->room, $this->type, 'FHIR-ST-'.$sigapStatus->value, $sigapStatus);

        $this->withToken($this->token)
            ->get("/api/fhir/r4/Device/{$eq->id}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/fhir+json')
            ->assertJsonPath('resourceType', 'Device')
            ->assertJsonPath('id', (string) $eq->id)
            ->assertJsonPath('status', $fhirStatus)
            ->assertJsonPath('statusReason.0.coding.0.code', $reasonCode)
            ->assertJsonPath('extension.0.valueCode', $sigapStatus->value)
            ->assertJsonPath('manufacturer', 'Mindray')
            ->assertJsonPath('modelNumber', 'SV300')
            ->assertJsonPath('type.text', 'Ventilator FHIR')
            ->assertJsonPath('location.reference', "Location/{$this->room->id}")
            ->assertJsonPath('owner.reference', 'Organization/hospital');
    })->with([
        'fit_for_use' => [EquipmentStatus::FitForUse, 'active', 'online'],
        'calibration_due' => [EquipmentStatus::CalibrationDue, 'active', 'online'],
        'overdue' => [EquipmentStatus::Overdue, 'active', 'not-ready'],
        'under_testing' => [EquipmentStatus::UnderTesting, 'inactive', 'standby'],
        'under_repair' => [EquipmentStatus::UnderRepair, 'inactive', 'offline'],
        'unfit' => [EquipmentStatus::Unfit, 'inactive', 'hw-discon'],
    ]);

    it('supports Device search filters and searchset Bundle pagination with next/previous links', function () {
        $otherRoom = Room::create(['name' => 'Ruang IGD FHIR', 'building' => 'Gedung A', 'floor' => 'Lantai 1']);
        makeFhirEquipment($this->room, $this->type, 'FHIR-PG-01', EquipmentStatus::FitForUse, 'SN-PG-1');
        makeFhirEquipment($this->room, $this->type, 'FHIR-PG-02', EquipmentStatus::Overdue, 'SN-PG-2');
        makeFhirEquipment($otherRoom, $this->type, 'FHIR-PG-03', EquipmentStatus::FitForUse, 'SN-PG-3');

        // Search by identifier
        $this->withToken($this->token)
            ->get('/api/fhir/r4/Device?identifier=FHIR-PG-02')
            ->assertOk()
            ->assertJsonPath('resourceType', 'Bundle')
            ->assertJsonPath('type', 'searchset')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('entry.0.resource.identifier.0.value', 'FHIR-PG-02');

        // Search by location & pagination _count=1
        $page1 = $this->withToken($this->token)
            ->get("/api/fhir/r4/Device?location=Location/{$this->room->id}&_count=1&page=1")
            ->assertOk()
            ->assertJsonPath('total', 2);

        $links1 = collect($page1->json('link'))->pluck('relation')->all();
        expect($links1)->toContain('self')->toContain('next')->not->toContain('previous');

        $page2 = $this->withToken($this->token)
            ->get("/api/fhir/r4/Device?location=Location/{$this->room->id}&_count=1&page=2")
            ->assertOk();

        $links2 = collect($page2->json('link'))->pluck('relation')->all();
        expect($links2)->toContain('self')->toContain('previous')->not->toContain('next');
    });

    it('serves DeviceMetric, Location, and Organization resources and never leaks cost, technician, or complaint data', function () {
        $eq = makeFhirEquipment($this->room, $this->type, 'FHIR-PRIV-01', EquipmentStatus::FitForUse, 'SN-PRIV-1');

        Calibration::create([
            'equipment_id' => $eq->id,
            'testing_institution_id' => $this->inst->id,
            'tested_at' => now()->subMonth()->toDateString(),
            'valid_until' => now()->addMonths(11)->toDateString(),
            'result' => CalibrationResult::Pass,
            'certificate_number' => 'CERT-FHIR-1',
        ]);

        $dr = DamageReport::create([
            'equipment_id' => $eq->id,
            'reported_by' => $this->teknisi->id,
            'reported_at' => now()->subDays(5),
            'complaint' => 'KELUHAN_RAHASIA_SENSOR_ERROR',
            'status' => DamageReportStatus::Resolved,
        ]);

        Repair::create([
            'damage_report_id' => $dr->id,
            'technician_id' => $this->teknisi->id,
            'started_at' => now()->subDays(5),
            'finished_at' => now()->subDays(4),
            'action' => 'Ganti modul internal',
            'labor_cost' => 1_750_000,
        ]);

        // DeviceMetric
        $metricRes = $this->withToken($this->token)
            ->get("/api/fhir/r4/DeviceMetric/{$eq->id}")
            ->assertOk()
            ->assertJsonPath('resourceType', 'DeviceMetric')
            ->assertJsonPath('calibration.0.type', 'two-point')
            ->assertJsonPath('calibration.0.state', 'calibrated')
            ->assertJsonPath('extension.0.valueDate', now()->addMonths(11)->format('Y-m-d'));

        // Location
        $this->withToken($this->token)
            ->get("/api/fhir/r4/Location/{$this->room->id}")
            ->assertOk()
            ->assertJsonPath('resourceType', 'Location')
            ->assertJsonPath('name', 'Ruang ICU FHIR')
            ->assertJsonPath('description', 'Gedung Utama - Lantai 2');

        // Organization (hospital + testing institution)
        $this->withToken($this->token)
            ->get('/api/fhir/r4/Organization/hospital')
            ->assertOk()
            ->assertJsonPath('resourceType', 'Organization')
            ->assertJsonPath('id', 'hospital');

        $this->withToken($this->token)
            ->get("/api/fhir/r4/Organization/inst-{$this->inst->id}")
            ->assertOk()
            ->assertJsonPath('resourceType', 'Organization')
            ->assertJsonPath('identifier.0.value', 'LK-045-IDN');

        // Privacy check across Device and DeviceMetric payloads
        $deviceJson = $this->withToken($this->token)->get("/api/fhir/r4/Device/{$eq->id}")->getContent();
        $metricJson = $metricRes->getContent();

        foreach ([$deviceJson, $metricJson] as $body) {
            expect($body)
                ->not->toContain('350000000')
                ->not->toContain('1750000')
                ->not->toContain('KELUHAN_RAHASIA_SENSOR_ERROR')
                ->not->toContain($this->teknisi->name)
                ->not->toContain('acquisition_cost')
                ->not->toContain('labor_cost');
        }
    });
});

describe('SatuSehat Sandbox Sync (Http::fake only)', function () {
    beforeEach(function () {
        $settings = app(SettingsService::class);
        $settings->setMany([
            'satusehat.enabled' => true,
            'satusehat.environment' => 'sandbox',
            'satusehat.auth_url' => 'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1',
            'satusehat.fhir_base_url' => 'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1',
            'satusehat.organization_id' => '10000004',
            'satusehat.client_id' => 'fake-client-id',
            'satusehat.client_secret' => 'SUPER-SECRET-SHOULD-NEVER-LEAK',
        ]);
    });

    it('caches OAuth2 access token across calls and creates IPSRS sub-organization only once', function () {
        Http::fake([
            '*/oauth2/v1/accesstoken*' => Http::response(['access_token' => 'fake-token-abc', 'expires_in' => 3600], 200),
            '*/fhir-r4/v1/Organization' => Http::response(['resourceType' => 'Organization', 'id' => 'org-ipsrs-uuid-1'], 201),
        ]);

        $sync = app(SatuSehatSyncService::class);

        $first = $sync->syncIpsrsOrganization();
        expect($first['status'])->toBe('success')
            ->and($first['satusehat_id'])->toBe('org-ipsrs-uuid-1')
            ->and(app(SettingsService::class)->get(SatuSehatSyncService::IPSRS_ORG_SETTING_KEY))->toBe('org-ipsrs-uuid-1');

        // Second call must skip (already registered)
        $second = $sync->syncIpsrsOrganization();
        expect($second['status'])->toBe('skipped')
            ->and($second['satusehat_id'])->toBe('org-ipsrs-uuid-1');

        // Token fetched once, Organization POSTed once
        Http::assertSentCount(2);
    });

    it('POSTs new Location, saves rooms.satusehat_id, then PUTs on subsequent sync', function () {
        Http::fake([
            '*/oauth2/v1/accesstoken*' => Http::response(['access_token' => 'fake-token-loc', 'expires_in' => 3600], 200),
            '*/fhir-r4/v1/Location' => Http::response(['resourceType' => 'Location', 'id' => 'loc-uuid-101'], 201),
            '*/fhir-r4/v1/Location/loc-uuid-101' => Http::response(['resourceType' => 'Location', 'id' => 'loc-uuid-101'], 200),
        ]);

        $sync = app(SatuSehatSyncService::class);

        $created = $sync->syncLocation($this->room);
        expect($created['status'])->toBe('success')
            ->and($created['action'])->toBe('create')
            ->and($this->room->fresh()->satusehat_id)->toBe('loc-uuid-101');

        // Update room name & sync again -> PUT
        $this->room->update(['name' => 'Ruang ICU FHIR Utama']);
        $updated = $sync->syncLocation($this->room->fresh());
        expect($updated['status'])->toBe('success')
            ->and($updated['action'])->toBe('update');

        Http::assertSent(fn (Request $req) => $req->method() === 'POST' && str_ends_with($req->url(), '/Location'));
        Http::assertSent(fn (Request $req) => $req->method() === 'PUT' && str_ends_with($req->url(), '/Location/loc-uuid-101'));
    });

    it('logs 4xx/5xx sync failures without ever storing client_secret or bearer tokens', function () {
        Http::fake([
            '*/oauth2/v1/accesstoken*' => Http::response(['access_token' => 'fake-secret-bearer-token', 'expires_in' => 3600], 200),
            '*/fhir-r4/v1/Location' => Http::response([
                'resourceType' => 'OperationOutcome',
                'issue' => [['severity' => 'error', 'diagnostics' => 'Invalid managingOrganization SUPER-SECRET-SHOULD-NEVER-LEAK Bearer fake-secret-bearer-token']],
            ], 422),
        ]);

        $result = app(SatuSehatSyncService::class)->syncLocation($this->room);
        expect($result['status'])->toBe('failed')
            ->and($result['http_status'])->toBe(422);

        $log = SatuSehatSyncLog::query()->latest('id')->firstOrFail();
        expect($log->status)->toBe('failed')
            ->and($log->http_status)->toBe(422)
            ->and($log->response_summary)->not->toContain('SUPER-SECRET-SHOULD-NEVER-LEAK')
            ->and($log->response_summary)->not->toContain('fake-secret-bearer-token');
    });

    it('makes zero HTTP requests when SatuSehat integration toggle is disabled', function () {
        Http::fake();
        app(SettingsService::class)->set('satusehat.enabled', false);

        $sync = app(SatuSehatSyncService::class);
        expect($sync->syncIpsrsOrganization()['status'])->toBe('skipped')
            ->and($sync->syncLocation($this->room)['status'])->toBe('skipped');

        $this->artisan('satusehat:sync', ['--resource' => 'location', '--now' => true])
            ->assertSuccessful();

        Http::assertNothingSent();
    });

    it('POSTs Device with SNOMED CT code, saves equipment.satusehat_id, PUTs on update, and skips types without code', function () {
        $this->room->update(['satusehat_id' => 'loc-uuid-101']);
        $this->type->update(['fhir_type_code' => '706172005', 'fhir_type_display' => 'Ventilator']);

        $mappedEq = makeFhirEquipment($this->room, $this->type, 'DEV-SYNC-01', EquipmentStatus::FitForUse, 'SN-DEV-1');

        $unmappedType = EquipmentType::create([
            'name' => 'Alat Tanpa SNOMED',
            'requires_calibration' => false,
            'calibration_interval_months' => 12,
            'useful_life_years' => 5,
            'fhir_type_code' => null,
        ]);
        $unmappedEq = makeFhirEquipment($this->room, $unmappedType, 'DEV-SKIP-01', EquipmentStatus::FitForUse, 'SN-SKIP-1');

        Http::fake([
            '*/oauth2/v1/accesstoken*' => Http::response(['access_token' => 'fake-token-dev', 'expires_in' => 3600], 200),
            '*/fhir-r4/v1/Device' => Http::response(['resourceType' => 'Device', 'id' => 'dev-uuid-201'], 201),
            '*/fhir-r4/v1/Device/dev-uuid-201' => Http::response(['resourceType' => 'Device', 'id' => 'dev-uuid-201'], 200),
        ]);

        // First run via Artisan command: mappedEq POSTs (201), unmappedEq skipped & logged
        $this->artisan('satusehat:sync', ['--resource' => 'device', '--now' => true])
            ->assertSuccessful();

        expect($mappedEq->fresh()->satusehat_id)->toBe('dev-uuid-201')
            ->and($unmappedEq->fresh()->satusehat_id)->toBeNull();

        $skipLog = SatuSehatSyncLog::where('resource', 'Device')
            ->where('local_id', $unmappedEq->id)
            ->latest('id')
            ->firstOrFail();
        expect($skipLog->status)->toBe('skipped')
            ->and($skipLog->response_summary)->toContain('belum memiliki kode SNOMED CT');

        // Second run: mappedEq PUTs (200)
        $res2 = app(SatuSehatSyncService::class)->syncDevice($mappedEq->fresh());
        expect($res2['status'])->toBe('success')
            ->and($res2['action'])->toBe('update');

        Http::assertSent(fn (Request $req) => $req->method() === 'POST'
            && str_ends_with($req->url(), '/Device')
            && data_get($req->data(), 'identifier.0.system') === 'http://sys-ids.kemkes.go.id/device/10000004'
            && data_get($req->data(), 'type.coding.0.code') === '706172005');
        Http::assertSent(fn (Request $req) => $req->method() === 'PUT' && str_ends_with($req->url(), '/Device/dev-uuid-201'));
    });
});
