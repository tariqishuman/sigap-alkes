@props([
    'value' => '–',
    'unit' => null,
    'caption' => null,
    'deltaValue' => null,
    'deltaDirection' => null,
    'deltaPolarity' => 'good_up',
    'sparkline' => [],
    'sparklineTone' => 'primary',
    'stats' => [],
])

@php
    $hasSparkline = is_array($sparkline) && count($sparkline) >= 2;
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col flex-1 gap-3']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="{{ $hasSparkline ? 'max-w-[58%]' : 'w-full' }}">
            @if ($caption)
                <p class="text-[12.5px] leading-[17px] text-[#6B7C80] mb-1.5">
                    {{ $caption }}
                </p>
            @endif

            <div class="flex flex-wrap items-baseline gap-2">
                <span class="text-[28px] leading-[32px] font-bold tracking-[-0.02em] text-[#13262B] tabular-nums">
                    {{ $value }}
                </span>

                @if ($unit)
                    <span class="text-[13px] font-medium text-[#6B7C80]">{{ $unit }}</span>
                @endif

                <x-sigap.delta
                    :value="$deltaValue"
                    :direction="$deltaDirection"
                    :polarity="$deltaPolarity"
                />
            </div>
        </div>

        @if ($hasSparkline)
            <div class="w-[38%] shrink-0 pt-1">
                <x-sigap.sparkline :values="$sparkline" :tone="$sparklineTone" />
            </div>
        @endif
    </div>

    @if ($slot->isNotEmpty())
        <div>{{ $slot }}</div>
    @endif

    @if (! empty($stats))
        <x-sigap.stat-row :items="$stats" />
    @endif
</div>
