<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Label QR Alat Kesehatan — RS When yh</title>
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
            padding: 2mm 2.2mm;
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
            width: 23mm;
            vertical-align: middle;
            text-align: center;
            padding-right: 1.8mm;
        }
        .qr-cell img {
            width: 21.5mm;
            height: 21.5mm;
            display: block;
            margin: 0 auto 1mm auto;
        }
        .qr-id-tag {
            font-size: 6pt;
            font-family: 'DejaVu Sans Mono', monospace;
            font-weight: bold;
            border: 0.6pt solid #000000;
            padding: 0.4mm 0.8mm;
            display: inline-block;
        }
        .info-cell {
            vertical-align: middle;
            text-align: left;
            padding-left: 1mm;
        }
        .brand-header {
            font-size: 6pt;
            font-weight: bold;
            letter-spacing: 0.4pt;
            text-transform: uppercase;
            border-bottom: 0.5pt solid #000000;
            padding-bottom: 0.8mm;
            margin-bottom: 1mm;
        }
        .inventory-code {
            font-size: 7.5pt;
            font-family: 'DejaVu Sans Mono', monospace;
            font-weight: bold;
            margin-bottom: 0.8mm;
        }
        .equipment-name {
            font-size: 7.8pt;
            font-weight: bold;
            line-height: 1.15;
            margin-bottom: 0.8mm;
        }
        .meta-line {
            font-size: 6.2pt;
            line-height: 1.2;
            margin-bottom: 0.5mm;
        }
        .scan-hint {
            font-size: 5.8pt;
            border-top: 0.5pt dashed #000000;
            padding-top: 0.8mm;
            margin-top: 1mm;
        }
    </style>
</head>
<body>
    <div class="labels-grid">
        @foreach ($items as $item)
            @php
                /** @var \App\Models\Equipment $eq */
                $eq = $item['equipment'];
                $brandModel = trim(($eq->brand ?? '') . ' ' . ($eq->model ?? ''));
            @endphp
            <div class="label-card">
                <table class="label-table">
                    <tr>
                        <td class="qr-cell">
                            <img src="{{ $item['qr_data_uri'] }}" alt="QR {{ $eq->inventory_code }}">
                            <div class="qr-id-tag">{{ \Illuminate\Support\Str::limit($eq->inventory_code, 14) }}</div>
                        </td>
                        <td class="info-cell">
                            <div class="brand-header">RS When yh • INVENTARIS ALKES</div>
                            <div class="equipment-name">{{ \Illuminate\Support\Str::limit($eq->name, 30) }}</div>
                            @if ($brandModel !== '')
                                <div class="meta-line">Tipe: {{ \Illuminate\Support\Str::limit($brandModel, 24) }}</div>
                            @endif
                            <div class="meta-line">Ruang: {{ \Illuminate\Support\Str::limit($eq->room?->name ?? '-', 22) }}</div>
                            <div class="scan-hint">Scan QR untuk cek status alat</div>
                        </td>
                    </tr>
                </table>
            </div>
        @endforeach
    </div>
</body>
</html>
