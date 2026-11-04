<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

it('configures Indonesian locale and Asia/Jakarta timezone', function () {
    expect(config('app.locale'))->toBe('id')
        ->and(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(config('app.name'))->toBe('SIGAP-Alkes');
});

it('seeds all five roles and demo users with password "password"', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    foreach (UserRole::cases() as $roleEnum) {
        expect(Role::where('name', $roleEnum->value)->exists())->toBeTrue();
    }

    $expectedUsers = [
        'superadmin@sigap-alkes.test' => UserRole::SuperAdmin->value,
        'admin@sigap-alkes.test' => UserRole::AdminIpsrs->value,
        'teknisi@sigap-alkes.test' => UserRole::Teknisi->value,
        'kepalaruangan@sigap-alkes.test' => UserRole::KepalaRuangan->value,
        'manajemen@sigap-alkes.test' => UserRole::Manajemen->value,
    ];

    foreach ($expectedUsers as $email => $roleName) {
        $user = User::where('email', $email)->first();

        expect($user)->not->toBeNull()
            ->and(Hash::check('password', $user->password))->toBeTrue()
            ->and($user->hasRole($roleName))->toBeTrue()
            ->and($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
    }
});

it('renders the Filament admin login page and protects the dashboard', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/admin/login')->assertOk();
});

it('allows demo users to access the admin dashboard and enforces UserResource permissions', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $superAdmin = User::where('email', 'superadmin@sigap-alkes.test')->firstOrFail();
    $adminIpsrs = User::where('email', 'admin@sigap-alkes.test')->firstOrFail();
    $teknisi = User::where('email', 'teknisi@sigap-alkes.test')->firstOrFail();
    $kepalaRuangan = User::where('email', 'kepalaruangan@sigap-alkes.test')->firstOrFail();
    $manajemen = User::where('email', 'manajemen@sigap-alkes.test')->firstOrFail();

    $this->actingAs($superAdmin)->get('/admin')->assertOk();
    $this->actingAs($superAdmin)->get('/admin/users')->assertOk();
    $this->actingAs($superAdmin)->get('/admin/shield/roles')->assertOk();

    $this->flushSession();
    $this->actingAs($adminIpsrs)->get('/admin')->assertOk();
    $this->actingAs($adminIpsrs)->get('/admin/users')->assertOk();
    $this->actingAs($adminIpsrs)->get('/admin/shield/roles')->assertForbidden();

    $this->flushSession();
    $this->actingAs($teknisi)->get('/admin')->assertOk();
    $this->actingAs($teknisi)->get('/admin/users')->assertForbidden();

    $this->flushSession();
    $this->actingAs($kepalaRuangan)->get('/admin')->assertOk();
    $this->actingAs($kepalaRuangan)->get('/admin/users')->assertForbidden();

    $this->flushSession();
    $this->actingAs($manajemen)->get('/admin')->assertOk();
    $this->actingAs($manajemen)->get('/admin/users')->assertForbidden();
});

it('records user changes in activity log', function () {
    $user = User::factory()->create([
        'name' => 'Nama Awal',
        'email' => 'awal@sigap-alkes.test',
    ]);

    $user->update(['name' => 'Nama Baru']);

    expect(Activity::where('subject_type', User::class)->where('subject_id', $user->id)->count())
        ->toBeGreaterThanOrEqual(2);
});
