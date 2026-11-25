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
    <title>Pemeliharaan Tersimpan | {{ $hospitalName }}</title>
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
            box-shadow: 0 2px 6px rgba(19, 38, 43, 0.045);
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
        .badge-ok {
            display: inline-block;
            background: #E7F5EE;
            color: #22774F;
            border: 1px solid #B9E2CE;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.2rem 0.65rem;
            margin-bottom: 0.75rem;
        }
        h1 { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.4rem; }
        p { font-size: 0.9rem; color: #56686C; line-height: 1.5; margin-bottom: 1rem; }
        .meta {
            background: #F4F7F7;
            border: 1px solid #E3EAEA;
            border-radius: 10px;
            padding: 0.875rem 1rem;
            font-size: 0.875rem;
            line-height: 1.55;
            margin-bottom: 1rem;
        }
        .warn-box {
            background: #FCF3E2;
            border: 1px solid #F3D9A4;
            color: #A66F12;
            border-radius: 10px;
            padding: 0.875rem 1rem;
            font-size: 0.875rem;
            line-height: 1.45;
            margin-bottom: 1rem;
        }
        .actions { display: flex; flex-direction: column; gap: 0.5rem; }
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            border-radius: 999px;
            font-size: 0.9375rem;
            font-weight: 600;
            text-decoration: none;
            padding: 0 1rem;
        }
        .btn-warn { background: #D9534F; color: #FFFFFF; }
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

        <span class="badge-ok">✓ Tersimpan via scan QR</span>
        <h1>Pemeliharaan preventif dicatat</h1>
        <p>{{ $equipment->name }} ({{ $equipment->inventory_code }}) telah diperbarui jadwal pemeliharaannya.</p>

        <div class="meta">
            <div>Tanggal pelaksanaan: <strong>{{ $pm->performed_at?->translatedFormat('d F Y') }}</strong></div>
            <div>Jadwal berikutnya: <strong>{{ $pm->next_due_at?->translatedFormat('d F Y') }}</strong></div>
            <div>Hasil: <strong>{{ $pm->result?->getLabel() }}</strong></div>
        </div>

        @if ($damageReportUrl)
            <div class="warn-box">
                Terdapat butir pemeriksaan bertanda <strong>Tidak baik</strong>. Status alat belum berubah — buat laporan kerusakan bila alat perlu ditindaklanjuti.
            </div>
        @endif

        <div class="actions">
            @if ($damageReportUrl)
                <a href="{{ $damageReportUrl }}" class="btn btn-warn">Buat laporan kerusakan</a>
            @endif
            <a href="{{ $backUrl }}" class="btn btn-primary">Kembali ke Status Alat</a>
            <a href="{{ url('/admin') }}" class="btn btn-secondary">Buka Dasbor</a>
        </div>
    </main>
</body>
</html>
