<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Label QR Alat Kesehatan — SIGAP-Alkes</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', 'DejaVu Sans', sans-serif;
            color: #000000;
            background: #ffffff;
            margin: 0;
            padding: 0;
            font-size: 9pt;
        }
        .labels-grid {
            width: 100%;
        }
        .label-card {
            display: inline-block;
            vertical-align: top;
            width: 60mm;
            height: 40mm;
            border: 1pt solid #000000;
            padding: 2.5mm;
            margin: 0 1.5mm 3mm 0;
            page-break-inside: avoid;
            overflow: hidden;
        }
        .label-table {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
        }
        .qr-cell {
            width: 24mm;
            vertical-align: middle;
            text-align: center;
            padding-right: 2mm;
        }
        .qr-cell img {
            width: 22mm;
            height: 22mm;
            display: block;
        }
        .info-cell {
            vertical-align: middle;
            text-align: left;
            padding-left: 1mm;
        }
        .brand-header {
            font-size: 6.5pt;
            font-weight: bold;
            letter-spacing: 0.5pt;
            text-transform: uppercase;
            border-bottom: 0.5pt solid #000000;
            padding-bottom: 1mm;
            margin-bottom: 1.5mm;
        }
        .equipment-name {
            font-size: 8.5pt;
            font-weight: bold;
            line-height: 1.15;
            margin-bottom: 1mm;
        }
        .inventory-code {
            font-size: 7.5pt;
            font-family: 'DejaVu Sans Mono', monospace;
            margin-bottom: 2mm;
        }
        .scan-hint {
            font-size: 6.5pt;
            border-top: 0.5pt dashed #000000;
            padding-top: 1mm;
        }
    </style>
</head>
<body>
    <div class="labels-grid">
        @foreach ($items as $item)
            <div class="label-card">
                <table class="label-table">
                    <tr>
                        <td class="qr-cell">
                            <img src="{{ $item['qr_data_uri'] }}" alt="QR {{ $item['equipment']->inventory_code }}">
                        </td>
                        <td class="info-cell">
                            <div class="brand-header">SIGAP-Alkes</div>
                            <div class="equipment-name">{{ \Illuminate\Support\Str::limit($item['equipment']->name, 34) }}</div>
                            <div class="inventory-code">{{ $item['equipment']->inventory_code }}</div>
                            <div class="scan-hint">Scan untuk cek status</div>
                        </td>
                    </tr>
                </table>
            </div>
        @endforeach
    </div>
</body>
</html>
