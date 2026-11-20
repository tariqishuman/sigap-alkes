# Assumptions — SIGAP-Alkes

Dokumen ini mencatat asumsi teknis dan bisnis yang diambil selama pengembangan per milestone sesuai aturan di `AGENTS.md`.

## M0 — Setup
1. **Sumber Kebenaran Role & Otorisasi**:
   - Spatie Permission + Filament Shield (`hasRole`, `hasAnyRole`, dan `$user->can(...)` via Policy) adalah **satu-satunya sumber kebenaran** untuk pengecekan hak akses.
   - Tabel `users` tidak menyimpan kolom `role`. Enum `App\Enums\UserRole` hanya digunakan untuk konstanta string nama role, label bahasa Indonesia, warna badge Filament (`HasLabel`, `HasColor`), serta seeding.
2. **Email Akun Demo per Role** (kata sandi seluruh akun: `password`):
   - `super_admin`: `superadmin@sigap-alkes.test`
   - `admin_ipsrs`: `admin@sigap-alkes.test`
   - `teknisi`: `teknisi@sigap-alkes.test`
   - `kepala_ruangan`: `kepalaruangan@sigap-alkes.test` (terhubung ke `Ruang ICU`)
   - `manajemen`: `manajemen@sigap-alkes.test`
3. **Manajemen Pengguna (`UserResource`) & Role (`RoleResource`)**:
   - `UserResource` berada di grup navigasi `Pengaturan` dengan label `Pengguna`, dapat diakses oleh `super_admin` dan `admin_ipsrs`.
   - `RoleResource` (Filament Shield) berada di grup navigasi `Pengaturan` dengan label `Role`, hanya dapat diakses oleh `super_admin`.

## M1 — Data Master & Inventaris (PRD F1)
1. **Status Awal Alat Baru (`EquipmentStatusService::initializeStatus`)**:
   - Saat alat baru dibuat, status awal ditentukan secara otomatis oleh `EquipmentStatusService` berdasarkan `equipmentType->requires_calibration`:
     - Jika `requires_calibration = true` -> `calibration_due` (*Perlu kalibrasi*).
     - Jika `requires_calibration = false` -> `fit_for_use` (*Laik pakai*).
   - Perubahan status awal ini langsung dicatat ke tabel `status_histories`.
2. **Penghapusan Alat (Soft Deactivation)**:
   - Sesuai aturan wajib #8 (`AGENTS.md`), alat tidak pernah di-hard delete (`EquipmentPolicy::delete` dan `deleteAny` selalu mengembalikan `false`). Penonaktifan dilakukan melalui aksi **Nonaktifkan** yang mengubah `is_active = false`.
3. **Hak Akses Resource M1 per Role (`PRD.md` §3)**:
   - `super_admin` & `admin_ipsrs`: CRUD penuh pada `Room`, `EquipmentType`, `TestingInstitution`, `SparePart`, dan `Equipment` (termasuk cetak label QR PDF satuan & massal).
   - `teknisi`: Hanya melihat daftar dan detail seluruh alat (`ViewAny:Equipment`, `View:Equipment`); tidak memiliki akses ke Data Master maupun tambah/ubah alat (mendapat HTTP 403).
   - `kepala_ruangan`: Hanya melihat daftar dan detail alat yang berada di `room_id` miliknya (`EquipmentResource::getEloquentQuery()` dan `EquipmentPolicy::view()`); mendapat HTTP 403 bila mengakses alat ruangan lain atau Data Master.
   - `manajemen`: Tidak memiliki akses ke halaman CRUD Data Master maupun kelola alat (mendapat HTTP 403).

## M2 — Kalibrasi, Status, Pengingat (PRD F2 & F4)
1. **Kalibrasi ulang boleh kapan saja**: `CalibrationService::record` menerima alat aktif berstatus *Laik pakai* (termasuk sebelum H-30), *Perlu kalibrasi* (termasuk setelah perbaikan), *Terlambat*, atau *Dalam uji & kalibrasi*. Bila alat belum ditandai "mulai diuji", status dilewatkan ke *Dalam uji & kalibrasi* lebih dulu supaya tetap mengikuti tabel §F4 (tercatat dua baris riwayat). Alat *Dalam perbaikan* dan *Tidak laik pakai* tetap ditolak: alat yang gagal uji harus diperbaiki dulu sebelum diuji lagi.
2. **Aturan validasi tambahan di luar PRD**: `valid_until` harus setelah `tested_at`, dan saat pencatatan baru tidak boleh sudah lewat, agar sertifikat kedaluwarsa tidak menampilkan alat sebagai *Laik pakai*. Hasil *tidak lulus* tidak menyimpan nomor sertifikat, `valid_until`, maupun file.
3. **Kalibrasi tidak bisa dihapus** (PRD §F2): `CalibrationPolicy` tidak menyediakan aksi hapus karena kalibrasi menjadi dasar riwayat status. Koreksi lewat Ubah; alat dan hasil terkunci karena sudah memicu transisi.
4. **Hak akses kalibrasi**: `teknisi` lihat, catat, dan ubah. `admin_ipsrs` dan `manajemen` hanya melihat daftar serta Jadwal Kalibrasi dan bisa mengekspor (PRD §3). `kepala_ruangan` tidak punya akses.
5. **Pengingat memakai ambang, bukan tanggal persis**: tiap hari dikirim pengingat paling mendesak yang berlaku (sisa ≤30 → H-30, ≤7 → H-7, ≤0 → H-0), sekali per jenis per `valid_until`. Bila scheduler sempat mati, pengingat tetap terkirim saat berjalan lagi, dan jenis yang sudah lewat tidak dikirim. Sertifikat baru memulai siklus baru.
6. **Alat yang tidak diberi pengingat**: alat nonaktif, tanpa sertifikat, jenis tidak wajib kalibrasi, serta yang berstatus *Dalam uji & kalibrasi*, *Dalam perbaikan*, atau *Tidak laik pakai* karena sedang ditangani.
7. **File sertifikat disimpan di disk privat** (`local`) dan hanya bisa diunduh lewat aksi tabel setelah login.
8. **Alat tanpa sertifikat tetap muncul di Jadwal Kalibrasi**, di urutan paling atas, karena justru paling perlu diajukan. Filter bulan menyembunyikannya.
9. **Jadwal scheduler**: evaluasi status 00.10 WIB, pengingat 07.00 WIB.
10. **Sertifikat terbaru menjadi acuan**: yang dipakai adalah kalibrasi lulus dengan `tested_at` paling akhir, walaupun `valid_until`-nya lebih cepat dari sertifikat sebelumnya. Pengingat untuk sertifikat lama berhenti karena siklusnya mengikuti `valid_until` sertifikat terbaru. Hasil *tidak lulus* tidak mengubah acuan, tetapi status tetap menjadi *Tidak laik pakai*.
11. **Perbaikan selesai** (PRD §F4): perbaikan boleh dimulai juga dari *Perlu kalibrasi* dan *Terlambat*. Jenis wajib kalibrasi tidak pernah langsung *Laik pakai*: status menjadi *Perlu kalibrasi* lalu langsung dievaluasi dengan aturan yang sama dengan `equipment:evaluate-status`, sehingga menjadi *Terlambat* bila sertifikat sudah lewat, tercatat dua baris riwayat. Jenis tidak wajib kalibrasi tetap kembali *Laik pakai*.
12. **Sertifikat pada jenis tidak wajib kalibrasi** boleh dicatat, tetapi tidak memengaruhi status saat masa berlakunya lewat dan tidak memicu pengingat.

## M3 — Kerusakan, Perbaikan, Mutasi (PRD F3)
1. **Pelapor laporan kerusakan**: `kepala_ruangan` hanya boleh membuat dan melihat laporan kerusakan untuk alat di ruangannya sendiri (`room_id`). `teknisi`, `admin_ipsrs`, dan `super_admin` juga boleh membuat laporan kerusakan untuk alat aktif mana pun (misalnya tindak lanjut gagal kalibrasi).
2. **Laporan tidak mengubah status alat**: Pembuatan `damage_reports` tidak mengubah status alat. Status alat baru berubah menjadi *Dalam perbaikan* saat teknisi memulai perbaikan (`EquipmentStatusService::startRepair`).
3. **Siklus status laporan kerusakan**: `open` (*Terbuka*) → `in_progress` (*Sedang diperbaiki*, saat perbaikan dimulai) → `resolved` (*Selesai*, saat perbaikan selesai).
4. **Notifikasi laporan baru**: Setiap laporan kerusakan baru mengirim notifikasi database Filament (`DamageReportedNotification`) kepada seluruh `teknisi` dan `admin_ipsrs`.
5. **Pengeditan & larangan hapus laporan**: Selama status masih `open`, hanya pelapor (`reported_by`) yang dapat mengubah keluhan. Laporan kerusakan tidak dapat dihapus (`DamageReportPolicy::delete/deleteAny` selalu `false`).
6. **Relasi perbaikan & batas satu perbaikan aktif**: Setiap perbaikan selalu berasal dari satu laporan kerusakan (`damage_report_id` unik). Satu alat hanya boleh memiliki maksimal satu perbaikan aktif (`finished_at IS NULL`) pada satu waktu.
7. **Transisi status perbaikan terpusat**: Mulai perbaikan wajib melalui `EquipmentStatusService::startRepair` (ke *Dalam perbaikan*), dan selesai perbaikan wajib melalui `EquipmentStatusService::finishRepair` (aturan M2: jenis wajib kalibrasi → *Perlu kalibrasi* lalu dievaluasi ke *Terlambat* jika sertifikat lewat; jenis tidak wajib kalibrasi → *Laik pakai*).
8. **Suku cadang & snapshot harga**: Suku cadang dipilih dari master `spare_parts`, dan `unit_price` disimpan per baris di `repair_spare_part` sebagai snapshot harga saat perbaikan berlangsung.
9. **Kalkulasi biaya perbaikan**: Total biaya satu perbaikan = `labor_cost + Σ(quantity × unit_price)` dihitung memakai `bcmath` dalam format `decimal(15,2)`. Model `Equipment::totalRepairCost()` menyediakan akumulasi biaya perbaikan kumulatif untuk dipakai di detail alat dan penanda *"Pertimbangkan ganti"* pada M4.
10. **Validasi & penguncian perbaikan selesai**: `finished_at` wajib `>= started_at` dan tindakan perbaikan wajib diisi saat menyelesaikan perbaikan. Perbaikan yang sudah selesai (`finished_at != null`) terkunci dan tidak dapat diedit maupun dihapus.
11. **Mutasi alat & ruangan asal otomatis**: `from_room_id` selalu diisi otomatis dari `equipment.room_id` saat ini. Pemindahan ke ruangan yang sama ditolak.
12. **Transaksi atomik mutasi**: Pencatatan `equipment_movements`, pembaruan `equipment.room_id`, dan evaluasi status oleh `EquipmentStatusService::handleMovement()` dijalankan dalam satu `DB::transaction` dengan `lockForUpdate()`.
13. **Dampak mutasi pada status**: Jika jenis alat `requires_installation = true` atau `is_reinstallation` dicentang, dan status alat saat ini *Laik pakai*, status berubah menjadi *Perlu kalibrasi*. Status selain *Laik pakai* tidak berubah.
14. **Mutasi tidak bisa diubah/dihapus**: `EquipmentMovementPolicy` menonaktifkan aksi edit dan hapus karena mutasi merupakan rekam jejak audit lokasi aset.
15. **Halaman QR publik**: Tetap hanya menampilkan identitas alat, lokasi, status kelaikan, dan tanggal berlaku sertifikat — tidak pernah membocorkan biaya perbaikan, nama teknisi, atau isi keluhan kerusakan.

## M4 — Laporan, Nilai Aset, & Dashboard (PRD F5, F6, §5)
1. **Downtime untuk Uptime (`UptimeCalculator`)**: Hanya status `under_repair` dan `unfit` yang dihitung sebagai downtime (`D`). Status `under_testing` tetap dihitung sebagai uptime.
2. **Batas waktu periode `T`**: Untuk tiap alat aktif, `T` dimulai dari yang paling akhir antara awal periode dan `equipment.created_at`. Untuk bulan berjalan, `T` dihitung sampai waktu sekarang (`now()`). Alat nonaktif (`is_active = false`) tidak dimasukkan dalam perhitungan.
3. **Agregasi Uptime**: Uptime per ruangan dan per bulan dihitung dengan rumus gabungan `(ΣT − ΣD) / ΣT × 100%`, bukan rata-rata persentase per alat.
4. **Keterbatasan mutasi tengah bulan**: Alat yang dimutasi ke ruangan lain di tengah bulan dihitung ke `room_id` alat saat ini (*known limitation* untuk versi ini).
5. **Kepatuhan kalibrasi (`ComplianceCalculator`)**: Berupa *snapshot* hari ini (`K = Ns / Nw × 100%`), tanpa riwayat kepatuhan historis per bulan. Jika `Nw = 0`, UI menampilkan `"–"` dan tidak melakukan pembagian dengan nol.
6. **Nilai aset & penanda "Pertimbangkan ganti"**: Menggunakan `DepreciationCalculator` dari M1. Penanda *"Pertimbangkan ganti"* muncul bila total biaya perbaikan kumulatif (`Equipment::totalRepairCost()`) ≥ `X% × nilai buku`, dibandingkan menggunakan perkalian `bcmath` (`repairCost × 100 >= bookValue × X`) tanpa pembagian. Nilai `X` dibaca dari `config/sigap.php` (`SIGAP_REPLACE_THRESHOLD_PERCENT`, default `50`) tanpa tabel settings.
7. **Visibilitas Kartu Nilai Aset**: Kartu *Nilai Aset* di detail alat (beserta disclaimer PRD §F6) disembunyikan dari `kepala_ruangan` dan tidak pernah tampil di halaman QR publik.
8. **Dashboard**: Widget statistik, chart uptime 12 bulan, tabel 10 jatuh tempo terdekat, dan chart total nilai buku per ruangan hanya tampil untuk `super_admin`, `admin_ipsrs`, dan `manajemen`.
9. **Format ekspor ASPAK sementara**: `AspakEquipmentExporter` menggunakan 12 kolom sementara (kode inventaris, nama alat, jenis, merek, tipe/model, nomor seri, ruangan, tahun perolehan, harga perolehan, kondisi dari status, tanggal kalibrasi terakhir, berlaku s.d.) dan diberi penanda eksplisit di UI bahwa format kolom bersifat sementara menunggu konfirmasi RS mitra.

## M5 — Seeder Sintetis & Finishing UI
1. **Empty state panel notifikasi Filament**: Filament 5 tidak menyediakan hook empty-state khusus pada `DatabaseNotifications` tanpa mem-publish dan meng-override penuh view vendor `database-notifications.blade.php`. Sesuai aturan `design.md` (hindari override komponen besar Filament), ilustrasi `empty-notifications.svg` dilewati pada panel notifikasi bawaan.

## R1 — Revisi Kelompok Sebelum M6
1. **Teks UI production-ready**: Seluruh label, subheading, dan helper text di panel Filament serta PDF dibersihkan dari rumus, simbol matematika (`Σ`, `×`, `÷`, `≥`, `≤`), referensi `PRD`/`§`, dan istilah internal; rumus tetap didokumentasikan di `PRD.md` dan `README.md`.
2. **Buku stok suku cadang (`SparePartStockService`)**: Kolom `spare_parts.stock` adalah saldo terakhir (tidak di-mass-assign) dan selalu diubah bersama pencatatan `spare_part_movements` (`in`, `out`, `return`, `adjustment`) dalam satu `DB::transaction` dengan `lockForUpdate()`. Stok tidak pernah negatif; pemakaian melebihi stok ditolak dengan pesan `"Stok tidak cukup (tersisa N)"`. Saat perbaikan aktif mengubah/menghapus baris suku cadang, hanya selisihnya yang dicatat (`out`/`return`); setelah perbaikan selesai, baris suku cadang terkunci. Penurunan stok ke `<= min_stock` mengirim notifikasi database ke `admin_ipsrs`. `teknisi` hanya bisa melihat stok (`ViewAny`/`View`), tidak bisa menambah atau menyesuaikan.
3. **Penyusutan bulanan**: Penyusutan dihitung bulanan `(HP - NR) / (MM * 12)` dan nilai buku dihitung langsung dari `HP - ((HP - NR) * n / (MM * 12))` berbasis `bcmath` (presisi 8 desimal, dibulatkan *half-up* 2 desimal di akhir) agar bebas selisih pembulatan. UI Kartu Nilai Aset dan Laporan Nilai Aset menampilkan *"Penyusutan per Bulan"*.
4. **Role `badan_kalibrasi`**: Terhubung ke `users.testing_institution_id` (wajib diisi). Dapat melihat daftar alat & Jadwal Kalibrasi (tanpa harga/nilai aset/biaya), serta mencatat & mengubah kalibrasi **hanya milik lembaganya sendiri** (divalidasi di `CalibrationService`, `CalibrationPolicy`, dan dikunci di `CalibrationForm`). Tidak dapat mengakses laporan kerusakan, perbaikan, mutasi, suku cadang, laporan, dashboard keuangan, atau pengguna.
5. **Role `magang`**: Dapat melihat alat, kalibrasi, laporan kerusakan, perbaikan, dan mutasi di semua ruangan **tanpa harga, biaya, nilai aset, atau stok**, serta dapat membuat laporan kerusakan untuk alat di ruangan mana saja (tidak dapat mengedit/menghapus data lain).
6. **Kartu halaman error**: Gaya `.card` pada `resources/views/errors/layout.blade.php` dikembalikan memiliki latar putih, border, radius, dan shadow kartu sesuai commit `ebbdab3`.

## Pengaturan Aplikasi (`app-settings`)
1. **Tabel `settings` + `SettingsService` (tanpa package tambahan)**: Dipilih tabel `settings` (`key`, `value` json, `is_encrypted`) + `SettingsService` dengan cache dibanding menambah `filament/spatie-laravel-settings-plugin`, supaya tetap mematuhi daftar package di `AGENTS.md`, mendukung enkripsi kredensial (`Crypt::encryptString`) tanpa pernah masuk `activity_log`, serta aman memakai `try/catch` fallback ketika database tidak tersedia.
2. **Keamanan Client Secret SatuSehat**: `satusehat.client_secret` selalu disimpan terenkripsi (`is_encrypted = true`), tidak pernah dimuat kembali ke form (hanya placeholder `••••`, isi baru untuk mengganti), dan tombol *Tes koneksi* tidak pernah menampilkan atau mencatat access token.
3. **Ketahanan halaman error**: `resources/views/errors/layout.blade.php` membaca nama aplikasi, logo, dan favicon dari `SettingsService` di dalam blok `try/catch` dengan nilai bawaan (`SIGAP-Alkes`, `images/logo-its.png`, `images/brand/favicon.svg`) agar halaman 500/503 tetap ter-render saat koneksi database mati.
4. **Ambang "Pertimbangkan ganti"**: Dipindahkan ke pengaturan `asset.replace_threshold_percent` dengan `config('sigap.replace_threshold_percent')` (`SIGAP_REPLACE_THRESHOLD_PERCENT`, default `50`) sebagai nilai awal bawaan.

## UI Revamp v2 (`ui-revamp-v2`) — Tahap A
1. **Topbar & Sidebar tanpa override view vendor (§8)**: Tombol split **"+ Tambah"** beserta nama + label role ditempatkan melalui render hook `PanelsRenderHook::GLOBAL_SEARCH_AFTER`, dan footer sidebar (**Pengaturan** + **Bantuan**) melalui `PanelsRenderHook::SIDEBAR_FOOTER`, sehingga tidak ada view vendor Filament yang di-publish/override.
2. **Status badge terpadu**: Seluruh kolom dan entri status `EquipmentStatus` menggunakan komponen `<x-sigap.status-badge>` melalui `ViewColumn`/`ViewEntry` (`components.sigap.table-status-badge` & `components.sigap.infolist-status-badge`) agar warna, tint, titik penanda, dan tipografi 100% mengikuti tabel `design.md` §1.2.







## UI Revamp v2 (ui-revamp-v2) — Tahap B

- **Modal Bantuan Cepat**: Memakai `<template x-teleport="body">` di `resources/views/filament/sidebar/footer.blade.php` agar modal tampil terpusat di viewport dan tidak terpotong batas lebar sidebar 248px.
- **Tata letak 4 baris & gauge 2-baris**: Baris 1–2 (5 kartu KPI + gauge Uptime yang membentang 2 baris di kanan) dibungkus dalam satu `ExecutiveTopMetricsWidget` (12-kolom CSS Grid) agar `row-span-2` presisi di desktop tanpa override layout Filament.
- **Grafik stacked bar Kerusakan**: Porsi "Masih terbuka" memakai warna `aqua` (`#5CC8C4`) polos di Chart.js (sesuai opsi resmi di `design.md` §3.8) agar tidak perlu menambah library eksternal.
- **Target uptime**: Ditambahkan sebagai pengaturan baru `asset.uptime_target_percent` (default `95%`) di Tab Nilai Aset pada Pengaturan Aplikasi.
- **Cache dashboard**: Semua agregasi di `DashboardMetricsService` di-cache 10 menit per kombinasi filter (`period`, `date`, `room_id`, `group_by`).

### Perbaikan Dashboard Tahap B
- **Sumber tunggal tren historis Kepatuhan & Terlambat**: Dihitung dari sertifikat kalibrasi lulus (`calibrations.result = pass` dengan `tested_at <= akhir bulan` dan `valid_until` dibandingkan terhadap akhir bulan) untuk 12 titik akhir bulan. Dipilih karena seeder tidak menulis snapshot `status_histories` harian untuk bulan lampau, sehingga sertifikat menjadi sumber data historis yang akurat dan konsisten antara Kepatuhan dan Terlambat.
- **Filter dashboard ringkas**: Kartu `filtersForm` besar dihapus dan diganti 3 tombol pill sejajar di kanan header halaman (`[📅 1 – 30 Nov 2026 | Bulanan]`, `[Semua ruangan]`, `[Ekspor]`) via `HasFiltersAction` + modal kecil Filament 5.

## UI Revamp v2 (ui-revamp-v2) — Tahap C

- **Login (§4.9)**: Tetap menggunakan `PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE` dan styling `.fi-simple-layout` / `.fi-simple-main` di `theme.css` (Plus Jakarta Sans, kanvas `#F4F7F7` + `pattern.svg`, ilustrasi `illustrations/login.svg` tanpa modifikasi SVG) agar tidak perlu override view vendor login Filament.
- **Detail Alat (§4.3)**: Kartu identitas (foto 96px, chip kode, badge status `lg`, QR + tautan publik) dan Strip KPI (4 kartu untuk role berizin biaya, 2 kartu untuk role lainnya) dirender via `ViewEntry` di `EquipmentInfolist`; penanda "Pertimbangkan ganti" ditampilkan sebagai banner tint kuning di dalam kartu Nilai Aset.
- **Jadwal Kalibrasi (§4.4)**: Default menampilkan 4 kelompok ber-aksen (`Terlambat`, `Minggu ini`, `Bulan ini`, `Berikutnya`) dengan toggle ke tampilan tabel & ekspor.
- **Pengaturan Aplikasi (§4.8)**: Menggunakan tab vertikal (`Tabs::make()->vertical()`) dan chip status koneksi SatuSehat (`Terhubung` / `Gagal` / `Belum diatur`) beserta waktu cek terakhir yang tersimpan di `settings`.
