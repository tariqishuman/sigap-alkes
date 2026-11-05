<?php

use Database\Seeders\ClearDummyDataSeeder;
use Database\Seeders\DummyDataSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sigap:seed-dummy', function () {
    $this->info('Mengisi data dummy operasional SIGAP-Alkes...');
    $this->call('db:seed', ['--class' => DummyDataSeeder::class, '--force' => true]);
    $this->info('Berhasil! Data dummy inventaris, master data, dan riwayat kalibrasi siap digunakan.');
})->purpose('Isi database dengan data dummy realistis SIGAP-Alkes (tanpa data pasien)');

Artisan::command('sigap:clear-dummy', function () {
    $this->info('Menghapus seluruh data dummy operasional SIGAP-Alkes...');
    $this->call('db:seed', ['--class' => ClearDummyDataSeeder::class, '--force' => true]);
    $this->info('Berhasil! Seluruh data dummy telah dihapus (5 akun demo & role tetap dipertahankan).');
})->purpose('Hapus seluruh data dummy SIGAP-Alkes tanpa menghapus akun demo');

// Status first, so reminders are sent against up-to-date equipment.
Schedule::command('equipment:evaluate-status')->dailyAt('00:10');
Schedule::command('calibration:send-reminders')->dailyAt('07:00');
