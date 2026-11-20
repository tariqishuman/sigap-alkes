@php
    $settings = app(\App\Services\SettingsService::class);
    $appName = $settings->appName();
    $hospitalName = $settings->hospitalName();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Nilai Aset &amp; Penyusutan — {{ $appName }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #1B2B30; }
        h1 { font-size: 14pt; color: #0E7C7B; margin: 0 0 4px 0; }
        .meta { font-size: 8pt; color: #6B7C80; margin-bottom: 10px; }
        .disclaimer { background: #EEF6F5; border: 1px solid #DDE7E6; padding: 6px 10px; margin-bottom: 12px; font-size: 8pt; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #DDE7E6; padding: 5px 6px; text-align: left; }
        table.data th { background: #0E7C7B; color: #FFFFFF; font-size: 7.8pt; }
        .num { text-align: right; }
        .warn { color: #B87D1E; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $appName }} — Laporan Nilai Aset &amp; Penyusutan</h1>
    <div class="meta">
        <strong>{{ $hospitalName }}</strong> &nbsp;|&nbsp;
        Dicetak: {{ $generatedAt }} WIB &nbsp;|&nbsp;
        Total Nilai Buku: <strong>Rp{{ number_format((float) $totalBookValue, 0, ',', '.') }}</strong> &nbsp;|&nbsp;
        Total Biaya Perbaikan: <strong>Rp{{ number_format((float) $totalRepairCost, 0, ',', '.') }}</strong>
    </div>

    <div class="disclaimer">
        Nilai buku adalah estimasi, bukan pembukuan resmi RS.
        Penanda "Pertimbangkan ganti" muncul bila biaya perbaikan sudah mencapai {{ $threshold }}% dari nilai buku.
    </div>

    <table class="data">
        <thead>
            <tr>
                <th>Kode</th>
                <th>Nama Alat</th>
                <th>Ruangan</th>
                <th class="num">Harga Perolehan</th>
                <th class="num">Penyusutan / Bln</th>
                <th class="num">Nilai Buku</th>
                <th class="num">Total Perbaikan</th>
                <th>Indikator</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['equipment']->inventory_code }}</td>
                    <td>{{ $row['equipment']->name }}</td>
                    <td>{{ $row['equipment']->room?->name ?? '-' }}</td>
                    <td class="num">Rp{{ number_format((float) $row['depreciation']['acquisition_cost'], 0, ',', '.') }}</td>
                    <td class="num">Rp{{ number_format((float) $row['depreciation']['monthly_depreciation'], 0, ',', '.') }}</td>
                    <td class="num">Rp{{ number_format((float) $row['depreciation']['book_value'], 0, ',', '.') }}</td>
                    <td class="num">Rp{{ number_format((float) $row['repair_cost'], 0, ',', '.') }}</td>
                    <td class="{{ $row['should_replace'] ? 'warn' : '' }}">{{ $row['should_replace'] ? 'Pertimbangkan ganti' : '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center;">Tidak ada alat aktif.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
