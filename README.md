<p align="center">
  <img src="public/images/logo-its.png" alt="Logo ITS" width="90" />
</p>

<h1 align="center">🏥 SIGAP-Alkes</h1>

<p align="center">
  <strong>Sistem Informasi Operasional Inventaris, Kalibrasi, Pemeliharaan, dan Nilai Aset Alat Kesehatan Rumah Sakit</strong><br/>
  <em>Proyek Mata Kuliah Manajemen Data — Departemen Teknologi Kedokteran, Institut Teknologi Sepuluh Nopember (ITS) 2026</em>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-13.x-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 13" />
  <img src="https://img.shields.io/badge/Filament-5.x-0E7C7B?style=flat-square" alt="Filament 5" />
  <img src="https://img.shields.io/badge/PHP-%E2%89%A58.3-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.3+" />
  <img src="https://img.shields.io/badge/Tailwind_CSS-4.x-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white" alt="Tailwind 4" />
  <img src="https://img.shields.io/badge/Pest-4.x-2E9E6B?style=flat-square" alt="Pest" />
</p>

---

## 👋 Kenalan Dulu Sama SIGAP-Alkes!

Halo semuanya! Selamat datang di repositori **SIGAP-Alkes** ✨

Di banyak rumah sakit, pencatatan jadwal kalibrasi, riwayat perbaikan, sampai mutasi alat kesehatan (alkes) seringkali masih tersebar di file Excel atau buku manual. Akibatnya? Nggak jarang ada alat yang kelewat jadwal kalibrasinya karena nggak ada *reminder* otomatis, atau perawat di ruangan bingung apakah suatu alat masih **Laik Pakai** atau sebenarnya lagi bermasalah.

Nah, **SIGAP-Alkes** hadir buat menjawab *pain point* tersebut sebagai *operational companion* harian bagi tim **IPSRS (Instalasi Pemeliharaan Sarana Rumah Sakit)** dan unit **Elektromedis**. Catatan penting: aplikasi ini dirancang buat **melengkapi ASPAK**, bukan menggantikannya yaa!

### 🎯 *Goals* Utama Aplikasi Ini:
1. **Zero Overdue Calibration**: Nggak ada lagi ceritanya alkes telat dikalibrasi gara-gara lupa jadwal, soalnya ada pengingat otomatis (H-30, H-7, H-0).
2. **Instant Status Check via QR**: Cukup *scan* stiker QR di bodi alat pakai HP (tanpa perlu login!), siapa pun langsung tahu alat itu **Laik Pakai**, **Perlu Kalibrasi**, atau **Tidak Laik Pakai**.
3. **Single Source of Truth**: Riwayat kalibrasi, laporan kerusakan, perbaikan + suku cadang, sampai mutasi antar-ruangan tercatat rapi di satu tempat.
4. **Asset Valuation & Analytics**: Manajemen RS bisa langsung pantau *uptime* alat, persentase kepatuhan kalibrasi, sampai **nilai buku aset (penyusutan garis lurus)** secara *real-time*.

---

## 🛠️ Tech Stack yang Dipakai

Kita pakai *stack* modern yang bersih, cepat, dan *maintainable*:

| Komponen | Versi / Pilihan | Keterangan |
|---|---|---|
| **Backend Framework** | Laravel 13.x (PHP ≥ 8.3) | Fondasi utama aplikasi, timezone `Asia/Jakarta`, locale `id` |
| **Admin Panel & UI** | Filament 5.x + Livewire 4.x | Panel operasional di `/admin` dengan *custom theme* Tailwind CSS 4.x |
| **Role & Permission** | `bezhansalleh/filament-shield` 4.x | Berbasis `spatie/laravel-permission` (RBAC ketat per *role*) |
| **Audit Trail** | `spatie/laravel-activitylog` 5.x | Mencatat semua perubahan data penting secara otomatis |
| **QR Code Generator** | `chillerlan/php-qrcode` | Generate QR SVG tajam untuk halaman publik & cetak label |
| **PDF Engine** | `barryvdh/laravel-dompdf` | Cetak label QR ukuran 60×40 mm (satuan maupun massal di kertas A4) & laporan |
| **Database** | MySQL 8 / MariaDB | Penyimpanan relasional utama (nominal uang `decimal(15,2)` + `bcmath`) |
| **Testing & Code Style** | Pest 4.x & Laravel Pint | *Automated feature/unit testing* & standar kerapian kode |

---

## 👥 Pembagian Role & Hak Akses

Akses di dalam sistem diatur secara ketat lewat **Filament Shield** & **Laravel Policy** (jadi menu yang nggak relevan bakal otomatis disembunyikan, bukan cuma di-*disable*):

| Role (`slug`) | Pengguna | Hak Akses Utama |
|---|---|---|
| `super_admin` | Developer / Pengelola Sistem | Akses penuh ke seluruh modul, pengaturan pengguna, dan manajemen *role* Shield. |
| `admin_ipsrs` | Admin IPSRS | CRUD seluruh Data Master, Inventaris Alat, Akun Pengguna, serta cetak label QR PDF (satuan & massal). |
| `teknisi` | Teknisi Elektromedis | Melihat seluruh alat; mencatat hasil uji/kalibrasi, tindak lanjut perbaikan, dan mutasi alat. |
| `kepala_ruangan` | Kepala Ruangan / Perawat | **Hanya melihat alat yang berada di ruangannya sendiri** (`room_id`) dan membuat laporan kerusakan. |
| `manajemen` | Manajemen RS / Tim Akreditasi | Melihat *dashboard* eksekutif, analitik *uptime*, kepatuhan kalibrasi, nilai aset, dan ekspor laporan. |
| *Publik (Tanpa Login)* | Siapa pun yang *scan* QR | Hanya melihat halaman status publik `/alat/{qr_token}` (nama alat, ruangan, status, masa berlaku sertifikat). **Dijamin aman**, tidak membocorkan harga perolehan, nilai residu, maupun data internal! |

---

## ✨ Fitur-Fitur Utama & Aturan Bisnis

### 1. 📦 Inventaris Alat & Label QR (`F1`)
- Pencatatan komprehensif: kode inventaris unik, nama alat, jenis alat, ruangan, merek/tipe/nomor seri, tanggal & harga perolehan, nilai residu opsional, foto alat, serta catatan.
- Setiap alat otomatis memiliki `qr_token` berbasis **UUID** (nggak bisa ditebak secara urut) yang terhubung ke halaman status publik `/alat/{qr_token}`.
- **No Hard-Delete Policy**: Alat kesehatan tidak pernah dihapus permanen dari database demi menjaga keutuhan riwayat audit; alat yang dihapuskan cukup dinonaktifkan (`is_active = false`).
- **Cetak Label QR (PDF)**: Mendukung cetak satuan maupun *bulk action* dalam format hitam-putih 60 × 40 mm yang siap ditempel pada fisik alat.

### 2. 🔄 Otomatisasi Status Alat (`F4`)
Status alat **tidak pernah diubah manual secara sembarangan**, melainkan dikelola sepenuhnya lewat `EquipmentStatusService` dan selalu tercatat di tabel `status_histories`:
- 🟢 **`fit_for_use` (Laik pakai)**
- 🟡 **`calibration_due` (Perlu kalibrasi)** — otomatis aktif saat alat baru wajib kalibrasi belum punya sertifikat, mendekati H-30 jatuh tempo, selesai diperbaiki, atau habis dimutasi (untuk alat yang wajib instalasi ulang).
- 🔴 **`overdue` (Terlambat)** — melewati tanggal `valid_until` sertifikat kalibrasi.
- 🔵 **`under_testing` (Dalam uji & kalibrasi)** — sedang dalam proses pengujian/kalibrasi.
- ⚪ **`under_repair` (Dalam perbaikan)** — sedang ditangani teknisi.
- 🔴 **`unfit` (Tidak laik pakai)** — dinyatakan tidak lulus uji/kalibrasi.

> ⚠️ **Peringatan Keselamatan di Halaman Publik QR**: Jika alat berstatus **Terlambat** atau **Tidak laik pakai**, halaman scan QR di HP akan menampilkan *banner* merah tegas: *"Jangan gunakan alat ini. Hubungi IPSRS."*

### 3. 💰 Nilai Aset & Penyusutan Garis Lurus (`F6`)
Menggunakan metode **Garis Lurus (*Straight-Line Depreciation*)** dengan presisi `bcmath`:
- **Penyusutan per Tahun**: `P = (HP − NR) / MM`
- **Nilai Buku Saat Ini**: `NB = max(NR, HP − (P × n))`
  *(dengan `HP` = Harga Perolehan, `NR` = Nilai Residu, `MM` = Masa Manfaat dalam tahun, dan `n` = umur pakai berdasarkan bulan penuh sejak tanggal perolehan ÷ 12)*.
- Dilengkapi *disclaimer* transparan bahwa nilai buku pada sistem merupakan estimasi manajerial operasional dan bukan pengganti pembukuan akuntansi resmi RS.

---

## 🚀 Cara Instalasi & Menjalankan di Lokal

Pastikan di laptop/komputer kamu sudah terinstal **PHP ≥ 8.3**, **Composer**, **Node.js & NPM**, serta **MySQL / MariaDB** yaa!

```bash
# 1. Clone repositori ini
git clone https://github.com/tariqishuman/sigap-alkes.git
cd sigap-alkes

# 2. Instal dependensi PHP & JavaScript
composer install
npm install

# 3. Salin konfigurasi environment & generate app key
cp .env.example .env
php artisan key:generate

# 4. Sesuaikan konfigurasi DB_DATABASE, DB_USERNAME, dan DB_PASSWORD di file .env
#    Lalu jalankan migrasi database beserta seeder akun demo
php artisan migrate --seed

# 5. Buat symlink storage publik & build aset frontend (Tailwind 4 + Filament Theme)
php artisan storage:link
npm run build

# 6. Jalankan server development
php artisan serve
```

Setelah server jalan, langsung aja buka browser ke **`http://localhost:8000/admin`** 🎉

---

## 🔐 Akun Demo (Siap Pakai!)

Setelah menjalankan `php artisan migrate --seed`, kamu bisa langsung login menggunakan salah satu akun demo berikut (semua akun menggunakan kata sandi: **`password`**):

| Role | Email Login | Kata Sandi | Catatan |
|---|---|---|---|
| **Super Admin** | `superadmin@sigap-alkes.test` | `password` | Akses penuh semua modul & *Role Shield* |
| **Admin IPSRS** | `admin@sigap-alkes.test` | `password` | Kelola Data Master, Alat, Pengguna, & Cetak QR |
| **Teknisi Elektromedis** | `teknisi@sigap-alkes.test` | `password` | Lihat semua alat & operasional teknis |
| **Kepala Ruangan ICU** | `kepalaruangan@sigap-alkes.test` | `password` | Terhubung ke **Ruang ICU** (hanya melihat alat ICU) |
| **Manajemen RS** | `manajemen@sigap-alkes.test` | `password` | Dashboard & Laporan Eksekutif |

---

## 🧪 Menjalankan Pengujian (Testing) & Format Kode

Sebelum melakukan *commit* atau menyelesaikan *milestone*, pastikan seluruh pengujian otomatis hijau dan gaya penulisan kode sudah rapi:

```bash
# Menjalankan seluruh test suite dengan Pest
./vendor/bin/pest

# Merapikan format kode sesuai standar Laravel Pint
./vendor/bin/pint
```

---

## 🗺️ Peta Jalan Pengembangan (Milestones)

- [x] **M0 — Setup**: Inisialisasi Laravel 13, Filament 5 Panel (`/admin`), tema kustom Teal rumah sakit + Logo ITS, Filament Shield 5 *role*, Activity Log, dan Seeder akun demo.
- [x] **M1 — Data Master & Inventaris (`PRD F1`)**: CRUD Ruangan, Jenis Alat, Lembaga Penguji, Suku Cadang, Inventaris Alat (+ foto & kalkulasi nilai buku), halaman QR publik `/alat/{qr_token}`, cetak label QR PDF (satuan & massal), serta *scoping* khusus Kepala Ruangan.
- [x] **M2 — Kalibrasi, Status, & Pengingat (`PRD F2 & F4`)**: Pencatatan kalibrasi, matriks transisi penuh `EquipmentStatusService`, scheduler harian evaluasi status (`sigap:evaluate-equipment-status`) & pengingat H-30/H-7/H-0 (`sigap:send-calibration-reminders`), serta halaman Jadwal Kalibrasi + ekspor.
- [ ] **M3 — Kerusakan, Perbaikan, & Mutasi (`PRD F3`)**: Laporan kerusakan oleh Kepala Ruangan, tindak lanjut perbaikan + pemakaian suku cadang oleh Teknisi, dan mutasi ruangan alat.
- [ ] **M4 — Laporan & Nilai Aset (`PRD F5 & F6`)**: Kalkulator *Uptime* & Kepatuhan Kalibrasi, indikator *"Pertimbangkan ganti"*, *Dashboard* grafik & statistik lengkap, serta ekspor Excel/PDF & ASPAK.
- [ ] **M5 — Seeder Sintetis & Finishing**: Seeder data sintetis lengkap (6 ruangan, 10 jenis alat, ±60 alat dengan riwayat 12 bulan terakhir tanpa data pasien) & finalisasi menyeluruh.
- [ ] **M6 — Integrasi Interoperabilitas HL7 FHIR R4 *(Future Roadmap / Todo)***: Pemetaan entitas operasional non-pasien ke resource standar HL7 FHIR R4 (`Equipment` $\rightarrow$ `Device`, `Room` $\rightarrow$ `Location`, `Calibration` $\rightarrow$ `DeviceMetric`, `TestingInstitution` $\rightarrow$ `Organization`), penyediaan REST API endpoint `/api/fhir/r4/*`, serta sinkronisasi status kelaikan alat ke SIMRS / SatuSehat.

---

<p align="center">
  Dibuat dengan penuh semangat oleh <strong>Kelompok 3 — Manajemen Data</strong><br/>
  Departemen Teknologi Kedokteran, Fakultas Teknologi Elektro dan Informatika Cerdas (FTEIC)<br/>
  <strong>Institut Teknologi Sepuluh Nopember (ITS) Surabaya — 2026</strong> 🐊
</p>
