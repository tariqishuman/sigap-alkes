<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Ringkasan {{ $appName }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #13262B; margin: 20px; }
        h1 { font-size: 16px; margin: 0 0 4px; color: #0E7C7B; }
        .sub { color: #6B7C80; font-size: 10px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #E3EAEA; padding: 6px 8px; text-align: left; }
        th { background: #F4F7F7; color: #3A4D52; font-size: 10px; }
        .num { text-align: right; }
        h2 { font-size: 12px; margin: 14px 0 6px; color: #13262B; }
        .note { font-size: 9px; color: #6B7C80; margin-top: 8px; }
    </style>
</head>
<body>
    <h1>Ringkasan Alat Kesehatan — {{ $appName }}</h1>
    <div class="sub">{{ $hospitalName }} · {{ $roomName }} · Periode {{ $top['period_label'] }} · Dicetak {{ $generatedAt }}</div>

    <h2>Indikator Utama</h2>
    <table>
        <tr>
            <th>Total Alat Aktif</th>
            <th>Kepatuhan Kalibrasi</th>
            <th>Uptime Periode</th>
            <th>Rata-rata Lama Perbaikan</th>
            <th>Terlambat Kalibrasi</th>
        </tr>
        <tr>
            <td>{{ $top['total_equipment']['count'] }} unit</td>
            <td>{{ $top['compliance']['formatted'] }} ({{ $top['compliance']['compliant_count'] }}/{{ $top['compliance']['required_count'] }})</td>
            <td>{{ $top['uptime']['percent'] !== null ? number_format($top['uptime']['percent'], 1, ',', '.').'%' : '–' }}</td>
            <td>{{ $top['repair_duration']['formatted'] }} {{ $top['repair_duration']['avg_days'] !== null ? 'hari' : '' }}</td>
            <td>{{ $top['overdue']['count'] }} alat</td>
        </tr>
    </table>

    <h2>Jatuh Tempo Terdekat</h2>
    <table>
        <tr><th>Alat</th><th>Kode & Ruangan</th><th>Sisa</th></tr>
        @forelse ($upcoming as $item)
            <tr>
                <td>{{ $item['title'] }}</td>
                <td>{{ $item['subtitle'] }}</td>
                <td>{{ $item['chip'] }}</td>
            </tr>
        @empty
            <tr><td colspan="3">Tidak ada kalibrasi jatuh tempo dalam waktu dekat.</td></tr>
        @endforelse
    </table>

    <h2>Nilai Buku per Ruangan (Total {{ $bookValue['total_formatted'] }})</h2>
    <table>
        <tr><th>Ruangan</th><th class="num">Nilai Buku</th></tr>
        @forelse ($bookValue['rooms'] as $room)
            <tr>
                <td>{{ $room['name'] }}</td>
                <td class="num">{{ $room['formatted'] }}</td>
            </tr>
        @empty
            <tr><td colspan="2">Belum ada data nilai aset.</td></tr>
        @endforelse
    </table>

    <div class="note">Nilai buku adalah estimasi, bukan pembukuan resmi RS.</div>
</body>
</html>
