{{--
    Layout halaman error SIGAP-Alkes.
    Dipakai oleh errors/401, 403, 404, 419, 429, 500, 503.
    Sengaja berdiri sendiri (tanpa Vite/Filament) supaya tetap tampil walau aplikasi sedang bermasalah.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    @hasSection('refresh')
        <meta http-equiv="refresh" content="@yield('refresh')">
    @endif
    <title>@yield('code') · @yield('title') — SIGAP-Alkes</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=poppins:400,500,600&display=swap" rel="stylesheet" />
    <style>
        :root {
            --primary: #0E7C7B;
            --primary-dark: #0B6463;
            --ink: #1B2B30;
            --muted: #6B7C80;
            --line: #DDE7E6;
            --tint: #EEF6F5;
            --tint-strong: #E3F3F2;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', system-ui, -apple-system, 'Segoe UI', sans-serif;
            background-color: var(--tint);
            background-image: radial-gradient(var(--line) 1px, transparent 1px);
            background-size: 22px 22px;
            color: var(--ink);
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
        }
        .card {
            width: 100%;
            max-width: 28rem;
            background: #FFFFFF;
            border: 1px solid var(--line);
            border-radius: 1rem;
            padding: 1.75rem 1.5rem 1.5rem;
            text-align: center;
            box-shadow: 0 1px 2px rgba(27, 43, 48, .04), 0 12px 32px -12px rgba(14, 124, 123, .18);
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            font-size: .8125rem;
            font-weight: 600;
            letter-spacing: .02em;
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
            letter-spacing: .08em;
            color: var(--primary);
            background: var(--tint-strong);
            border-radius: 999px;
            padding: .25rem .75rem;
            margin-bottom: .75rem;
        }
        h1 {
            font-size: 1.375rem;
            font-weight: 600;
            line-height: 1.3;
            margin-bottom: .5rem;
        }
        .message {
            font-size: .9rem;
            color: var(--muted);
            line-height: 1.6;
            max-width: 22rem;
            margin: 0 auto;
        }
        .note {
            font-size: .8125rem;
            color: var(--primary);
            background: var(--tint);
            border: 1px dashed var(--line);
            border-radius: .625rem;
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
            border-radius: .625rem;
            padding: .625rem 1.125rem;
            font: inherit;
            font-size: .875rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: background-color .15s, border-color .15s, color .15s;
        }
        .btn-primary { background: var(--primary); color: #FFFFFF; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-ghost { background: #FFFFFF; color: var(--ink); border-color: var(--line); }
        .btn-ghost:hover { border-color: var(--primary); color: var(--primary); }
        .btn:focus-visible { outline: 3px solid var(--tint-strong); outline-offset: 2px; }
        footer {
            margin-top: 1.25rem;
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
            <img src="{{ asset('images/logo-its.png') }}" alt="Logo ITS">
            <span>SIGAP-Alkes</span>
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

    <footer>SIGAP-Alkes · Sistem Inventaris &amp; Kalibrasi Alat Kesehatan</footer>
</body>
</html>
