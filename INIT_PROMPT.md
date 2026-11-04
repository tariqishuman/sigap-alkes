# Prompt init untuk Claude Code

Cara pakai:
1. Buat folder kosong `sigap-alkes`, taruh `PRD.md`, `AGENTS.md`, `design.md`, dan `CLAUDE.md` di dalamnya.
2. Pastikan `php -v` ≥ 8.3, Composer, Node, dan MySQL (Laragon/XAMPP) sudah jalan. Buat database kosong `sigap_alkes`.
3. Buka terminal di folder itu, jalankan `claude`, lalu tempel prompt di bawah.

---

```
Kamu akan membangun SIGAP-Alkes dari nol di folder ini.

Baca dulu PRD.md, AGENTS.md, dan design.md sampai selesai. Ikuti AGENTS.md sebagai aturan kerja.
Folder ini masih kosong selain dokumen tersebut. Buat project Laravel 13 di folder ini
tanpa menghapus keempat file .md (misalnya buat di subfolder sementara lalu pindahkan isinya).

Kerjakan per milestone. Di akhir SETIAP milestone: jalankan ./vendor/bin/pest dan ./vendor/bin/pint,
commit, tulis ringkasan singkat (apa yang dibuat, asumsi, cara mengecek di browser), lalu BERHENTI dan
tunggu aku bilang "lanjut".

M0 — Setup
- Laravel 13 + MySQL (database: sigap_alkes), locale id, timezone Asia/Jakarta.
- Filament 5 panel di /admin sesuai design.md (warna, font, navigasi grup).
- Filament Shield + role: super_admin, admin_ipsrs, teknisi, kepala_ruangan, manajemen.
- spatie/laravel-activitylog, Pest, Pint.
- Seeder user demo satu per role (password: password).

M1 — Data master & inventaris (PRD F1)
- Migration, model, enum, Filament Resource: Room, EquipmentType, TestingInstitution, SparePart, Equipment.
- qr_token UUID, halaman publik /alat/{qr_token} sesuai design.md §4, cetak label QR PDF (single & bulk).
- Scope kepala_ruangan hanya melihat alat di ruangannya.

M2 — Kalibrasi, status, pengingat (PRD F2 & F4)
- Calibration Resource + relation manager di Equipment.
- EquipmentStatusService dengan seluruh tabel transisi PRD §F4 + status_histories.
- Command harian EvaluateEquipmentStatus dan SendCalibrationReminders (H-30, H-7, H-0; notifikasi database + email; tidak dobel).
- Halaman Jadwal Kalibrasi + ekspor.

M3 — Kerusakan, perbaikan, mutasi (PRD F3)
- DamageReport, Repair (+ suku cadang), EquipmentMovement, terhubung ke EquipmentStatusService.

M4 — Laporan & nilai aset (PRD F5 & F6)
- UptimeCalculator, ComplianceCalculator, DepreciationCalculator.
- Kartu Nilai Aset di detail alat, penanda "Pertimbangkan ganti" (setting default 50%), disclaimer.
- Dashboard sesuai design.md, halaman Laporan, ekspor Excel & PDF, exporter ASPAK (kolom sementara).

M5 — Seeder & finishing
- Seeder data sintetis: 6 ruangan, 10 jenis alat, ±60 alat dengan status bervariasi, riwayat kalibrasi & perbaikan 12 bulan terakhir. Tanpa data pasien.
- Cek ulang semua permission per role, lengkapi test, tulis README (cara install & akun demo).

Mulai dari M0. Sebelum menulis kode, tunjukkan rencana singkat M0 (langkah + package yang akan diinstal).
```
