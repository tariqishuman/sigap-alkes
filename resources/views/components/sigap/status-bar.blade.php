@props([
    'counts' => [], // keyed by EquipmentStatus value, e.g. ['fit_for_use' => 35, ...]
    'compact' => false,
    'filterUrlBase' => null,
])

@php
    $order = [
        ['key' => 'fit_for_use', 'label' => 'Laik pakai', 'color' => '#2E9E6B'],
        ['key' => 'calibration_due', 'label' => 'Perlu kalibrasi', 'color' => '#E0A23B'],
        ['key' => 'overdue', 'label' => 'Terlambat', 'color' => '#D9534F'],
        ['key' => 'under_testing', 'label' => 'Dalam uji', 'color' => '#3B82F6'],
        ['key' => 'under_repair', 'label' => 'Perbaikan', 'color' => '#8A9699'],
        ['key' => 'unfit', 'label' => 'Tidak laik', 'color' => '#B83C38'],
    ];

    $total = 0;
    foreach ($order as $item) {
        $total += (int) ($counts[$item['key']] ?? 0);
    }
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col gap-2.5']) }}>
    <div class="flex w-full h-[10px] gap-[3px] overflow-hidden" role="img" aria-label="Distribusi status alat kesehatan">
        @if ($total > 0)
            @foreach ($order as $item)
                @php $val = (int) ($counts[$item['key']] ?? 0); @endphp
                @if ($val > 0)
                    @php $pct = round(($val / $total) * 100, 2); @endphp
                    @if ($filterUrlBase)
                        <a
                            href="{{ $filterUrlBase }}?filters[status][value]={{ $item['key'] }}"
                            title="{{ $item['label'] }}: {{ $val }}"
                            class="h-full rounded-[3px] transition-opacity hover:opacity-80"
                            style="width: {{ $pct }}%; background-color: {{ $item['color'] }};"
                        ></a>
                    @else
                        <div
                            title="{{ $item['label'] }}: {{ $val }}"
                            class="h-full rounded-[3px]"
                            style="width: {{ $pct }}%; background-color: {{ $item['color'] }};"
                        ></div>
                    @endif
                @endif
            @endforeach
        @else
            <div class="h-full w-full rounded-[3px] bg-[#EFF2F2]"></div>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-x-3.5 gap-y-1.5 text-[12px] leading-[16px]">
        @foreach ($order as $item)
            @php $val = (int) ($counts[$item['key']] ?? 0); @endphp
            @if (! $compact || $val > 0)
                <div class="inline-flex items-center gap-1.5 text-[#3A4D52]">
                    <span class="h-2 w-2 rounded-full shrink-0" style="background-color: {{ $item['color'] }};" aria-hidden="true"></span>
                    <span>{{ $item['label'] }}</span>
                    <strong class="font-bold text-[#13262B] tabular-nums">{{ number_format($val, 0, ',', '.') }}</strong>
                </div>
            @endif
        @endforeach
    </div>
</div>
