@php
    $appName = 'SIGAP-Alkes';
    $logoUrl = asset('images/logo-its.png');
    $faviconUrl = asset('images/brand/favicon.svg');

    try {
        $settings = app(\App\Services\SettingsService::class);
        $appName = $settings->appName();
        $logoUrl = $settings->logoUrl();
        $faviconUrl = $settings->faviconUrl();
    } catch (\Throwable) {
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    @hasSection('refresh')
        <meta http-equiv="refresh" content="@yield('refresh')">
    @endif
    <title>@yield('code') · @yield('title') — {{ $appName }}</title>
    <link rel="icon" href="{{ $faviconUrl }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet" />
    <style>
        :root {
            --primary: #0E7C7B;
            --primary-dark: #0B6463;
            --primary-50: #E8F4F3;
            --primary-100: #D5ECEA;
            --canvas: #F4F7F7;
            --surface: #FFFFFF;
            --border: #E3EAEA;
            --ink: #13262B;
            --muted: #6B7C80;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
            background-color: var(--canvas);
            background-image: radial-gradient(var(--border) 1px, transparent 1px);
            background-size: 22px 22px;
            color: var(--ink);
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            -webkit-font-smoothing: antialiased;
        }
        .card {
            width: 100%;
            max-width: 28rem;
            text-align: center;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            font-size: .875rem;
            font-weight: 700;
            letter-spacing: -.01em;
            color: var(--primary);
        }
        .brand img { width: 1.5rem; height: 1.5rem; object-fit: contain; }
        .illustration {
            display: block;
            width: 100%;
            max-width: 15rem;
            height: auto;
            margin: 1rem auto .5rem;
        }
        .code {
            display: inline-block;
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: .06em;
            color: var(--primary);
            background: var(--primary-50);
            border: 1px solid var(--primary-100);
            border-radius: 999px;
            padding: .25rem .75rem;
            margin-bottom: .75rem;
        }
        h1 {
            font-size: 1.375rem;
            font-weight: 700;
            letter-spacing: -.015em;
            line-height: 1.3;
            margin-bottom: .5rem;
        }
        .message {
            font-size: .875rem;
            color: var(--muted);
            line-height: 1.6;
            max-width: 22rem;
            margin: 0 auto;
        }
        .note {
            font-size: .8125rem;
            color: var(--primary);
            background: var(--primary-50);
            border: 1px dashed var(--primary-100);
            border-radius: 10px;
            padding: .625rem .75rem;
            margin-top: 1rem;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            justify-content: center;
            margin-top: 1.5rem;
        }
        .btn {
            appearance: none;
            border: 1px solid transparent;
            border-radius: 999px;
            padding: .625rem 1.25rem;
            font: inherit;
            font-size: .84375rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: background-color .15s, border-color .15s, color .15s;
        }
        .btn-primary { background: var(--primary); color: #FFFFFF; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-ghost { background: var(--surface); color: var(--ink); border-color: var(--border); }
        .btn-ghost:hover { border-color: var(--primary); color: var(--primary); }
        .btn:focus-visible { outline: 3px solid var(--primary-100); outline-offset: 2px; }
        footer {
            margin-top: 1.5rem;
            font-size: .75rem;
            color: var(--muted);
        }
        @media (max-width: 420px) {
            .card { padding: 1.5rem 1.125rem 1.25rem; }
            .actions .btn { flex: 1 1 100%; }
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="brand">
            <img src="{{ $logoUrl }}" alt="Logo {{ $appName }}">
            <span>{{ $appName }}</span>
        </div>

        <img class="illustration" src="{{ asset('images/errors/'.trim($__env->yieldContent('code')).'.svg') }}" alt="" aria-hidden="true">

        <div class="code">Error @yield('code')</div>
        <h1>@yield('title')</h1>
        <p class="message">@yield('message')</p>

        @hasSection('note')
            <p class="note">@yield('note')</p>
        @endif

        <div class="actions">
            @yield('actions')
        </div>
    </main>

    <footer>{{ $appName }} · Sistem Inventaris &amp; Kalibrasi Alat Kesehatan</footer>
</body>
</html>
