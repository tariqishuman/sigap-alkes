# PRD — SIGAP-Alkes

Sistem informasi operasional inventaris, kalibrasi, pemeliharaan, dan nilai aset alat kesehatan rumah sakit.
Sumber: proposal Kelompok 3, mata kuliah Manajemen Data, Teknologi Kedokteran ITS (2026).

---

## 1. Latar belakang singkat

- Alat kesehatan wajib diuji/dikalibrasi berkala supaya tetap laik pakai.
- Di banyak RS, jadwal kalibrasi dan riwayat perbaikan masih di Excel/buku, tersebar, dan tanpa pengingat.
- SIGAP-Alkes dipakai harian oleh IPSRS dan unit elektromedis. Aplikasi ini **melengkapi ASPAK**, bukan menggantikannya.

## 2. Tujuan produk

1. Tidak ada alat yang terlambat dikalibrasi di unit uji coba.
2. Status laik pakai setiap alat bisa dicek dengan scan QR.
3. Riwayat kalibrasi, kerusakan, perbaikan, dan pemindahan alat tersimpan di satu tempat.
4. Manajemen punya data uptime, kepatuhan kalibrasi, dan **nilai aset (penyusutan)** tiap alat.

## 3. Pengguna dan role

| Role (slug) | Siapa | Hak akses utama |
|---|---|---|
| `super_admin` | Developer/pengelola sistem | Semua |
| `admin_ipsrs` | Admin IPSRS | CRUD semua data master, alat, akun pengguna; cetak label QR |
| `teknisi` | Teknisi elektromedis | Lihat semua alat; lihat, catat, dan ubah kalibrasi (tanpa hapus); CRUD perbaikan dan mutasi alat |
| `kepala_ruangan` | Kepala ruangan / perawat | Lihat alat **di ruangannya saja**; buat laporan kerusakan |
| `manajemen` | Manajemen RS / tim akreditasi | Lihat dashboard dan laporan; ekspor |
| (publik, tanpa login) | Siapa pun yang scan QR | Hanya halaman status alat (nama, ruangan, status, berlaku s.d.) |

Role dikelola dengan **Filament Shield** (spatie/laravel-permission).

## 4. Fitur inti (6 fitur)

### F1. Inventaris alat
- CRUD alat: kode inventaris (unik), nama, jenis alat, ruangan, merek, tipe/model, nomor seri, tanggal perolehan, **harga perolehan**, nilai residu (opsional), foto, catatan, aktif/nonaktif.
- Setiap alat punya `qr_token` (UUID, unik, tidak bisa ditebak) untuk tautan halaman status publik.
- Aksi **cetak label QR** (PDF) per alat dan massal (bulk action).
- Alat dihapuskan = dinonaktifkan (`is_active = false`), riwayat tetap disimpan. Tidak ada hard delete untuk alat.

**Acceptance criteria**
- Kode inventaris dan `qr_token` tidak boleh ganda.
- Alat baru dengan jenis wajib kalibrasi dan belum punya sertifikat otomatis berstatus *Perlu kalibrasi*.

### F2. Jadwal dan pengingat kalibrasi
- Catat hasil uji/kalibrasi: alat, lembaga penguji, tanggal uji, hasil (lulus/tidak lulus), nomor sertifikat, berlaku sampai, file sertifikat (PDF).
- Kalibrasi ulang boleh dilakukan kapan saja, termasuk sebelum H-30 dan setelah perbaikan, selama alat aktif dan tidak sedang *Dalam perbaikan* atau *Tidak laik pakai*.
- Kalibrasi **tidak bisa dihapus**, hanya bisa diedit. Saat diedit, alat dan hasil terkunci karena sudah memicu perubahan status.
- Jatuh tempo = `valid_until` dari kalibrasi lulus terbaru (tanggal uji paling akhir). Sertifikat terbaru ini yang menjadi acuan status dan pengingat, walaupun masa berlakunya lebih pendek dari sertifikat sebelumnya. Interval bawaan diambil dari jenis alat (`calibration_interval_months`, default 12) dan bisa diubah admin.
- Scheduler harian mengirim pengingat **H-30, H-7, dan H-0** ke teknisi dan admin IPSRS lewat **notifikasi database Filament + email**. Satu pengingat hanya terkirim sekali per siklus.
- Halaman **Jadwal Kalibrasi**: daftar alat jatuh tempo per bulan, bisa difilter dan diekspor (untuk diajukan ke BPAFK/lab).

**Aturan validasi**
- `tested_at` ≤ hari ini.
- Hasil *lulus* wajib punya nomor sertifikat, lembaga penguji, dan `valid_until`.
- `valid_until` ≤ `tested_at` + interval jenis alat.

### F3. Riwayat perbaikan
- Kepala ruangan membuat **laporan kerusakan** (alat, keluhan, waktu lapor).
- Teknisi menindaklanjuti dengan **perbaikan**: tindakan, waktu mulai, waktu selesai, biaya jasa, suku cadang (nama, jumlah, harga satuan).
- **Mutasi alat**: catat pindah ruangan (dari, ke, tanggal, oleh siapa).

**Aturan validasi**
- `finished_at` ≥ `started_at`.
- Biaya perbaikan total = biaya jasa + Σ(jumlah × harga satuan suku cadang).

### F4. Status alat (otomatis)
Enam status:

| Status (enum value) | Label UI | Warna |
|---|---|---|
| `fit_for_use` | Laik pakai | success (hijau) |
| `calibration_due` | Perlu kalibrasi | warning (kuning) |
| `overdue` | Terlambat | danger (merah) |
| `under_testing` | Dalam uji & kalibrasi | info (biru) |
| `under_repair` | Dalam perbaikan | gray |
| `unfit` | Tidak laik pakai | danger (merah) |

Aturan transisi (semua perubahan lewat satu service dan dicatat di `status_histories`):

| Dari | Pemicu | Ke |
|---|---|---|
| Laik pakai | Hari ini ≥ `valid_until` − 30 hari | Perlu kalibrasi |
| Laik pakai | Mutasi alat yang jenisnya `requires_installation = true`, atau reinstalasi | Perlu kalibrasi |
| (alat baru) | Jenis wajib kalibrasi, belum ada sertifikat | Perlu kalibrasi |
| Laik pakai / Tidak laik pakai / Perlu kalibrasi / Terlambat | Perbaikan dimulai | Dalam perbaikan |
| Dalam perbaikan | Perbaikan selesai, jenis wajib kalibrasi | Hasil evaluasi kalibrasi: Perlu kalibrasi, atau Terlambat jika hari ini > `valid_until` |
| Dalam perbaikan | Perbaikan selesai, jenis **tidak** wajib kalibrasi | Laik pakai |
| Perlu kalibrasi | Hari ini > `valid_until` | Terlambat |
| Laik pakai / Perlu kalibrasi / Terlambat | Alat dikirim / mulai diuji | Dalam uji & kalibrasi |
| Dalam uji & kalibrasi | Hasil lulus | Laik pakai |
| Dalam uji & kalibrasi | Hasil tidak lulus | Tidak laik pakai |

Prinsip: **alat hanya bisa kembali Laik pakai lewat uji & kalibrasi** (kecuali jenis yang tidak wajib kalibrasi).

Halaman publik `/alat/{qr_token}`: tampilkan nama alat, ruangan, status (badge warna), sertifikat berlaku s.d. Tanpa login. Jangan tampilkan biaya, harga, nama teknisi, atau data akun.

### F5. Laporan uptime dan kepatuhan kalibrasi
- **Uptime** per alat / ruangan / bulan:
  `U = (T − D) / T × 100%`
  T = total jam periode, D = jam alat berstatus `under_repair` atau `unfit` dalam periode (dihitung dari `status_histories`).
- **Kepatuhan kalibrasi**:
  `K = Ns / Nw × 100%`
  Nw = alat aktif yang jenisnya wajib kalibrasi, Ns = yang punya kalibrasi lulus dengan `valid_until` ≥ hari ini.
- Ekspor laporan ke **Excel (xlsx)** dan **PDF**.

### F6. Nilai aset (penyusutan) — *permintaan dosen*
- Metode **garis lurus**:
  - Penyusutan per tahun: `P = (HP − NR) / MM`
  - Nilai buku: `NB = HP − (P × n)`, dengan batas bawah `NB ≥ NR`
  - HP = harga perolehan, NR = nilai residu, MM = masa manfaat (tahun), n = umur pakai (tahun, dihitung dari bulan penuh sejak `acquisition_date` ÷ 12).
- `useful_life_years` dan `residual_value_percent` disimpan per **jenis alat** (bisa diubah admin). Alat boleh override nilai residu sendiri (`residual_value`).
- Ditampilkan di halaman detail alat: harga perolehan, penyusutan per tahun, akumulasi penyusutan, nilai buku saat ini, total biaya perbaikan kumulatif.
- Penanda **"Pertimbangkan ganti"** jika total biaya perbaikan ≥ X% nilai buku (X = setting, default 50%). Ini hanya penanda bantu, bukan keputusan.
- Widget dashboard: total nilai buku aset per ruangan.
- Disclaimer di UI: *"Nilai buku adalah estimasi untuk kebutuhan manajemen, bukan pengganti pembukuan akuntansi resmi RS."*

**Test case wajib**: HP = 30.000.000, NR = 3.000.000, MM = 5 → P = 5.400.000/tahun; setelah 36 bulan NB = 13.800.000; setelah 72 bulan NB = 3.000.000.

## 5. Dashboard (manajemen & admin)
- Stat: jumlah alat aktif, % kepatuhan kalibrasi, alat terlambat, alat jatuh tempo ≤ 30 hari, total nilai buku aset.
- Chart: uptime rata-rata per bulan (12 bulan terakhir).
- Tabel: 10 alat dengan jatuh tempo terdekat.

## 6. Model data

Nama tabel/kolom pakai **bahasa Inggris** (konvensi Laravel). Label UI pakai **bahasa Indonesia**.

| Tabel | Padanan di proposal | Kolom utama |
|---|---|---|
| `rooms` | ruangan | name, building, floor |
| `equipment_types` | jenis_alat | name, requires_calibration, requires_installation, calibration_interval_months (default 12), useful_life_years, residual_value_percent |
| `testing_institutions` | lembaga_penguji | name, kind (bpafk / swasta / internal_rs), accreditation_number |
| `equipment` | alat | inventory_code (unique), name, equipment_type_id, room_id, brand, model, serial_number, acquisition_date, acquisition_cost decimal(15,2), residual_value decimal(15,2) nullable, status (enum), qr_token (uuid unique), photo_path, is_active, notes |
| `calibrations` | kalibrasi | equipment_id, testing_institution_id, recorded_by (user), tested_at, result (pass/fail), certificate_number, valid_until, certificate_path |
| `damage_reports` | laporan_kerusakan | equipment_id, reported_by, reported_at, complaint, status (open/in_progress/resolved) |
| `repairs` | perbaikan | damage_report_id, technician_id, action, started_at, finished_at, labor_cost |
| `spare_parts` | suku_cadang | name, unit |
| `repair_spare_part` | perbaikan_suku_cadang | repair_id, spare_part_id, quantity, unit_price |
| `equipment_movements` | mutasi_alat | equipment_id, from_room_id, to_room_id, moved_by, moved_at, is_reinstallation, notes |
| `status_histories` | riwayat_status | equipment_id, from_status, to_status, changed_at, reason, changed_by (nullable = sistem) |
| `calibration_reminders` | pengingat | equipment_id, kind (h30/h7/h0), cycle_valid_until, sent_at, channel; unique(equipment_id, kind, cycle_valid_until) |
| `users` | pengguna | + room_id nullable (untuk kepala ruangan) |
| activity log | log_audit | pakai spatie/laravel-activitylog |

Unik tambahan: (testing_institution_id, certificate_number).

## 7. Non-fungsional
- Bahasa UI: Indonesia. Timezone: `Asia/Jakarta`. Format uang: Rupiah (`Rp30.000.000`).
- Tidak menyimpan **data pasien** sama sekali.
- Password di-hash (default Laravel), semua halaman admin wajib login, halaman QR publik read-only.
- Semua perubahan data penting tercatat di activity log.
- Tampilan responsif (bisa dibuka di HP teknisi).
- Data uji pakai **seeder sintetis** (bukan data RS asli).

## 8. Di luar scope (versi ini)
- Integrasi langsung API ASPAK (cukup ekspor file).
- WhatsApp/SMS gateway.
- Kalibrasi otomatis / membaca data langsung dari alat.
- Pembukuan akuntansi resmi.

## 9. Pertanyaan terbuka (konfirmasi ke kelompok/dosen)
1. Format kolom ekspor ASPAK — buat exporter dengan kolom sementara, sesuaikan setelah observasi.
2. Nilai default masa manfaat dan % nilai residu per jenis alat — isi sementara di seeder, konfirmasi ke RS mitra.
3. Ambang "Pertimbangkan ganti" (default 50%) — konfirmasi ke dosen.
