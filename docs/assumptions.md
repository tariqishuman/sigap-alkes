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
