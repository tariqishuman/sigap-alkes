# Assumptions — SIGAP-Alkes

Dokumen ini mencatat asumsi teknis dan bisnis yang diambil selama pengembangan per milestone sesuai aturan di `AGENTS.md`.

## M0 — Setup
1. **Email Akun Demo per Role** (kata sandi seluruh akun: `password`):
   - `super_admin`: `superadmin@sigap-alkes.test`
   - `admin_ipsrs`: `admin@sigap-alkes.test`
   - `teknisi`: `teknisi@sigap-alkes.test`
   - `kepala_ruangan`: `kepalaruangan@sigap-alkes.test`
   - `manajemen`: `manajemen@sigap-alkes.test`
2. **Manajemen Pengguna (`UserResource`)**:
   - Ditempatkan pada grup navigasi `Pengaturan` dengan label `Pengguna` (sesuai `design.md` §3: `Pengaturan — Pengguna, Role (Shield)`).
   - Dapat diakses oleh `super_admin` dan `admin_ipsrs` (sesuai `PRD.md` §3).
3. **Manajemen Role (`RoleResource` / Filament Shield)**:
   - Ditempatkan pada grup navigasi `Pengaturan` dengan label `Role`, hanya dapat diakses oleh `super_admin`.
