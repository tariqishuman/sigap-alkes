<?php

use App\Http\Controllers\PublicEquipmentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/alat/{qr_token}', [PublicEquipmentController::class, 'show'])
    ->name('public.equipment.show');
