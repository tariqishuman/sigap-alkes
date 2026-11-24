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
    <title>Catat Pemeliharaan — {{ $equipment->name }} | {{ $appName }}</title>
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
            padding: 1rem;
            -webkit-font-smoothing: antialiased;
        }
        .wrap {
            width: 100%;
            max-width: 28rem;
            margin: 0 auto 2rem;
        }
        .card {
            background: #FFFFFF;
            border: 1px solid #E3EAEA;
            border-radius: 14px;
            padding: 1.25rem;
            box-shadow: 0 2px 6px rgba(19, 38, 43, 0.045);
            margin-bottom: 0.875rem;
        }
        .brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.875rem;
        }
        .brand-left {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 700;
        }
        .brand img { width: 1.5rem; height: 1.5rem; object-fit: contain; }
        .code-pill {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 0.75rem;
            font-weight: 700;
            color: #0E7C7B;
            background: #E8F4F3;
            border: 1px solid #D5ECEA;
            padding: 0.2rem 0.55rem;
            border-radius: 6px;
        }
        h1 { font-size: 1.25rem; font-weight: 700; line-height: 1.3; margin-bottom: 0.25rem; }
        .sub { font-size: 0.875rem; color: #56686C; line-height: 1.45; }
        .alert-error {
            background: #FBEAEA;
            border: 1px solid #F3C4C2;
            color: #9A2F2C;
            border-radius: 10px;
            padding: 0.875rem 1rem;
            font-size: 0.875rem;
            margin-bottom: 0.875rem;
        }
        .alert-error ul { margin-left: 1.1rem; margin-top: 0.35rem; }
        .check-card {
            background: #FFFFFF;
            border: 1px solid #E3EAEA;
            border-radius: 14px;
            padding: 1.125rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 1px 3px rgba(19, 38, 43, 0.04);
        }
        .check-num {
            font-size: 0.75rem;
            font-weight: 600;
            color: #56686C;
            margin-bottom: 0.2rem;
        }
        .check-title {
            font-size: 1rem;
            font-weight: 700;
            color: #13262B;
            margin-bottom: 0.75rem;
        }
        .choice-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.625rem;
            margin-bottom: 0.75rem;
        }
        .choice-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            min-height: 48px;
            border-radius: 10px;
            border: 1.5px solid #D3DEDD;
            background: #FFFFFF;
            font-size: 0.9375rem;
            font-weight: 600;
            color: #13262B;
            cursor: pointer;
            transition: all 160ms ease;
        }
        .choice-input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .choice-input:checked + .choice-good {
            background: #E7F5EE;
            border-color: #2E9E6B;
            color: #22774F;
        }
        .choice-input:checked + .choice-bad {
            background: #FBEAEA;
            border-color: #D9534F;
            color: #9A2F2C;
        }
        .field-label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #314449;
            margin-bottom: 0.3rem;
        }
        .input-text, .input-textarea {
            width: 100%;
            border: 1px solid #D3DEDD;
            border-radius: 10px;
            padding: 0.65rem 0.75rem;
            font-size: 0.9375rem;
            font-family: inherit;
            color: #13262B;
            background: #FFFFFF;
        }
        .input-text { min-height: 44px; }
        .input-file {
            display: block;
            width: 100%;
            font-size: 0.8125rem;
            color: #56686C;
            margin-top: 0.25rem;
        }
        .photo-wrap { margin-top: 0.65rem; }
        .btn-submit {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 50px;
            border-radius: 999px;
            background: #0E7C7B;
            color: #FFFFFF;
            border: none;
            font-size: 1rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            margin-top: 0.5rem;
        }
        .btn-back {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 44px;
            border-radius: 999px;
            background: #FFFFFF;
            color: #13262B;
            border: 1px solid #D3DEDD;
            font-size: 0.9375rem;
            font-weight: 600;
            text-decoration: none;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <main class="wrap">
        <div class="card">
            <div class="brand">
                <div class="brand-left">
                    <img src="{{ $logoUrl }}" alt="Logo {{ $appName }}">
                    <span>{{ $hospitalName }}</span>
                </div>
                <span class="code-pill">{{ $equipment->inventory_code }}</span>
            </div>
            <h1>Catat Pemeliharaan Preventif</h1>
            <p class="sub">
                <strong>{{ $equipment->name }}</strong> · {{ $equipment->room?->name ?? '-' }} · Interval {{ $equipment->equipmentType->pm_interval_months }} bulan
            </p>
        </div>

        @if ($errors->any())
            <div class="alert-error" role="alert">
                <strong>Periksa isian berikut sebelum menyimpan:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $submitUrl }}" enctype="multipart/form-data">
            @csrf

            @foreach ($checklistItems as $idx => $itemLabel)
                @php
                    $oldStatus = old("checklist.{$idx}.status");
                    $oldNote = old("checklist.{$idx}.note");
                @endphp
                <div class="check-card">
                    <div class="check-num">Butir {{ $idx + 1 }} dari {{ count($checklistItems) }}</div>
                    <div class="check-title">{{ $itemLabel }}</div>
                    <input type="hidden" name="checklist[{{ $idx }}][item]" value="{{ $itemLabel }}">

                    <div class="choice-grid">
                        <div>
                            <input
                                class="choice-input"
                                type="radio"
                                id="check-{{ $idx }}-good"
                                name="checklist[{{ $idx }}][status]"
                                value="good"
                                @checked($oldStatus === 'good')
                                required
                            >
                            <label class="choice-label choice-good" for="check-{{ $idx }}-good">
                                ✓ Baik
                            </label>
                        </div>
                        <div>
                            <input
                                class="choice-input"
                                type="radio"
                                id="check-{{ $idx }}-bad"
                                name="checklist[{{ $idx }}][status]"
                                value="needs_attention"
                                @checked($oldStatus === 'needs_attention')
                                required
                            >
                            <label class="choice-label choice-bad" for="check-{{ $idx }}-bad">
                                ! Tidak baik
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="field-label" for="note-{{ $idx }}">Catatan (wajib bila Tidak baik)</label>
                        <input
                            class="input-text"
                            type="text"
                            id="note-{{ $idx }}"
                            name="checklist[{{ $idx }}][note]"
                            value="{{ $oldNote }}"
                            placeholder="Tulis catatan temuan bila tidak baik..."
                            maxlength="500"
                        >
                    </div>

                    <div class="photo-wrap">
                        <label class="field-label" for="photo-{{ $idx }}">Foto (opsional, JPG/PNG maks 3 MB)</label>
                        <input
                            class="input-file"
                            type="file"
                            id="photo-{{ $idx }}"
                            name="checklist[{{ $idx }}][photo]"
                            accept="image/jpeg,image/png"
                            capture="environment"
                        >
                    </div>
                </div>
            @endforeach

            <div class="card">
                <label class="field-label" for="pm-notes">Catatan Umum (Opsional)</label>
                <textarea
                    class="input-textarea"
                    id="pm-notes"
                    name="notes"
                    rows="3"
                    maxlength="2000"
                    placeholder="Ringkasan kondisi alat atau tindakan ringan yang dilakukan..."
                >{{ old('notes') }}</textarea>
            </div>

            <button type="submit" class="btn-submit">Simpan pemeliharaan</button>
            <a href="{{ $backUrl }}" class="btn-back">Batal &amp; kembali ke status alat</a>
        </form>
    </main>
</body>
</html>
