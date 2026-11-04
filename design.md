# design.md — SIGAP-Alkes

Gaya: **minimalis, bersih, 2D, nuansa rumah sakit**. Sama dengan deck presentasi kelompok: dasar putih, satu warna aksen teal, warna status hanya untuk status.

## 1. Token warna

| Token | Hex | Pemakaian |
|---|---|---|
| Primary (teal) | `#0E7C7B` | Tombol utama, link, aksen panel |
| Ink | `#1B2B30` | Teks utama |
| Muted | `#6B7C80` | Teks sekunder, caption |
| Line | `#DDE7E6` | Garis/border tipis |
| Tint | `#EEF6F5` | Latar kartu/panel lembut |
| Success | `#2E9E6B` | Laik pakai |
| Warning | `#E0A23B` | Perlu kalibrasi |
| Danger | `#D9534F` | Terlambat, Tidak laik pakai |
| Info | Filament default `info` | Dalam uji & kalibrasi |
| Gray | Filament default `gray` | Dalam perbaikan |

Aturan: warna hijau/kuning/merah **hanya** untuk status alat dan indikator, jangan untuk dekorasi.

## 2. Tipografi
- Font aplikasi: **Poppins** (set di panel Filament via `->font('Poppins')`).
- Halaman publik QR dan PDF: Poppins untuk isi; judul boleh **DM Serif Display** (opsional, hanya di halaman publik).
- Angka besar (stat dashboard): Poppins SemiBold.

## 3. Panel Filament

```php
->id('admin')->path('admin')
->brandName('SIGAP-Alkes')
->colors(['primary' => Color::hex('#0E7C7B')])   // atau Color palette setara
->font('Poppins')
->sidebarCollapsibleOnDesktop()
->databaseNotifications()
```

- Pakai **custom theme** Filament (Tailwind 4) hanya untuk penyesuaian kecil: radius kartu, warna border `Line`, latar `Tint`. Jangan override komponen besar.
- Light mode saja untuk versi ini (`->darkMode(false)`).

### Navigasi (grup sidebar)
1. **Dashboard**
2. **Alat** — Daftar Alat, Jadwal Kalibrasi, Laporan Kerusakan
3. **Kegiatan** — Kalibrasi, Perbaikan, Mutasi Alat
4. **Laporan** — Uptime & Kepatuhan, Nilai Aset
5. **Data Master** — Ruangan, Jenis Alat, Lembaga Penguji, Suku Cadang
6. **Pengaturan** — Pengguna, Role (Shield)

Item yang tidak boleh diakses role tertentu disembunyikan, bukan di-disable.

### Daftar Alat (tabel)
Kolom: Kode inventaris · Nama · Ruangan · Jenis · **Status (badge)** · Berlaku s.d. · Nilai buku.
Filter: ruangan, jenis, status, jatuh tempo ≤ 30 hari. Bulk action: cetak label QR, ekspor.

### Detail Alat (infolist + tab relasi)
- Kartu atas: nama, kode, ruangan, badge status besar, QR kecil.
- Kartu "Kalibrasi": sertifikat terakhir, berlaku s.d., sisa hari.
- Kartu "Nilai aset": harga perolehan, penyusutan/tahun, akumulasi, **nilai buku**, total biaya perbaikan, penanda "Pertimbangkan ganti" (warning) bila berlaku, plus disclaimer kecil.
- Tab relasi: Kalibrasi · Perbaikan · Mutasi · Riwayat Status.

### Dashboard
Baris 1 (stat): Alat aktif · Kepatuhan kalibrasi % · Terlambat · Jatuh tempo ≤ 30 hari · Total nilai buku.
Baris 2: chart garis uptime rata-rata 12 bulan (lebar 2/3) + tabel jatuh tempo terdekat (1/3).
Baris 3: nilai buku per ruangan (bar chart).

## 4. Halaman publik QR (`/alat/{qr_token}`)

Mobile-first, satu kolom, tanpa navigasi admin.

```
┌──────────────────────────┐
│ SIGAP-Alkes              │  (kecil, teal)
│                          │
│ Infusion Pump            │  (judul besar)
│ Ruang ICU                │  (muted)
│                          │
│ ● LAIK PAKAI             │  (badge besar, warna status)
│                          │
│ Sertifikat berlaku s.d.  │
│ 12 Maret 2027            │
│ ──────────────────────── │
│ Diperbarui: hari ini     │  (muted, kecil)
└──────────────────────────┘
```

- Jika status **Terlambat / Tidak laik pakai**: tampilkan pesan tegas "Jangan gunakan alat ini. Hubungi IPSRS." dengan latar merah lembut.
- Token tidak valid → halaman 404 sederhana.

## 5. Label QR (PDF)
- Ukuran label 60 × 40 mm, beberapa label per A4 untuk cetak massal.
- Isi: QR (kiri) · nama alat · kode inventaris · "Scan untuk cek status" (kanan).
- Hitam putih, tanpa warna, supaya jelas saat dicetak.

## 6. Copywriting
- Bahasa Indonesia sederhana dan aktif. Contoh: "Simpan kalibrasi", "Tandai perbaikan selesai", "Cetak label QR".
- Notifikasi pengingat: "Kalibrasi *Infusion Pump (ICU-012)* jatuh tempo 7 hari lagi (12 Maret 2027)."
- Hindari istilah teknis Inggris di UI kecuali yang umum (uptime, QR).
