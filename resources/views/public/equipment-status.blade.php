@php
    use App\Enums\EquipmentStatus;

    $settings = app(\App\Services\SettingsService::class);
    $appName = $settings->appName();
    $hospitalName = $settings->hospitalName();
    $hospitalPhone = $settings->get('general.hospital_phone', '(031) 5994251');
    $logoUrl = $settings->logoUrl();
    $faviconUrl = $settings->faviconUrl();

    $statusEnum = isset($statusValue) ? EquipmentStatus::tryFrom((string) $statusValue) : null;
    $isBeingServiced = in_array($statusEnum, [EquipmentStatus::UnderRepair, EquipmentStatus::UnderTesting], true);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $equipmentName }} ({{ $inventoryCode }}) — Status Alat | {{ $appName }}</title>
    <link rel="icon" href="{{ $faviconUrl }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet" />
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: #F4F7F7;
            color: #13262B;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            -webkit-font-smoothing: antialiased;
        }
        .card {
            width: 100%;
            max-width: 26rem;
            background: #FFFFFF;
            border: 1px solid #E3EAEA;
            border-radius: 14px;
            box-shadow: 0 1px 2px rgba(19, 38, 43, 0.04);
            padding: 1.5rem;
        }
        .brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 1.125rem;
        }
        .brand-left {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 700;
            color: #13262B;
        }
        .brand-left b { color: #0E7C7B; }
        .brand img { width: 1.5rem; height: 1.5rem; object-fit: contain; }
        .inventory-pill {
            display: inline-block;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.75rem;
            font-weight: 700;
            color: #0E7C7B;
            background-color: #E8F4F3;
            border: 1px solid #D5ECEA;
            padding: 0.2rem 0.55rem;
            border-radius: 6px;
            letter-spacing: 0.02em;
        }
        .title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #13262B;
            line-height: 1.3;
            letter-spacing: -0.015em;
            margin-bottom: 0.25rem;
        }
        .room {
            font-size: 0.875rem;
            color: #6B7C80;
            margin-bottom: 1.125rem;
        }
        .status-block {
            border-radius: 12px;
            padding: 1rem 1.125rem;
            margin-bottom: 1rem;
            border: 1px solid;
        }
        .status-head {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9375rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            margin-bottom: 0.45rem;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            display: inline-block;
        }
        .status-meta {
            font-size: 0.8125rem;
            opacity: 0.9;
            line-height: 1.45;
        }
        .status-meta strong {
            font-variant-numeric: tabular-nums;
        }
        .alert-box {
            background-color: #FBEAEA;
            border: 1px solid #F3C4C2;
            color: #9A2F2C;
            padding: 0.875rem 1rem;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1.45;
            margin-bottom: 1rem;
        }
        .phone-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 0.5rem;
            min-height: 44px;
            padding: 0 1rem;
            border-radius: 999px;
            background-color: #D9534F;
            color: #FFFFFF;
            text-decoration: none;
            font-size: 0.8125rem;
            font-weight: 600;
        }
        .service-note {
            background-color: #EFF2F2;
            border: 1px solid #E3EAEA;
            color: #3A4D52;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.84375rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }
        .identity-box {
            background-color: #F4F7F7;
            border: 1px solid #E3EAEA;
            border-radius: 10px;
            padding: 0.875rem 1rem;
            margin-bottom: 1rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem 1rem;
        }
        .meta-label {
            font-size: 0.71875rem;
            color: #6B7C80;
            margin-bottom: 0.15rem;
        }
        .meta-value {
            font-size: 0.875rem;
            font-weight: 600;
            color: #13262B;
            font-variant-numeric: tabular-nums;
        }
        .meta-value-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.875rem;
            font-weight: 700;
            color: #0E7C7B;
        }
        .pm-info-box {
            background-color: #F4F7F7;
            border: 1px solid #E3EAEA;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem 1rem;
        }
        .report-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            min-height: 44px;
            padding: 0 1rem;
            margin-bottom: 0.625rem;
            border-radius: 999px;
            background-color: #FFFFFF;
            border: 1px solid #D3DEDD;
            color: #13262B;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }
        .cta-stack {
            margin-bottom: 1rem;
        }
        .report-cta:hover {
            background-color: #E8F4F3;
            border-color: #0E7C7B;
            color: #0E7C7B;
        }
        .footer {
            font-size: 0.75rem;
            color: #6B7C80;
            display: flex;
            justify-content: space-between;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="brand">
            <div class="brand-left">
                <img src="{{ $logoUrl }}" alt="Logo {{ $appName }}">
                <span>{{ $hospitalName }}</span>
            </div>
            <span class="inventory-pill">{{ $inventoryCode }}</span>
        </div>

        <h1 class="title">{{ $equipmentName }}</h1>
        <p class="room">
            {{ $roomName }}
            @if (! empty($roomLocation))
                · {{ $roomLocation }}
            @endif
            @if (! empty($brandModel) && $brandModel !== '-')
                · {{ $brandModel }}
            @endif
        </p>

        <div
            class="status-block"
            style="background-color: {{ $statusColors['bg'] }}; color: {{ $statusColors['text'] }}; border-color: {{ $statusColors['border'] }};"
        >
            <div class="status-head">
                <span class="status-dot" style="background-color: {{ $statusColors['text'] }};" aria-hidden="true"></span>
                <span>{{ $statusLabel }}</span>
            </div>
            <div class="status-meta">
                {{ $certificateStatusLine ?? ('Sertifikat berlaku s.d. ' . $validUntilFormatted) }}
            </div>
        </div>

        @if ($isDangerousToUse)
            <div class="alert-box" role="alert">
                <div>Jangan gunakan alat ini. Hubungi IPSRS.</div>
                @if (filled($hospitalPhone))
                    <a class="phone-link" href="tel:{{ preg_replace('/[^0-9+]/', '', (string) $hospitalPhone) }}">
                        Hubungi IPSRS: {{ $hospitalPhone }}
                    </a>
                @endif
            </div>
        @elseif ($isBeingServiced)
            <div class="service-note">
                Alat sedang ditangani teknisi.
            </div>
        @endif

        <div class="identity-box">
            <div>
                <div class="meta-label">Kode Inventaris</div>
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
                <div class="meta-label">Ruangan</div>
                <div class="meta-value">{{ $roomName }}</div>
            </div>
        </div>

        @if (! empty($hasPmSchedule))
            <div class="pm-info-box">
                <div>
                    <div class="meta-label">Pemeliharaan terakhir</div>
                    <div class="meta-value">{{ $lastPmFormatted }}</div>
                </div>
                <div>
                    <div class="meta-label">Berikutnya</div>
                    <div class="meta-value">{{ $nextPmFormatted }}</div>
                </div>
            </div>
        @endif

        @if (! empty($reportUrl) || ! empty($pmUrl))
            <div class="cta-stack">
                @if (! empty($reportUrl))
                    <a href="{{ $reportUrl }}" class="report-cta">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                        </svg>
                        <span>Laporkan kerusakan</span>
                    </a>
                @endif

                @if (! empty($pmUrl))
                    <a href="{{ $pmUrl }}" class="report-cta" style="margin-bottom: 0;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <span>Catat pemeliharaan</span>
                    </a>
                @endif
            </div>
        @endif

        <div class="footer">
            <span>Diperbarui {{ $updatedAtFormatted }}</span>
            <span>{{ $appName }}</span>
        </div>
    </main>
    @include('components.watermark-kelompok2')
</body>
</html>
