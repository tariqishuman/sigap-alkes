<?php

use App\Http\Controllers\Api\FhirR4Controller;
use Illuminate\Support\Facades\Route;

Route::prefix('fhir/r4')
    ->middleware(['auth:sanctum', 'throttle:fhir'])
    ->group(function (): void {
        Route::get('metadata', [FhirR4Controller::class, 'metadata'])->name('api.fhir.metadata');

        Route::get('Device', [FhirR4Controller::class, 'searchDevices'])->name('api.fhir.device.search');
        Route::get('Device/{id}', [FhirR4Controller::class, 'readDevice'])->whereNumber('id')->name('api.fhir.device.read');

        Route::get('DeviceMetric', [FhirR4Controller::class, 'searchDeviceMetrics'])->name('api.fhir.device-metric.search');
        Route::get('DeviceMetric/{id}', [FhirR4Controller::class, 'readDeviceMetric'])->whereNumber('id')->name('api.fhir.device-metric.read');

        Route::get('Location', [FhirR4Controller::class, 'searchLocations'])->name('api.fhir.location.search');
        Route::get('Location/{id}', [FhirR4Controller::class, 'readLocation'])->whereNumber('id')->name('api.fhir.location.read');

        Route::get('Organization', [FhirR4Controller::class, 'searchOrganizations'])->name('api.fhir.organization.search');
        Route::get('Organization/{id}', [FhirR4Controller::class, 'readOrganization'])->name('api.fhir.organization.read');
    });
