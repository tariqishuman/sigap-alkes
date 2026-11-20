@props([
    'value' => null,
    'direction' => null, // 'up' | 'down' | 'flat'
    'polarity' => 'good_up', // 'good_up' | 'good_down' | 'neutral'
])

@php
    $show = filled($value) && $direction && $direction !== 'flat';

    $isGood = match ($polarity) {
        'good_up' => $direction === 'up',
        'good_down' => $direction === 'down',
        default => null,
    };

    $colors = match ($isGood) {
        true => 'bg-[#E7F5EE] text-[#22774F]',
        false => 'bg-[#FBEAEA] text-[#B83C38]',
        null => 'bg-[#EFF2F2] text-[#55666A]',
    };

    $arrow = $direction === 'up' ? '▲' : '▼';
@endphp

@if ($show)
    <span {{ $attributes->merge([
        'class' => "inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11.5px] leading-[14px] font-semibold tabular-nums {$colors}",
    ]) }}>
        <span aria-hidden="true" class="text-[9px]">{{ $arrow }}</span>
        <span>{{ $value }}</span>
    </span>
@endif
