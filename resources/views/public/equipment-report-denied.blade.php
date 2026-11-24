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
    <title>Tidak Dapat Melaporkan Kerusakan | {{ $appName }}</title>
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
            max-width: 26rem;
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
        .badge {
            display: inline-block;
            background: #FCF3E2;
            color: #A66F12;
            border: 1px solid #F3D9A4;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.2rem 0.65rem;
            margin-bottom: 0.75rem;
        }
        h1 {
            font-size: 1.125rem;
            font-weight: 700;
            line-height: 1.35;
            margin-bottom: 0.5rem;
        }
        p {
            font-size: 0.875rem;
            color: #55666A;
            line-height: 1.55;
            margin-bottom: 1.25rem;
        }
        .actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 1rem;
            border-radius: 999px;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
        }
        .btn-primary {
            background: #0E7C7B;
            color: #FFFFFF;
        }
        .btn-secondary {
            background: #FFFFFF;
            color: #13262B;
            border: 1px solid #D3DEDD;
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="brand">
            <img src="{{ $logoUrl }}" alt="Logo {{ $appName }}">
            <span>{{ $hospitalName }}</span>
        </div>

        <span class="badge">Akses terbatas</span>
        <h1>{{ $heading ?? 'Tidak dapat melaporkan kerusakan untuk alat ini' }}</h1>
        <p>{{ $message }}</p>

        <div class="actions">
            <a href="{{ url('/admin') }}" class="btn btn-primary">Kembali ke Dasbor</a>
            <a href="{{ $backUrl }}" class="btn btn-secondary">Kembali ke Status Alat</a>
        </div>
    </main>
</body>
</html>
