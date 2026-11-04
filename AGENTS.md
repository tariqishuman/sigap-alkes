# AGENTS.md — SIGAP-Alkes

Panduan untuk AI coding agent (Claude Code, dll) yang mengerjakan repo ini.
Baca juga: `PRD.md` (apa yang dibangun) dan `design.md` (tampilan).

## Stack

| Komponen | Versi / pilihan |
|---|---|
| PHP | ≥ 8.3 |
| Laravel | 13.x |
| Filament | 5.x (panel di `/admin`) |
| Livewire | 4.x (ikut Filament 5) |
| Tailwind | 4.x (custom theme Filament) |
| Role & permission | `bezhansalleh/filament-shield` 4.x + `spatie/laravel-permission` |
| Activity log | `spatie/laravel-activitylog` |
| QR code | `chillerlan/php-qrcode` |
| PDF | `barryvdh/laravel-dompdf` |
| Ekspor Excel | Filament Export Action bawaan (xlsx/csv) |
| Database | MySQL 8 (lokal via Laragon/XAMPP) |
| Test | Pest |

Jangan menambah package di luar daftar ini tanpa bertanya dulu.

## Perintah penting

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve
php artisan schedule:work      # jalankan scheduler saat development
php artisan queue:work         # untuk email & export
./vendor/bin/pest              # semua test
./vendor/bin/pint              # format kode
```

## Struktur kode

```
app/
  Enums/            EquipmentStatus, CalibrationResult, DamageReportStatus, ...
  Models/
  Services/         EquipmentStatusService, DepreciationCalculator,
                    UptimeCalculator, ComplianceCalculator
  Console/Commands/ EvaluateEquipmentStatus, SendCalibrationReminders
  Notifications/    CalibrationDueNotification
  Filament/
    Resources/      satu Resource per entitas utama
    Widgets/        widget dashboard
    Exports/        exporter (laporan, ASPAK)
  Http/Controllers/ PublicEquipmentController (halaman QR publik)
resources/views/public/  halaman status QR
resources/views/pdf/     label QR & laporan PDF
lang/id/                 label bahasa Indonesia
```

## Aturan wajib

1. **Kode dalam bahasa Inggris, UI dalam bahasa Indonesia.** Nama tabel/kolom/kelas Inggris. Semua label, pesan, dan notifikasi Indonesia. `APP_LOCALE=id`, timezone `Asia/Jakarta`.
2. **Status alat hanya diubah lewat `EquipmentStatusService`.** Service ini memvalidasi transisi sesuai tabel di PRD §F4 dan selalu menulis `status_histories`. Jangan pernah `$equipment->update(['status' => ...])` langsung dari Resource atau Controller.
3. **Logika bisnis di `app/Services`, bukan di Filament Resource.** Resource cukup memanggil service.
4. **Enum pakai PHP backed enum** yang mengimplementasikan `HasLabel` dan `HasColor` dari Filament, supaya badge dan label konsisten.
5. **Uang** disimpan `decimal(15,2)`. Hitung pakai bcmath atau integer rupiah, jangan float untuk penyusutan.
6. **Tidak ada data pasien.** Jangan membuat tabel, kolom, atau seeder yang berisi data pasien.
7. **Halaman QR publik** hanya menampilkan field yang diizinkan di PRD §F4. Gunakan `qr_token` (UUID), jangan ID numerik.
8. **Alat tidak di-hard delete.** Gunakan `is_active = false`.
9. **Otorisasi lewat Shield/Policy.** `kepala_ruangan` hanya melihat alat di `room_id` miliknya (scope query di Resource).
10. **Kode sederhana dan mudah dibaca** — ini proyek mata kuliah. Hindari abstraksi berlebihan, pattern yang tidak perlu, atau trik clever. Komentar singkat untuk rumus dan aturan bisnis.

## Testing

Wajib ada Pest test untuk:
- `DepreciationCalculator` — termasuk test case PRD §F6 (30 jt / 3 jt / 5 th → 5,4 jt per tahun; NB 13,8 jt di bulan ke-36; NB 3 jt di bulan ke-72).
- `EquipmentStatusService` — setiap baris tabel transisi PRD §F4, termasuk transisi yang harus ditolak.
- `UptimeCalculator` dan `ComplianceCalculator` dengan data kecil yang bisa dihitung manual.
- Command pengingat: tidak mengirim dobel untuk siklus yang sama.
- Halaman QR publik: tidak membocorkan field sensitif.

Jangan menandai milestone selesai kalau `./vendor/bin/pest` belum hijau.

## Cara kerja

- Kerjakan **per milestone** (lihat urutan di `INIT_PROMPT.md`). Di akhir milestone: jalankan test, jalankan Pint, ringkas apa yang dibuat, lalu **berhenti dan tunggu konfirmasi**.
- Sebelum migrasi yang menghapus/mengubah kolom yang sudah ada, tanya dulu.
- Commit kecil dan jelas per milestone (pesan commit bahasa Inggris).
- Jika PRD ambigu, pilih opsi paling sederhana, catat asumsinya di `docs/assumptions.md`, lalu lanjut.
