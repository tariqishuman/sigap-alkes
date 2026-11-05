<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $equipmentName }} ({{ $inventoryCode }}) — Status Alat | RS When yh</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=poppins:400,500,600,700&display=swap" rel="stylesheet" />
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Poppins', system-ui, -apple-system, sans-serif;
            background-color: #EEF6F5;
            color: #1B2B30;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
        }
        .card {
            width: 100%;
            max-width: 26rem;
            background: #FFFFFF;
            border: 1px solid #DDE7E6;
            border-radius: 0.875rem;
            padding: 1.75rem 1.5rem;
        }
        .brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #0E7C7B;
            letter-spacing: 0.04em;
            margin-bottom: 1rem;
        }
        .brand-left {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .brand img {
            width: 1.5rem;
            height: 1.5rem;
            object-fit: contain;
        }
        .inventory-pill {
            display: inline-block;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.75rem;
            font-weight: 700;
            color: #0E7C7B;
            background-color: #E3F3F2;
            border: 1px solid #B8DFDC;
            padding: 0.2rem 0.55rem;
            border-radius: 0.375rem;
            letter-spacing: 0.03em;
        }
        .title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #1B2B30;
            line-height: 1.25;
            margin-bottom: 0.25rem;
        }
        .room {
            font-size: 0.9375rem;
            color: #6B7C80;
            margin-bottom: 1.25rem;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1rem;
            border-radius: 0.625rem;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            margin-bottom: 1.25rem;
            border: 1px solid;
        }
        .danger-alert {
            background-color: #FDECEC;
            border: 1px solid #F3B8B6;
            color: #A61C1C;
            padding: 0.875rem 1rem;
            border-radius: 0.625rem;
            font-size: 0.9375rem;
            font-weight: 600;
            line-height: 1.45;
            margin-bottom: 1.25rem;
        }
        .identity-box {
            background-color: #F8FBFB;
            border: 1px solid #DDE7E6;
            border-radius: 0.625rem;
            padding: 0.875rem 1rem;
            margin-bottom: 1.25rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem 1rem;
        }
        .identity-item-full {
            grid-column: 1 / -1;
        }
        .meta-label {
            font-size: 0.75rem;
            color: #6B7C80;
            margin-bottom: 0.15rem;
        }
        .meta-value {
            font-size: 0.9375rem;
            font-weight: 600;
            color: #1B2B30;
        }
        .meta-value-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.9375rem;
            font-weight: 700;
            color: #0E7C7B;
        }
        .divider {
            border: none;
            border-top: 1px solid #DDE7E6;
            margin: 1rem 0;
        }
        .footer {
            font-size: 0.75rem;
            color: #6B7C80;
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="brand">
            <div class="brand-left">
                <img src="{{ asset('images/logo-its.png') }}" alt="Logo ITS">
                <span>RS When yh</span>
            </div>
            <span class="inventory-pill">ID: {{ $inventoryCode }}</span>
        </div>

        <h1 class="title">{{ $equipmentName }}</h1>
        <p class="room">
            {{ $roomName }}
            @if (! empty($roomLocation))
                <span>({{ $roomLocation }})</span>
            @endif
        </p>

        <div
            class="badge"
            style="background-color: {{ $statusColors['bg'] }}; color: {{ $statusColors['text'] }}; border-color: {{ $statusColors['border'] }};"
        >
            <span>●</span>
            <span>{{ $statusLabel }}</span>
        </div>

        @if ($isDangerousToUse)
            <div class="danger-alert" role="alert">
                Jangan gunakan alat ini. Hubungi IPSRS.
            </div>
        @endif

        <div class="identity-box">
            <div>
                <div class="meta-label">Kode Inventaris / ID Alat</div>
                <div class="meta-value-mono">{{ $inventoryCode }}</div>
            </div>
            <div>
                <div class="meta-label">Jenis Alat</div>
                <div class="meta-value">{{ $equipmentTypeName }}</div>
            </div>
            <div>
                <div class="meta-label">Merek / Tipe</div>
                <div class="meta-value">{{ $brandModel }}</div>
            </div>
            <div>
                <div class="meta-label">Sertifikat berlaku s.d.</div>
                <div class="meta-value">{{ $validUntilFormatted }}</div>
            </div>
        </div>

        <hr class="divider">

        <div class="footer">Diperbarui: {{ $updatedAtFormatted }}</div>
    </main>
    @include('components.watermark-kelompok2')
</body>
</html>
