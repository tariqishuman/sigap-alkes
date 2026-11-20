<?php

use App\Filament\Pages\OperationalReport;
use App\Filament\Resources\Calibrations\Pages\ListCalibrations;
use App\Filament\Resources\DamageReports\Pages\ListDamageReports;
use App\Filament\Resources\Equipment\Pages\ListEquipment;
use App\Filament\Resources\EquipmentMovements\Pages\ListEquipmentMovements;
use App\Filament\Resources\Repairs\Pages\ListRepairs;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the admin login page with the custom illustration and SVG favicon', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('images/illustrations/login.svg')
        ->assertSee('images/brand/favicon.svg');
});

it('renders the public equipment 404 page using the unified errors.sigap layout', function () {
    $this->get('/alat/token-tidak-valid')
        ->assertNotFound()
        ->assertSee('Error 404')
        ->assertSee('Alat tidak ditemukan')
        ->assertSee('images/errors/404.svg')
        ->assertSee('SIGAP-Alkes');

    $this->get('/alat/'.Str::uuid())
        ->assertNotFound()
        ->assertSee('Error 404')
        ->assertSee('Alat tidak ditemukan')
        ->assertSee('images/errors/404.svg');
});

it('renders illustrated empty states across equipment, calibration, repair, movement, and report tables', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $superAdmin = User::where('email', 'superadmin@sigap-alkes.test')->firstOrFail();

    $this->actingAs($superAdmin);

    Livewire::test(ListEquipment::class)
        ->assertSee('images/illustrations/empty-equipment.svg')
        ->searchTable('tidak-ada-alat')
        ->assertSee('images/illustrations/empty-search.svg');

    Livewire::test(ListCalibrations::class)
        ->assertSee('images/illustrations/empty-calibration.svg');

    Livewire::test(ListDamageReports::class)
        ->assertSee('images/illustrations/empty-repair.svg')
        ->assertSee('Tidak ada kerusakan. Semua alat aman.');

    Livewire::test(ListRepairs::class)
        ->assertSee('images/illustrations/empty-repair.svg')
        ->assertSee('Tidak ada kerusakan. Semua alat aman.');

    Livewire::test(ListEquipmentMovements::class)
        ->assertSee('images/illustrations/empty-movement.svg');

    Livewire::test(OperationalReport::class)
        ->assertSee('images/illustrations/empty-report.svg');
});
