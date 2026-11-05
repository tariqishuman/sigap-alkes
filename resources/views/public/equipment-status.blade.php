<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $equipmentName }} — Status Alat | SIGAP-Alkes</title>
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
            gap: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #0E7C7B;
            letter-spacing: 0.04em;
            margin-bottom: 1.25rem;
        }
        .brand img {
            width: 1.5rem;
            height: 1.5rem;
            object-fit: contain;
        }
        .title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1B2B30;
            line-height: 1.25;
            margin-bottom: 0.25rem;
        }
        .room {
            font-size: 0.9375rem;
            color: #6B7C80;
            margin-bottom: 1.5rem;
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
        .meta-label {
            font-size: 0.8125rem;
            color: #6B7C80;
            margin-bottom: 0.25rem;
        }
        .meta-value {
            font-size: 1rem;
            font-weight: 500;
            color: #1B2B30;
            margin-bottom: 1.25rem;
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
            <img src="{{ asset('images/logo-its.png') }}" alt="Logo ITS">
            <span>SIGAP-Alkes</span>
        </div>

        <h1 class="title">{{ $equipmentName }}</h1>
        <p class="room">{{ $roomName }}</p>

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

        <div class="meta-label">Sertifikat berlaku s.d.</div>
        <div class="meta-value">{{ $validUntilFormatted }}</div>

        <hr class="divider">

        <div class="footer">Diperbarui: {{ $updatedAtFormatted }}</div>
    </main>
    @include('components.watermark-kelompok2')
</body>
</html>
