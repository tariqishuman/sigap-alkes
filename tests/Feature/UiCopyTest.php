<?php

use App\Filament\Pages\AssetValueReport;
use App\Filament\Pages\OperationalReport;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not expose formulas, math symbols, or PRD references on main UI pages', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $superAdmin = User::where('email', 'superadmin@sigap-alkes.test')->firstOrFail();

    $this->actingAs($superAdmin);

    $urls = [
        '/admin',
        EquipmentResource::getUrl('index'),
        OperationalReport::getUrl(),
        AssetValueReport::getUrl(),
    ];

    $forbidden = ['Σ', '×', '÷', '≥', 'PRD', '§'];

    foreach ($urls as $url) {
        $html = $this->get($url)->assertOk()->getContent();

        foreach ($forbidden as $token) {
            expect($html)->not->toContain($token);
        }
    }
});
