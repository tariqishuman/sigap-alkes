@props([
    'title' => null,
    'icon' => null,
    'padding' => true,
])

@php
    $hasHeader = filled($title) || isset($header) || isset($actions);
@endphp

<div {{ $attributes->merge([
    'class' => 'bg-white border border-[#E3EAEA] rounded-[14px] shadow-[0_1px_2px_rgba(19,38,43,0.04)] flex flex-col',
]) }}>
    @if ($hasHeader)
        <div class="flex items-center justify-between gap-3 {{ $padding ? 'px-[18px] pt-4 pb-2' : 'px-[18px] py-3.5 border-b border-[#E3EAEA]' }}">
            <div class="flex items-center gap-2 min-w-0">
                @if ($icon)
                    <x-filament::icon :icon="$icon" class="h-[18px] w-[18px] shrink-0 text-[#3A4D52]" />
                @endif
                @if ($title)
                    <h3 class="text-[14px] leading-[20px] font-semibold text-[#13262B] truncate">
                        {{ $title }}
                    </h3>
                @endif
                @isset($header)
                    {{ $header }}
                @endisset
            </div>

            @isset($actions)
                <div class="flex items-center gap-2 shrink-0">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    <div class="{{ $padding ? ($hasHeader ? 'px-[18px] pb-4 pt-1' : 'px-[18px] py-4') . ' flex-1 flex flex-col' : 'flex-1 flex flex-col' }}">
        {{ $slot }}
    </div>
</div>
