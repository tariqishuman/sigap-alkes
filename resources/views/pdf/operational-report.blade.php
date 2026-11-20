<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Uptime &amp; Kepatuhan Kalibrasi — SIGAP-Alkes</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1B2B30; }
        h1 { font-size: 14pt; color: #0E7C7B; margin: 0 0 4px 0; }
        .meta { font-size: 8.5pt; color: #6B7C80; margin-bottom: 12px; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .summary td { border: 1px solid #DDE7E6; padding: 6px 10px; background: #EEF6F5; }
        .summary .label { font-size: 7.5pt; color: #6B7C80; }
        .summary .value { font-size: 11pt; font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #DDE7E6; padding: 5px 7px; text-align: left; }
        table.data th { background: #0E7C7B; color: #FFFFFF; font-size: 8pt; }
        .num { text-align: right; }
        .footer { margin-top: 10px; font-size: 7.5pt; color: #6B7C80; }
    </style>
</head>
<body>
    <h1>SIGAP-Alkes — Laporan Uptime &amp; Kepatuhan Kalibrasi</h1>
    <div class="meta">
        Periode: <strong>{{ $summary['period_label'] }}</strong> &nbsp;|&nbsp;
        Ruangan: <strong>{{ $summary['room_label'] }}</strong> &nbsp;|&nbsp;
        Dicetak: {{ $generatedAt }} WIB
    </div>

    <table class="summary">
        <tr>
            <td>
                <div class="label">Uptime periode</div>
                <div class="value">{{ $summary['uptime']['uptime_percent'] !== null ? number_format($summary['uptime']['uptime_percent'], 2, ',', '.').'%' : '–' }}</div>
            </td>
            <td>
                <div class="label">Total jam operasi</div>
                <div class="value">{{ number_format($summary['uptime']['total_hours'], 1, ',', '.') }} jam</div>
            </td>
            <td>
                <div class="label">Total downtime</div>
                <div class="value">{{ number_format($summary['uptime']['downtime_hours'], 1, ',', '.') }} jam</div>
            </td>
            <td>
                <div class="label">Kepatuhan kalibrasi hari ini</div>
                <div class="value">{{ $summary['compliance']['formatted'] }} ({{ $summary['compliance']['compliant_count'] }}/{{ $summary['compliance']['required_count'] }})</div>
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Kode Inventaris</th>
                <th>Nama Alat</th>
                <th>Ruangan</th>
                <th>Status</th>
                <th class="num">Total Jam Operasi</th>
                <th class="num">Downtime</th>
                <th class="num">Uptime (%)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['equipment']->inventory_code }}</td>
                    <td>{{ $row['equipment']->name }}</td>
                    <td>{{ $row['equipment']->room?->name ?? '-' }}</td>
                    <td>{{ $row['equipment']->status?->getLabel() ?? '-' }}</td>
                    <td class="num">{{ number_format($row['metrics']['total_hours'], 1, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['metrics']['downtime_hours'], 1, ',', '.') }}</td>
                    <td class="num">{{ $row['metrics']['uptime_percent'] !== null ? number_format($row['metrics']['uptime_percent'], 2, ',', '.').'%' : '–' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">Tidak ada alat aktif pada filter ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Downtime mencakup status Dalam perbaikan dan Tidak laik pakai. Alat yang dimutasi di tengah bulan dihitung ke ruangan saat ini.
    </div>
</body>
</html>
