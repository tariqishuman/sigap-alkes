@props([
    'items' => [], // array of ['label' => string, 'value' => string|int]
])

@php
    $count = count($items);
    $colsClass = match ($count) {
        1 => 'grid-cols-1',
        2 => 'grid-cols-2',
        3 => 'grid-cols-3',
        default => 'grid-cols-2 sm:grid-cols-4',
    };
@endphp

@if ($count > 0)
    <div {{ $attributes->merge([
        'class' => "mt-auto pt-3 border-t border-[#E3EAEA] grid {$colsClass} divide-x divide-[#E3EAEA]",
    ]) }}>
        @foreach ($items as $item)
            <div class="px-2 first:pl-0 last:pr-0 text-center">
                <div class="text-[11.5px] leading-[15px] text-[#6B7C80] truncate">
                    {{ $item['label'] }}
                </div>
                <div class="mt-0.5 text-[16px] leading-[22px] font-bold text-[#13262B] tabular-nums">
                    {{ $item['value'] }}
                </div>
            </div>
        @endforeach
    </div>
@endif
