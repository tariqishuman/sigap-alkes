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
