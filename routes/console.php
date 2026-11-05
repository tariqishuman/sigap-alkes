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

Artisan::command('sigap:watermark {state=off : Pilihan: on atau off}', function (string $state) {
    $enable = strtolower($state) === 'on' || strtolower($state) === 'true' || $state === '1';
    $envPath = base_path('.env');

    if (file_exists($envPath)) {
        $content = file_get_contents($envPath);
        $line = 'APP_WATERMARK='.($enable ? 'true' : 'false');

        if (preg_match('/^APP_WATERMARK=.*$/m', $content)) {
            $content = preg_replace('/^APP_WATERMARK=.*$/m', $line, $content);
        } else {
            $content = rtrim($content)."\n{$line}\n";
        }

        file_put_contents($envPath, $content);
    }

    $this->call('config:clear');
    $this->info($enable
        ? 'Watermark Kelompok 2 DIAKTIFKAN (APP_WATERMARK=true).'
        : 'Watermark Kelompok 2 DINONAKTIFKAN (APP_WATERMARK=false).');
})->purpose('Aktifkan atau matikan watermark Kelompok 2 dengan cepat (sigap:watermark on|off)');

// Status first, so reminders are sent against up-to-date equipment.
Schedule::command('equipment:evaluate-status')->dailyAt('00:10');
Schedule::command('calibration:send-reminders')->dailyAt('07:00');
