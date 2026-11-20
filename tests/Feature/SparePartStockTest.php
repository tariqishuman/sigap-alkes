<?php

use App\Enums\SparePartMovementType;
use App\Filament\Resources\SpareParts\Pages\ListSpareParts;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Room;
use App\Models\SparePart;
use App\Models\SparePartMovement;
use App\Models\User;
use App\Notifications\LowSparePartStockNotification;
use App\Services\DamageReportService;
use App\Services\RepairService;
use App\Services\SparePartStockService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->room = Room::where('name', 'Ruang ICU')->firstOrFail();
    $this->type = EquipmentType::create([
        'name' => 'Tensimeter Non-Kalibrasi',
        'requires_calibration' => false,
        'requires_installation' => false,
        'calibration_interval_months' => 12,
        'useful_life_years' => 4,
        'residual_value_percent' => 0,
    ]);

    $this->equipment = Equipment::create([
        'inventory_code' => 'STK-001',
        'name' => 'Alat Uji Stok',
        'equipment_type_id' => $this->type->id,
        'room_id' => $this->room->id,
        'acquisition_date' => '2025-01-01',
        'acquisition_cost' => '10000000.00',
        'is_active' => true,
    ]);

    $this->admin = User::where('email', 'admin@sigap-alkes.test')->firstOrFail();
    $this->teknisi = User::where('email', 'teknisi@sigap-alkes.test')->firstOrFail();
    $this->kepalaIcu = User::where('email', 'kepalaruangan@sigap-alkes.test')->firstOrFail();

    $this->part = SparePart::create([
        'name' => 'Baterai Medis 12V',
        'unit' => 'pcs',
        'min_stock' => 3,
    ]);
});

it('handles stock in, repair consumption, delta return/out, locked finished repair, and stock opname per SOP', function () {
    $stockService = app(SparePartStockService::class);
    $damageService = app(DamageReportService::class);
    $repairService = app(RepairService::class);

    // 1. Stock in (penerimaan barang)
    $inMovement = $stockService->stockIn($this->part, 10, 'PO-001 PT Medika', $this->admin->id);
    expect($this->part->fresh()->stock)->toBe(10)
        ->and($inMovement->type)->toBe(SparePartMovementType::In)
        ->and($inMovement->quantity)->toBe(10);

    // 2. Start repair consuming 4 units -> stock becomes 6
    $report = $damageService->report(['equipment_id' => $this->equipment->id, 'complaint' => 'Baterai lemah'], $this->kepalaIcu);
    $repair = $repairService->start([
        'damage_report_id' => $report->id,
        'started_at' => now()->subHour(),
        'action' => 'Ganti baterai',
        'labor_cost' => '100000',
        'spare_parts' => [
            ['spare_part_id' => $this->part->id, 'quantity' => 4, 'unit_price' => '500000'],
        ],
    ], $this->teknisi->id);

    expect($this->part->fresh()->stock)->toBe(6);

    // 3. Reduce quantity from 4 to 2 while repair is still active -> 2 returned -> stock becomes 8
    $repairService->update($repair, [
        'spare_parts' => [
            ['spare_part_id' => $this->part->id, 'quantity' => 2, 'unit_price' => '500000'],
        ],
    ], $this->teknisi->id);

    expect($this->part->fresh()->stock)->toBe(8)
        ->and(SparePartMovement::where('type', SparePartMovementType::Return)->where('quantity', 2)->exists())->toBeTrue();

    // 4. Increase quantity from 2 to 5 -> 3 consumed -> stock becomes 5
    $repairService->update($repair, [
        'spare_parts' => [
            ['spare_part_id' => $this->part->id, 'quantity' => 5, 'unit_price' => '500000'],
        ],
    ], $this->teknisi->id);

    expect($this->part->fresh()->stock)->toBe(5);

    // 5. Finish repair -> locked, no further stock edits allowed
    $repairService->finish($repair, ['finished_at' => now(), 'action' => 'Selesai'], $this->teknisi->id);
    expect(fn () => $repairService->update($repair->fresh(), [
        'spare_parts' => [],
    ], $this->teknisi->id))->toThrow(ValidationException::class);
    expect($this->part->fresh()->stock)->toBe(5);

    // 6. Stock opname adjustment with mandatory reason
    $adj = $stockService->adjust($this->part, 4, 'Opname bulanan: 1 unit rusak', $this->admin->id);
    expect($this->part->fresh()->stock)->toBe(4)
        ->and($adj->type)->toBe(SparePartMovementType::Adjustment)
        ->and($adj->quantity)->toBe(-1);

    // Reject adjustment without reason or negative stock
    expect(fn () => $stockService->adjust($this->part, 3, '', $this->admin->id))->toThrow(ValidationException::class);
    expect(fn () => $stockService->adjust($this->part, -1, 'Negatif', $this->admin->id))->toThrow(ValidationException::class);
});

it('rejects insufficient stock with clear message and prevents negative stock across consecutive requests', function () {
    $stockService = app(SparePartStockService::class);
    $damageService = app(DamageReportService::class);
    $repairService = app(RepairService::class);

    $stockService->stockIn($this->part, 3, 'Stok terbatas', $this->admin->id);

    $eq2 = Equipment::create([
        'inventory_code' => 'STK-002',
        'name' => 'Alat Uji Stok 2',
        'equipment_type_id' => $this->type->id,
        'room_id' => $this->room->id,
        'acquisition_date' => '2025-01-01',
        'acquisition_cost' => '10000000.00',
        'is_active' => true,
    ]);

    $r1 = $damageService->report(['equipment_id' => $this->equipment->id, 'complaint' => 'Kerusakan baterai alat uji 1'], $this->kepalaIcu);
    $r2 = $damageService->report(['equipment_id' => $eq2->id, 'complaint' => 'Kerusakan baterai alat uji 2'], $this->kepalaIcu);

    // Request 1 takes 2 units -> 1 left
    $repairService->start([
        'damage_report_id' => $r1->id,
        'started_at' => now(),
        'spare_parts' => [
            ['spare_part_id' => $this->part->id, 'quantity' => 2, 'unit_price' => '500000'],
        ],
    ], $this->teknisi->id);

    // Request 2 tries to take 2 units when only 1 remains -> rejected with exact message
    try {
        $repairService->start([
            'damage_report_id' => $r2->id,
            'started_at' => now(),
            'spare_parts' => [
                ['spare_part_id' => $this->part->id, 'quantity' => 2, 'unit_price' => '500000'],
            ],
        ], $this->teknisi->id);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors()['spare_parts'][0] ?? '')->toBe('Stok tidak cukup (tersisa 1)');
    }

    expect($this->part->fresh()->stock)->toBe(1);
});

it('sends a database notification to admin_ipsrs when stock drops to or below min_stock', function () {
    Notification::fake();

    $stockService = app(SparePartStockService::class);
    $damageService = app(DamageReportService::class);
    $repairService = app(RepairService::class);

    $stockService->stockIn($this->part, 5, 'Stok awal', $this->admin->id);

    $report = $damageService->report(['equipment_id' => $this->equipment->id, 'complaint' => 'Kerusakan komponen baterai utama'], $this->kepalaIcu);

    // Consume 2 -> stock becomes 3 == min_stock (3) -> triggers LowSparePartStockNotification
    $repairService->start([
        'damage_report_id' => $report->id,
        'started_at' => now(),
        'spare_parts' => [
            ['spare_part_id' => $this->part->id, 'quantity' => 2, 'unit_price' => '500000'],
        ],
    ], $this->teknisi->id);

    Notification::assertSentTo($this->admin, LowSparePartStockNotification::class);
    Notification::assertNotSentTo($this->teknisi, LowSparePartStockNotification::class);
});

it('allows teknisi to view spare part stock but hides stock_in and adjust_stock actions', function () {
    $this->actingAs($this->teknisi);

    Livewire::test(ListSpareParts::class)
        ->assertCanSeeTableRecords([$this->part])
        ->assertTableActionHidden('stock_in', $this->part)
        ->assertTableActionHidden('adjust_stock', $this->part);

    $this->flushSession();
    $this->actingAs($this->admin);

    Livewire::test(ListSpareParts::class)
        ->assertTableActionVisible('stock_in', $this->part)
        ->assertTableActionVisible('adjust_stock', $this->part);
});
