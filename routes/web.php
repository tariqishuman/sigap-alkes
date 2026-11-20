<?php

use App\Http\Controllers\CalibrationCertificateController;
use App\Http\Controllers\DamageReportPhotoController;
use App\Http\Controllers\PublicEquipmentController;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/alat/{qr_token}', [PublicEquipmentController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('public.equipment.show');

Route::get('/alat/{qr_token}/lapor', [PublicEquipmentController::class, 'report'])
    ->middleware('throttle:60,1')
    ->name('public.equipment.report');

Route::get('/admin/damage-reports/{damageReport}/photos/{index}', [DamageReportPhotoController::class, 'show'])
    ->middleware('auth')
    ->whereNumber('index')
    ->name('damage-reports.photo');

Route::get('/admin/calibrations/{calibration}/certificate', [CalibrationCertificateController::class, 'show'])
    ->middleware('auth')
    ->name('calibrations.certificate');

Route::middleware('auth')->group(function () {
    Route::post('/admin/push-subscriptions', [PushSubscriptionController::class, 'store'])
        ->name('push-subscriptions.store');
    Route::delete('/admin/push-subscriptions', [PushSubscriptionController::class, 'destroy'])
        ->name('push-subscriptions.destroy');
    Route::post('/admin/push-subscriptions/test', [PushSubscriptionController::class, 'test'])
        ->name('push-subscriptions.test');
});
