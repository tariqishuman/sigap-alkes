@props([
    'percent' => null, // float|null (0..100)
    'target' => 95,
    'caption' => 'rata-rata seluruh alat',
    'uptimeHours' => null,
    'downtimeHours' => null,
    'stats' => [],
])

@php
    $pct = $percent !== null ? max(0.0, min(100.0, (float) $percent)) : null;
    // Semicircle radius 76, center (100, 96), circumference of half-circle = pi * 76 ≈ 238.76
    $arcLen = 238.76;
    $uptimeLen = $pct !== null ? round(($pct / 100.0) * $arcLen, 2) : 0;
    $downtimeLen = $pct !== null ? round(((100.0 - $pct) / 100.0) * $arcLen, 2) : 0;
    $meetsTarget = $pct !== null && $pct >= (float) $target;
    $formattedPct = $pct !== null ? number_format($pct, 1, ',', '.').'%' : '–';
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col flex-1 justify-between gap-3']) }}>
    <div class="relative mx-auto w-full max-w-[210px] pt-1">
        <svg viewBox="0 0 200 112" class="w-full h-auto block" role="img" aria-label="Uptime {{ $formattedPct }}">
            {{-- Track --}}
            <path
                d="M 24 96 A 76 76 0 0 1 176 96"
                fill="none"
                stroke="#EFF2F2"
                stroke-width="22"
                stroke-linecap="butt"
            />
            @if ($pct !== null)
                {{-- Aqua remainder (downtime) --}}
                <path
                    d="M 24 96 A 76 76 0 0 1 176 96"
                    fill="none"
                    stroke="#5CC8C4"
                    stroke-width="22"
                    stroke-dasharray="{{ $arcLen }} {{ $arcLen }}"
                />
                {{-- Primary value (uptime) --}}
                <path
                    d="M 24 96 A 76 76 0 0 1 176 96"
                    fill="none"
                    stroke="#0E7C7B"
                    stroke-width="22"
                    stroke-dasharray="{{ $uptimeLen }} {{ $arcLen }}"
                />
            @endif
        </svg>

        <div class="absolute inset-x-0 bottom-1 text-center">
            <div class="text-[28px] leading-[32px] font-bold tracking-[-0.02em] text-[#13262B] tabular-nums">
                {{ $formattedPct }}
            </div>
            <div class="text-[11.5px] text-[#6B7C80] mt-0.5">{{ $caption }}</div>
        </div>
    </div>

    @if ($uptimeHours !== null || $downtimeHours !== null)
        <div class="grid grid-cols-2 gap-2 text-center pt-1">
            <div>
                <div class="inline-flex items-center gap-1.5 text-[11.5px] text-[#6B7C80]">
                    <span class="h-1.5 w-3 rounded-full bg-[#0E7C7B]"></span>
                    <span>Jam operasi</span>
                </div>
                <div class="text-[15px] font-bold text-[#13262B] tabular-nums mt-0.5">
                    {{ number_format((float) ($uptimeHours ?? 0), 0, ',', '.') }}
                </div>
            </div>
            <div>
                <div class="inline-flex items-center gap-1.5 text-[11.5px] text-[#6B7C80]">
                    <span class="h-1.5 w-3 rounded-full bg-[#5CC8C4]"></span>
                    <span>Downtime</span>
                </div>
                <div class="text-[15px] font-bold text-[#13262B] tabular-nums mt-0.5">
                    {{ number_format((float) ($downtimeHours ?? 0), 0, ',', '.') }}
                </div>
            </div>
        </div>
    @endif

    @if ($pct !== null)
        <div class="rounded-[10px] bg-[#F4F7F7] px-3 py-2 text-center text-[12px] font-medium {{ $meetsTarget ? 'text-[#22774F]' : 'text-[#A66F12]' }}">
            {{ $meetsTarget ? "✓ Di atas target {$target}%" : "Di bawah target {$target}%" }}
        </div>
    @endif

    @if (! empty($stats))
        <x-sigap.stat-row :items="$stats" />
    @endif
</div>
