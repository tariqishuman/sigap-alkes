<?php

use App\Http\Controllers\DamageReportPhotoController;
use App\Http\Controllers\PublicEquipmentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/alat/{qr_token}', [PublicEquipmentController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('public.equipment.show');

Route::get('/admin/damage-reports/{damageReport}/photos/{index}', [DamageReportPhotoController::class, 'show'])
    ->middleware('auth')
    ->whereNumber('index')
    ->name('damage-reports.photo');
