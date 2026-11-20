@props([
    'values' => [],
    'tone' => 'primary', // 'primary' | 'danger'
    'height' => 58,
])

@php
    $numeric = array_values(array_map('floatval', array_filter($values, fn ($v) => $v !== null)));
    $count = count($numeric);
    $stroke = $tone === 'danger' ? '#D9534F' : '#0E7C7B';
    $gradId = 'spark-'.substr(md5($tone.implode(',', $numeric)), 0, 8);

    $linePath = '';
    $areaPath = '';

    if ($count >= 2) {
        $min = min($numeric);
        $max = max($numeric);
        $range = ($max - $min) > 0 ? ($max - $min) : 1.0;

        $w = 160.0;
        $h = (float) $height;
        $padTop = 6.0;
        $padBottom = 6.0;
        $usableH = max(1.0, $h - $padTop - $padBottom);

        $points = [];
        foreach ($numeric as $i => $val) {
            $x = round(($i / ($count - 1)) * $w, 2);
            $y = round($padTop + $usableH - (($val - $min) / $range) * $usableH, 2);
            $points[] = "{$x},{$y}";
        }

        $linePath = 'M '.implode(' L ', $points);
        $areaPath = $linePath." L {$w},{$h} L 0,{$h} Z";
    }
@endphp

@if ($count >= 2)
    <svg
        viewBox="0 0 160 {{ $height }}"
        preserveAspectRatio="none"
        aria-hidden="true"
        {{ $attributes->merge(['class' => 'w-full block', 'style' => "height: {$height}px;"]) }}
    >
        <defs>
            <linearGradient id="{{ $gradId }}" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="{{ $stroke }}" stop-opacity="0.18" />
                <stop offset="100%" stop-color="{{ $stroke }}" stop-opacity="0" />
            </linearGradient>
        </defs>
        <path d="{{ $areaPath }}" fill="url(#{{ $gradId }})" />
        <path d="{{ $linePath }}" fill="none" stroke="{{ $stroke }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
    </svg>
@endif
