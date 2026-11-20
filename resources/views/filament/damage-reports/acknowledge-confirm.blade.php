@php
    $settings = app(\App\Services\SettingsService::class);
    $appName = $settings->appName();
    $hospitalName = $settings->hospitalName();
    $logoUrl = $settings->logoUrl();
    $faviconUrl = $settings->faviconUrl();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Penanganan Laporan | {{ $appName }}</title>
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
        }
        .card {
            width: 100%;
            max-width: 28rem;
            background: #FFFFFF;
            border: 1px solid #E3EAEA;
            border-radius: 14px;
            padding: 1.5rem;
            box-shadow: 0 1px 2px rgba(19, 38, 43, 0.04);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .brand img { width: 1.5rem; height: 1.5rem; object-fit: contain; }
        h1 { font-size: 1.125rem; font-weight: 700; line-height: 1.35; margin-bottom: 0.5rem; }
        p { font-size: 0.875rem; color: #55666A; line-height: 1.5; margin-bottom: 1rem; }
        .summary {
            background: #F4F7F7;
            border: 1px solid #E3EAEA;
            border-radius: 10px;
            padding: 0.875rem 1rem;
            margin-bottom: 1.25rem;
            font-size: 0.84375rem;
            line-height: 1.5;
        }
        .summary strong { color: #13262B; }
        .notice {
            background: #E8F4F3;
            border: 1px solid #D5ECEA;
            color: #0E7C7B;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.84375rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 1.25rem;
            border-radius: 999px;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            font-family: inherit;
        }
        .btn-primary { background: #0E7C7B; color: #FFFFFF; }
        .btn-secondary { background: #FFFFFF; color: #13262B; border: 1px solid #D3DEDD; }
    </style>
</head>
<body>
    <main class="card">
        <div class="brand">
            <img src="{{ $logoUrl }}" alt="Logo {{ $appName }}">
            <span>{{ $hospitalName }}</span>
        </div>

        <h1>Tandai laporan ini sedang Anda tangani?</h1>
        <p>Penandaan ini mencatat Anda sebagai petugas pertama yang merespons laporan. Perbaikan tetap dimulai secara terpisah.</p>

        <div class="summary">
            <div><strong>{{ $report->equipment?->name ?? '-' }}</strong> ({{ $report->equipment?->inventory_code ?? '-' }})</div>
            <div>Ruangan: {{ $report->equipment?->room?->name ?? '-' }}</div>
            <div>Keluhan: {{ $report->complaint }}</div>
        </div>

        @if ($report->isAcknowledged())
            <div class="notice">
                Sudah ditangani oleh {{ $report->acknowledgedByUser?->name ?? 'teknisi lain' }}.
            </div>
            <div class="actions">
                <a href="{{ $indexUrl }}" class="btn btn-primary">Lihat Daftar Laporan</a>
            </div>
        @else
            <form method="POST" action="{{ $storeUrl }}" class="actions">
                @csrf
                <button type="submit" class="btn btn-primary">Ya, saya tangani</button>
                <a href="{{ $indexUrl }}" class="btn btn-secondary">Batal</a>
            </form>
        @endif
    </main>
</body>
</html>
