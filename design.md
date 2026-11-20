# design.md — SIGAP-Alkes (v2)

> Versi 2 menggantikan v1. Arah visual: **dashboard klinis modern**. Kanvas abu-teal terang, kartu putih tipis, angka besar yang tegas, grafik halus, satu warna brand teal.
> Referensi visual wajib: `docs/design/dashboard-reference.html` (buka di browser) dan `docs/design/dashboard-reference.png`.
> Mockup itu acuan **tampilan dan isi**, bukan kode produksi. Implementasi tetap memakai Filament + Blade + Tailwind 4.

---

## 0. Prinsip

1. **Angka dulu, penjelasan belakangan.** Setiap kartu menjawab satu pertanyaan dalam 3 detik: angka besar, satu baris konteks, satu indikator perubahan.
2. **Warna punya arti.** Teal = brand dan aksi. Hijau/kuning/merah/biru/abu = **status alat dan indikator saja**, tidak untuk dekorasi.
3. **Tenang, bukan ramai.** Border 1px tipis, bayangan nyaris tidak ada, ruang kosong cukup. Tidak ada gradien besar, tidak ada kartu berwarna penuh.
4. **Satu sistem.** Panel admin, halaman QR publik, login, halaman error, PDF, dan email memakai token yang sama.
5. **Tanpa jargon.** UI tidak menampilkan rumus, simbol (Σ, ×, ÷), referensi PRD, atau nilai enum. Rumus cukup ada di README/docs.

---

## 1. Token

Semua token didefinisikan **sekali** di `resources/css/filament/admin/theme.css` (blok `@theme`) dan dipakai ulang di halaman publik, error, dan PDF. Jangan menulis hex langsung di Blade kecuali di file yang memang berdiri sendiri (error page, PDF).

### 1.1 Warna — brand & netral

| Token | Hex | Pemakaian |
|---|---|---|
| `primary` / 500 | `#0E7C7B` | Tombol utama, link, item menu aktif, seri grafik utama |
| `primary-600` | `#0B6463` | Hover tombol utama |
| `primary-100` | `#D5ECEA` | Avatar, latar ikon |
| `primary-50` | `#E8F4F3` | Latar item menu aktif, highlight baris |
| `aqua` | `#5CC8C4` | Seri grafik kedua, porsi "masih terbuka", downtime di gauge |
| `aqua-50` | `#E6F7F6` | Latar arsiran (hatch) |
| `canvas` | `#F4F7F7` | Latar halaman (body panel) |
| `surface` | `#FFFFFF` | Kartu, sidebar, topbar, tabel |
| `border` | `#E3EAEA` | Border kartu, pemisah, garis grid grafik |
| `border-strong` | `#D3DEDD` | Sumbu grafik, border input saat hover |
| `ink` | `#13262B` | Teks utama, angka KPI |
| `ink-2` | `#3A4D52` | Teks menu, label tombol sekunder |
| `muted` | `#6B7C80` | Caption, label kolom, teks sekunder |
| `subtle` | `#97A6A9` | Placeholder, label grup sidebar, label sumbu |

Palet Filament `primary` (50–950) tetap seperti di `AdminPanelProvider` sekarang (berbasis `#0E7C7B`).

### 1.2 Warna — status alat (hanya untuk status & indikator)

| Status (enum) | Label UI | Warna | Tint (latar chip) | Teks di atas tint |
|---|---|---|---|---|
| `fit_for_use` | Laik pakai | `#2E9E6B` | `#E7F5EE` | `#22774F` |
| `calibration_due` | Perlu kalibrasi | `#E0A23B` | `#FCF3E2` | `#A66F12` |
| `overdue` | Terlambat | `#D9534F` | `#FBEAEA` | `#B83C38` |
| `under_testing` | Dalam uji & kalibrasi | `#3B82F6` | `#EAF1FE` | `#2563EB` |
| `under_repair` | Dalam perbaikan | `#8A9699` | `#EFF2F2` | `#55666A` |
| `unfit` | Tidak laik pakai | `#B83C38` | `#FBEAEA` | `#9A2F2C` |

- Badge status **selalu** berisi titik/ikon + teks. Warna tidak boleh jadi satu-satunya penanda (aksesibilitas).
- Urutan status di legenda, filter, dan grafik selalu: Laik pakai → Perlu kalibrasi → Terlambat → Dalam uji → Dalam perbaikan → Tidak laik pakai.

### 1.3 Warna — grafik

| Peran | Warna |
|---|---|
| Seri 1 (utama) | `primary` `#0E7C7B` |
| Seri 2 | `aqua` `#5CC8C4` |
| Seri 3 (jarang) | `#97A6A9` |
| Garis target/ambang | `#97A6A9`, putus-putus 6/5 |
| Area di bawah garis | gradien `primary` 18% → 0% |
| Grid | `border` `#E3EAEA`, putus-putus 4/4, hanya horizontal |
| Sumbu | `border-strong` `#D3DEDD` |

Grafik yang menampilkan **status** memakai warna status (1.2), bukan palet grafik.

### 1.4 Tipografi

- Font UI: **Plus Jakarta Sans** (400/500/600/700) dari Bunny Fonts. Ganti `->font('Poppins')` menjadi `->font('Plus Jakarta Sans')`.
- Semua angka (KPI, tabel, sumbu, chip) memakai `font-variant-numeric: tabular-nums` (`tabular-nums` di Tailwind) supaya kolom angka rata.
- Halaman publik, error, PDF, email: Plus Jakarta Sans juga. DM Serif Display tidak dipakai lagi.

| Peran | Ukuran / tinggi baris | Berat | Catatan |
|---|---|---|---|
| Judul halaman | 22 / 28 | 700 | letter-spacing −0.015em |
| Subjudul halaman | 13 / 18 | 400 | `muted` |
| Judul kartu | 14 / 20 | 600 | didahului ikon 18px |
| KPI besar | 28 / 32 | 700 | letter-spacing −0.02em |
| KPI sedang (baris statistik) | 16 / 22 | 700 | |
| Isi / sel tabel | 13.5–14 / 20 | 400–500 | |
| Caption / label kolom | 12–12.5 / 16 | 400–500 | `muted` |
| Label grup sidebar | 11 / 14 | 600 | UPPERCASE, tracking 0.06em, `subtle` |
| Chip / badge | 11–11.5 / 14 | 600 | |

### 1.5 Ukuran, jarak, sudut, bayangan

- Grid jarak **4px**. Jarak yang dipakai: 4, 6, 8, 10, 12, 14, 16, 18, 24, 32.
- Padding kartu: `16px 18px`. Jarak antar kartu: `16px`. Padding konten halaman: `22px 24px 32px`.
- Radius: `sm 8px` (item menu, input), `md 10px` (catatan, kotak kecil), `lg 14px` (kartu, modal), `pill 999px` (tombol, chip, search, tab).
- Bayangan kartu: `0 1px 2px rgba(19,38,43,.04)`. Modal/dropdown: `0 12px 32px -12px rgba(19,38,43,.18)`. Tidak ada bayangan lain.
- Border: semua 1px `border`.

### 1.6 Ikon

- **Heroicons outline** (bawaan Filament), 18px di kartu & menu, 16px di tombol. Item menu aktif memakai versi **solid** (`activeNavigationIcon`).
- Ikon di judul kartu warna `ink-2`; di menu `muted`, aktif `primary`.
- Pemetaan ikon tetap (dipakai konsisten di menu, kartu, dan empty state):

| Konsep | Heroicon |
|---|---|
| Ringkasan | `squares-2x2` |
| Alat | `cube` |
| Jadwal kalibrasi | `calendar-days` |
| Laporan kerusakan | `exclamation-triangle` |
| Kalibrasi | `shield-check` |
| Perbaikan | `wrench-screwdriver` |
| Mutasi | `arrows-right-left` |
| Suku cadang | `archive-box` |
| Uptime | `chart-bar` / `signal` |
| Nilai aset | `banknotes` |
| Ruangan | `building-office` |
| Jenis alat | `tag` |
| Lembaga penguji | `check-badge` |
| Pengguna | `users` |
| Pengaturan | `cog-6-tooth` |
| Bantuan | `question-mark-circle` |

### 1.7 Gerak

- Transisi hanya untuk hover/focus/buka-tutup: 150ms `ease-out`.
- Tidak ada animasi angka berjalan, tidak ada animasi grafik saat load (Chart.js `animation: false`), supaya dashboard terasa cepat.
- Hormati `prefers-reduced-motion`.

---

## 2. Kerangka aplikasi (shell)

### 2.1 Sidebar (lebar 248px, bisa diciutkan)

- Latar `surface`, border kanan 1px.
- Header: logo mark 28px + wordmark "SIGAP**-Alkes**" (bagian "-Alkes" teal) dari Pengaturan Aplikasi; tombol ciutkan kotak 30px ber-border di kanan.
- Grup dengan label kecil UPPERCASE (`subtle`) dan jarak 14px antar grup:
  1. **Umum**: Ringkasan
  2. **Alat**: Daftar Alat, Jadwal Kalibrasi, Laporan Kerusakan
  3. **Kegiatan**: Kalibrasi, Perbaikan, Mutasi Alat, Stok Suku Cadang
  4. **Laporan**: Uptime & Kepatuhan, Nilai Aset
  5. **Data Master**: Ruangan, Jenis Alat, Lembaga Penguji
  6. **Admin**: Pengguna, Role
- **Footer sidebar** (menempel di bawah, dipisah garis): Pengaturan, Bantuan.
- Item: tinggi 36px, radius 8px, teks 13.5px/500 `ink-2`. Aktif: latar `primary-50`, teks & ikon `primary`, berat 600.
- Badge jumlah di kanan item (pill kecil): Laporan Kerusakan = jumlah laporan *open* (tint merah); Jadwal Kalibrasi = jumlah alat terlambat (tint merah) bila > 0; Stok Suku Cadang = jumlah item stok menipis (tint kuning). Badge hanya untuk role yang boleh melihat item itu.
- Item yang tidak boleh diakses role disembunyikan, bukan di-disable.

### 2.2 Topbar (tinggi 64px)

- Latar `surface`, border bawah 1px.
- Kiri: **pencarian global** berbentuk pill (lebar maks 420px, latar `canvas`), placeholder "Cari alat, kode inventaris, atau nomor seri…", hint keyboard `Ctrl K` di kanan. Pakai global search Filament + `->globalSearchKeyBindings(['command+k', 'ctrl+k'])`.
- Kanan, berurutan:
  1. Tombol split pill **"+ Tambah"** (primary) dengan menu cepat sesuai role: Lapor kerusakan, Catat kalibrasi, Tambah alat, Mutasi alat, Tambah stok. Isi menu mengikuti permission.
  2. Lonceng notifikasi (database notifications) dengan titik merah bila ada yang belum dibaca.
  3. Avatar inisial (lingkaran `primary-100`, teks `primary`) + nama + label role kecil di bawahnya.

### 2.3 Header halaman

- Kiri: judul (22px/700) + subjudul satu baris (`muted`). Contoh dashboard: "Ringkasan" / "Kondisi alat kesehatan RS per 20 November 2026".
- Kanan: aksi halaman sebagai tombol pill ber-border (`surface`), maksimal 3. Aksi utama halaman (mis. "Tambah alat") memakai pill `primary`.
- Filter periode memakai **segmented control**: `[📅 1 – 30 Nov 2026 | Bulanan ▾]`.

### 2.4 Grid & breakpoint

- Konten memakai grid 12 kolom, gap 16px.
- Breakpoint: `≥1280` penuh; `1024–1279` kartu 3-kolom jadi 2-kolom; `768–1023` semua kartu 6/12; `<768` satu kolom, sidebar jadi drawer, topbar: search jadi ikon.
- Lebar konten penuh (`Width::Full`), tidak ada max-width.

---

## 3. Komponen

Buat komponen Blade sendiri di `resources/views/components/sigap/` dan pakai di widget/halaman. Jangan menyalin markup yang sama ke banyak file.

### 3.1 `<x-sigap.card>`
Kartu dasar: `surface`, border 1px, radius 14px, padding 16/18, bayangan kartu. Slot: `icon`, `title`, `actions` (kanan header, mis. menu ⋯ atau tab), isi.

### 3.2 `<x-sigap.kpi>`
- Baris: angka besar (28/700, tabular) + satuan kecil `muted` + `<x-sigap.delta>`.
- Opsional caption satu baris di atas angka.
- Opsional `<x-sigap.sparkline>` di pojok kanan atas kartu (lebar ±38%, tinggi 58px). Bila ada sparkline, caption dibatasi lebar ±56% supaya tidak tertimpa.
- Opsional baris statistik 2–3 kolom di bawah (`<x-sigap.stat-row>`): garis atas 1px, tiap kolom label kecil + angka 16/700, dipisah garis vertikal.

### 3.3 `<x-sigap.delta>`
Chip pill kecil: panah ▲/▼ + nilai. Warnanya mengikuti **makna**, bukan arah:

| Metrik | Naik | Turun |
|---|---|---|
| Kepatuhan kalibrasi, uptime, alat laik pakai | hijau (baik) | merah |
| Terlambat, tidak laik, lama perbaikan, laporan terbuka, biaya perbaikan | merah | hijau (baik) |
| Netral (jumlah alat, nilai aset) | abu | abu |

Perubahan 0 atau data pembanding kosong → chip tidak ditampilkan.

### 3.4 `<x-sigap.sparkline>`
SVG yang dirender **di server** (tanpa JS) dari array angka: garis 2px + area gradien. Warna `primary`, atau `danger` untuk metrik buruk (terlambat). Tanpa sumbu, tanpa label, `preserveAspectRatio="none"`. Tooltip tidak perlu.

### 3.5 `<x-sigap.status-badge :status="...">`
Pill: titik 6px + label, latar tint status, teks warna gelap status (tabel 1.2). Ukuran `sm` (tabel) dan `lg` (detail alat & QR publik).

### 3.6 `<x-sigap.status-bar>`
Bar 10px tersegmentasi per status (gap 3px, radius 3px, lebar proporsional) + legenda di bawahnya: titik warna + label + angka tebal. Dipakai di kartu Total Alat dan di header Daftar Alat.

### 3.7 Gauge setengah lingkaran
Untuk persentase tunggal (uptime bulan ini). Busur 180°, tebal 24, track `#EFF2F2`, nilai `primary`, sisa (downtime) `aqua`. Angka di tengah 30/700 + caption kecil. Di bawah: legenda 2 kolom dan **catatan target** dalam kotak `canvas` ("✓ Di atas target 95%" / "Di bawah target 95%"). Implementasi: SVG di server (lebih ringan dari Chart.js).

### 3.8 Grafik (Chart.js lewat `ChartWidget`)
Konfigurasi wajib di semua grafik:
- `animation: false`, `maintainAspectRatio: false`, tinggi tetap per kartu.
- Legenda bawaan Chart.js **dimatikan**; legenda dibuat di header kartu (titik + label).
- Sumbu X: tanpa grid, label `subtle` 11px. Sumbu Y: grid putus-putus `border`, tanpa garis sumbu, 4–5 tick.
- Bar: `borderRadius: 6`, `maxBarThickness: 44`, `categoryPercentage: 0.6`. Porsi kedua (mis. "masih terbuka") ditumpuk di atas memakai **arsiran** `aqua` (pattern canvas) atau `aqua` polos bila pattern merepotkan.
- Line: `tension: 0`, garis 2.5px, titik disembunyikan kecuali titik terakhir (lingkaran putih ber-border), area gradien `primary`.
- Garis target memakai dataset terpisah, putus-putus `subtle`.
- Tooltip: latar `ink`, teks putih, radius 8, angka format Indonesia.

### 3.9 Baris ringkasan grafik
Di atas grafik besar: 3 statistik teks (label kecil `muted` + nilai 14/700) dipisah garis vertikal, legenda di ujung kanan. Contoh: "Paling sering rusak: Infusion Pump · Masih terbuka: 8 laporan · Ruangan terbanyak: ICU".

### 3.10 Tab segmented
Pill latar `canvas`, padding 3px; tab aktif pill putih + bayangan tipis. Dipakai untuk mengganti sudut pandang grafik (Jenis alat / Ruangan) tanpa pindah halaman.

### 3.11 Daftar ber-aksen
Untuk daftar pendek (jatuh tempo, aktivitas terbaru): garis aksen vertikal 3px warna status di kiri, judul 13/600, sub 11.5 `muted`, chip di kanan. Maksimal 6 baris + tautan "Lihat semua".

### 3.12 Tabel (Filament table)
- Header kolom: 12px/500 `muted`, sentence case, latar `surface`, garis bawah 1px.
- Baris: tinggi ±52px, garis pemisah 1px, hover latar `canvas`. Tanpa zebra.
- Kolom angka/uang rata kanan + tabular. Kolom status pakai `<x-sigap.status-badge>`.
- Kolom pertama (nama) tebal 600 + sub-teks `muted` (kode inventaris · ruangan) dalam satu sel, supaya tabel tidak terlalu lebar.
- Toolbar: search pill di kiri, filter & kolom sebagai tombol pill di kanan. Filter aktif tampil sebagai chip yang bisa dihapus.
- Aksi baris disembunyikan di menu ⋯, kecuali satu aksi utama (mis. "Catat hasil").
- Empty state memakai `<x-sigap.empty-state>` dengan ilustrasi yang sesuai.

### 3.13 Form
- Input tinggi 40px, radius 8px, border `border`, fokus ring 3px `primary-100` + border `primary`.
- Label 13/500 `ink-2` di atas input; helper text maksimal satu kalimat pendek, tanpa rumus.
- Form dikelompokkan dalam section kartu dengan judul 14/600. Dua kolom di desktop, satu kolom di mobile.
- Tombol simpan di kanan bawah: pill `primary`; batal: pill ber-border.

### 3.14 Tombol
| Jenis | Gaya |
|---|---|
| Utama | pill `primary`, teks putih 13/600, hover `primary-600` |
| Sekunder | pill `surface`, border 1px, teks `ink-2`, hover border `primary` |
| Bahaya | pill `danger` (hanya untuk aksi yang tidak bisa dibatalkan) |
| Ikon | kotak/lingkaran 36px, ikon 18px `ink-2` |
Tinggi 36px (toolbar) atau 40px (form).

### 3.15 Notifikasi
- Toast Filament: kartu putih, garis aksen kiri 3px warna jenis (sukses teal, peringatan kuning, gagal merah), judul 13/600, isi 12.5.
- Panel notifikasi database: daftar ber-aksen (3.11) dengan waktu relatif ("2 jam lalu"). Kosong → `empty-notifications`.

### 3.16 Empty state, loading, error
- Empty: `<x-sigap.empty-state>` (sudah ada) dengan ilustrasi di `public/images/illustrations/`. Kalimat positif bila kosong itu kabar baik ("Tidak ada kerusakan. Semua alat aman.").
- Loading widget: skeleton kotak abu `#EFF2F2` seukuran isi, bukan spinner. Pakai `lazy` widget Filament untuk grafik.
- Error halaman: `resources/views/errors/layout.blade.php` + ilustrasi `public/images/errors/`, disesuaikan ke token v2 (font Plus Jakarta Sans, kanvas `canvas`).

---

## 4. Halaman

### 4.1 Ringkasan (dashboard) — lihat mockup

Header: "Ringkasan" + filter periode (segmented: rentang tanggal | Harian/Mingguan/Bulanan) + filter ruangan + Ekspor PDF. Filter memakai `filtersForm` dashboard Filament dan berlaku untuk semua widget.

Tata letak (12 kolom):

| Baris | Kiri | Tengah | Kanan |
|---|---|---|---|
| 1 | **Total Alat Aktif** (4): angka + status bar + legenda 6 status | **Kepatuhan Kalibrasi** (5): % + delta vs bulan lalu + sparkline 12 bulan + stat row (Wajib kalibrasi · Bersertifikat · Jatuh tempo ≤ 30 hari) | **Uptime Bulan Ini** (3, tinggi 2 baris): gauge + jam operasi vs downtime + catatan target + stat row (Bulan lalu · Ruangan terendah) |
| 2 | **Rata-rata Lama Perbaikan** (4): hari + delta + stat row (Selesai bulan ini · Masih berjalan · Terlama) | **Terlambat Kalibrasi** (5): jumlah + delta + sparkline merah + stat row (Lewat ≤ 7 hari · 8–30 hari · > 30 hari) | (gauge) |
| 3 | **Kerusakan per Jenis Alat** (9): baris ringkasan + tab Jenis alat/Ruangan + bar bertumpuk Selesai/Masih terbuka | | **Jatuh Tempo Terdekat** (3): daftar ber-aksen 6 alat + "Lihat semua" |
| 4 | **Uptime 12 Bulan Terakhir** (8): line + garis target | | **Nilai Buku per Ruangan** (4): bar horizontal + total + disclaimer singkat |

Definisi data baru (hitung di service, bukan di view):
- **Delta kepatuhan**: `ComplianceCalculator` diberi parameter `asOf` supaya bisa menghitung kepatuhan per akhir bulan. Sparkline = 12 titik akhir bulan.
- **Lama perbaikan rata-rata**: rata-rata `repairs.finished_at − damage_reports.reported_at` (hari, 1 desimal) untuk perbaikan yang selesai dalam periode. Delta vs periode sebelumnya.
- **Tren terlambat**: jumlah alat berstatus *Terlambat* pada akhir tiap bulan, dari `status_histories`.
- **Target uptime**: pengaturan baru di Pengaturan Aplikasi (default 95%).
- Semua hasil dashboard di-cache 10 menit per kombinasi filter.

**Dashboard per role** (widget yang tidak relevan tidak ditampilkan):

| Role | Isi Ringkasan |
|---|---|
| super_admin, admin_ipsrs, manajemen | Semua widget di atas |
| teknisi | "Tugas Anda": kalibrasi jatuh tempo 14 hari ke depan, perbaikan aktif yang ditugaskan, laporan kerusakan terbuka, stok menipis. Tanpa biaya & nilai aset |
| kepala_ruangan | Status bar alat di ruangannya, jatuh tempo ruangannya, laporan kerusakan yang ia buat + statusnya, tombol besar "Lapor kerusakan" |
| badan_kalibrasi | Alat yang perlu dikalibrasi (jatuh tempo & terlambat), hasil kalibrasi yang dicatat lembaganya bulan ini |
| magang | Total alat + status bar, jatuh tempo terdekat, laporan kerusakan terbuka. Tanpa biaya, nilai aset, stok |

### 4.2 Daftar Alat
- Header: judul + status bar kecil seluruh alat (klik segmen = filter status).
- Kolom: Alat (nama tebal + "kode · ruangan" kecil) · Jenis · Status (badge) · Berlaku s.d. (tanggal + "dalam N hari"/"lewat N hari" berwarna) · Nilai buku (rata kanan, disembunyikan untuk role tanpa akses biaya).
- Filter: ruangan, jenis, status, jatuh tempo ≤ 30 hari. Bulk: Cetak label QR, Ekspor.

### 4.3 Detail Alat
Susunan atas ke bawah:
1. **Kartu identitas**: foto (atau placeholder) 96px radius 12 · nama 20/700 · chip kode inventaris · ruangan & jenis `muted` · badge status `lg` · QR kecil di kanan + tombol "Cetak label". Aksi utama di header (sesuai role): Catat hasil kalibrasi, Lapor kerusakan, Mutasi.
2. **Strip KPI** 4 kartu kecil: Sertifikat berlaku s.d. (+ sisa hari berwarna) · Uptime 30 hari · Nilai buku · Total biaya perbaikan. Dua terakhir hanya untuk role dengan akses biaya.
3. **Kartu Nilai Aset** (role berizin): harga perolehan, penyusutan per bulan, akumulasi, nilai buku, total biaya perbaikan; penanda "Pertimbangkan ganti" sebagai banner tint kuning di dalam kartu; disclaimer satu baris kecil.
4. **Tab**: Kalibrasi · Kerusakan & Perbaikan · Mutasi · Riwayat Status. Riwayat status ditampilkan sebagai **timeline** vertikal (titik warna status + tanggal + alasan).

### 4.4 Jadwal Kalibrasi
- Tampilan default: daftar dikelompokkan **Terlambat → Minggu ini → Bulan ini → Berikutnya**, tiap baris memakai daftar ber-aksen.
- Toggle ke tampilan tabel. Filter bulan, ruangan, jenis, status. Ekspor Excel/CSV tetap.

### 4.5 Laporan Kerusakan & Perbaikan
- Laporan: tab status (Terbuka · Diproses · Selesai) dengan angka di tab. Kolom keluhan dipotong 1 baris.
- Perbaikan: rincian biaya dalam kartu (biaya jasa, suku cadang per baris, total tebal). Perbaikan selesai menampilkan banner kecil "Terkunci" abu.

### 4.6 Stok Suku Cadang
- Kolom: Nama · Stok (angka + satuan, rata kanan) · Minimal · status stok chip (Aman / Menipis / Habis) · Pemakaian 30 hari.
- Aksi header: Tambah stok, Sesuaikan stok. Detail: tab Riwayat stok (masuk hijau, keluar abu, penyesuaian kuning).

### 4.7 Laporan (Uptime & Kepatuhan, Nilai Aset)
- Header dengan filter periode & ruangan + tombol Ekspor (Excel, PDF, ASPAK dalam satu dropdown).
- Atas: 3–4 kartu KPI (komponen 3.2). Bawah: grafik + tabel rinci.
- Kolom ASPAK sementara diberi chip kecil "Format sementara".

### 4.8 Pengaturan Aplikasi
- Navigasi tab vertikal di kiri (Umum, Pengingat, Nilai aset, Label QR, Integrasi SatuSehat, Token API), isi di kanan dalam kartu section.
- Integrasi: status koneksi sebagai chip (Terhubung hijau / Belum diatur abu / Gagal merah) + waktu cek terakhir.

### 4.9 Login
- Layout dua kolom di desktop: kiri panel `canvas` + pola `brand/pattern.svg` + ilustrasi `illustrations/login.svg` + tagline; kanan kartu form putih (logo, "Masuk ke SIGAP-Alkes", email, kata sandi, tombol pill penuh). Mobile: hanya kartu form dengan ilustrasi kecil di atas.

### 4.10 Halaman publik QR (`/alat/{qr_token}`)
Mobile-first, satu kartu, lebar maks 26rem, kanvas `canvas`:
```
┌──────────────────────────────┐
│ [logo] SIGAP-Alkes   ICU-012 │  brand kecil + chip kode
│                              │
│ Infusion Pump                │  20/700
│ Ruang ICU · Terumo TE-171    │  muted
│                              │
│ ┌──────────────────────────┐ │
│ │ ● LAIK PAKAI             │ │  blok status penuh, tint status
│ │ Sertifikat berlaku s.d.  │ │
│ │ 12 Maret 2027 (112 hari) │ │
│ └──────────────────────────┘ │
│ Diperbarui hari ini, 08.10   │  muted kecil
└──────────────────────────────┘
```
- Terlambat / Tidak laik pakai: blok status merah tint + teks tegas "Jangan gunakan alat ini. Hubungi IPSRS." + nomor telepon IPSRS dari Pengaturan.
- Dalam perbaikan / Dalam uji: blok abu/biru + "Alat sedang ditangani teknisi."
- Tidak pernah menampilkan biaya, harga, nama teknisi, atau isi keluhan.

### 4.11 Label QR (PDF)
Tidak berubah dari v1: 60 × 40 mm, hitam putih, QR kiri, nama + kode + "Scan untuk cek status" kanan. Font Plus Jakarta Sans bila tersedia di dompdf, kalau tidak DejaVu Sans.

### 4.12 PDF laporan & email
- PDF: header logo + nama RS dari Pengaturan, judul 16/700, tabel garis tipis, angka rata kanan, footer "Dicetak dari SIGAP-Alkes · tanggal · halaman N/M".
- Email: lebar 600px, header teal tipis, satu tombol utama, footer nama RS.

---

## 5. Format angka, tanggal, teks

- Locale `id_ID`. Ribuan titik, desimal koma: `26.755`, `95,8%`.
- Uang: `Rp30.000.000` di tabel/detail; ringkas di kartu: `Rp412 jt`, `Rp1,79 M`.
- Persen: 1 desimal di KPI (`87,0%` boleh ditulis `87%` bila bulat), 2 desimal hanya di laporan rinci.
- Tanggal: `12 Mar 2026` di tabel, `12 Maret 2026` di detail & QR publik, `1 – 30 Nov 2026` untuk rentang. Waktu `08.10`.
- Relatif: "3 hari lagi", "lewat 4 hari", "2 jam lalu".
- Kosong: tanda pisah `–`, bukan `0` atau `null`.

---

## 6. Aksesibilitas

- Kontras teks minimal WCAG AA (4.5:1; 3:1 untuk teks ≥ 18px/600). `subtle` hanya untuk placeholder & label sumbu.
- Fokus terlihat: ring 3px `primary-100` + border `primary` pada semua elemen interaktif.
- Status tidak hanya warna (selalu ada teks).
- Target sentuh minimal 36px (44px di halaman publik QR).
- Grafik punya ringkasan teks di baris ringkasan/caption untuk pembaca layar.

---

## 7. Copywriting

- Bahasa Indonesia sederhana dan aktif: "Simpan kalibrasi", "Tandai perbaikan selesai", "Cetak label QR", "Lapor kerusakan".
- Judul kartu berupa kata benda singkat ("Kepatuhan Kalibrasi"), caption menjelaskan arti dalam satu kalimat tanpa rumus.
- Notifikasi pengingat: "Kalibrasi *Infusion Pump (ICU-012)* jatuh tempo 7 hari lagi (12 Maret 2027)."
- Hindari istilah Inggris kecuali yang umum (uptime, QR).

---

## 8. Aturan implementasi (Filament 5 + Tailwind 4)

**Lakukan**
- Token di `@theme` pada `theme.css`; komponen Blade di `components/sigap/`.
- Kartu dashboard sebagai **custom Widget dengan view Blade** (bukan `StatsOverviewWidget`), supaya layout sesuai mockup. Grafik besar tetap `ChartWidget`.
- Gunakan render hook Filament untuk: tombol "+ Tambah" di topbar, footer sidebar, panel kiri login.
- Gunakan `navigationBadge()` untuk badge jumlah di menu.
- Hitung semua angka di service (`app/Services/Dashboard*`), view hanya menampilkan.
- Cache hasil dashboard 10 menit; widget grafik `lazy`.

**Jangan**
- Jangan publish dan mengubah view vendor Filament.
- Jangan override komponen besar dengan `!important` berlapis. Bila butuh, targetkan class `fi-*` secukupnya dan catat di komentar CSS.
- Jangan menambah library UI/JS baru kecuali Chart.js plugin kecil untuk pattern (opsional).
- Jangan menampilkan rumus atau istilah internal di UI.

---

## 9. Checklist QA visual

- [ ] Dashboard di 1440px mirip `docs/design/dashboard-reference.png` (susunan, jarak, hierarki).
- [ ] Dashboard di 1280, 1024, 768, 390px tidak ada scroll horizontal; kartu turun rapi.
- [ ] Semua role melihat dashboard sesuai tabel 4.1; tidak ada angka biaya bocor ke teknisi/kepala ruangan/magang/badan kalibrasi.
- [ ] Semua badge status memakai `<x-sigap.status-badge>` dan warnanya sesuai tabel 1.2.
- [ ] Semua angka tabular dan berformat Indonesia.
- [ ] Tidak ada teks Σ, ×, ÷, "PRD", atau nilai enum di HTML halaman panel.
- [ ] Login, QR publik, error 404/500/503, PDF, email memakai font & token v2.
- [ ] Fokus keyboard terlihat di semua tombol, input, item menu.
- [ ] Waktu muat dashboard < 1 detik dengan data seeder (cache hangat).

---

## 10. Komponen UI Baru (Milestone R3)

- **Form Laporan Kerusakan**: Pertanyaan wajib radio *"Apakah alat masih aman dipakai?"* (Ya / Tidak) dengan deskripsi dampak status + unggah maks 3 foto (JPG/PNG, maks 3 MB, disk private).
- **Halaman QR Publik (`/alat/{qr_token}`)**: Tombol pill sekunder **"Laporkan kerusakan"** (tinggi ≥ 44px) yang mengarahkan ke alur login (intended URL) lalu form laporan terisi otomatis, atau halaman pesan akses terbatas yang jelas (`public/equipment-report-denied.blade.php`).
- **Jadwal Kalibrasi & Pemeliharaan**: Tab tingkat atas **"Kalibrasi"** | **"Pemeliharaan"** di halaman Jadwal Kalibrasi, dengan 4 kelompok ber-aksen (`Terlambat`, `Minggu ini`, `Bulan ini`, `Berikutnya`) dan tautan langsung **+ Catat pemeliharaan**.
- **Dashboard KPI Kepatuhan**: Sub-stat ringkas **"Kepatuhan pemeliharaan"** di dalam kartu Kepatuhan Kalibrasi tanpa mengubah tata letak 4 baris.
- **Notifikasi di Perangkat Ini (`/admin/notifikasi-perangkat`)**: Diakses dari menu pengguna, berisi kontrol aktifkan/nonaktifkan Web Push, tombol **"Kirim notifikasi uji"**, dan preferensi tingkat alert (Penting / Info; Kritis selalu aktif untuk teknisi & Admin IPSRS).

