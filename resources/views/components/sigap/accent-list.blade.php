@props([
    'items' => [], // array of ['title', 'subtitle', 'chip', 'tone' ('fit'|'due'|'overdue'|'testing'|'repair'|'unfit'), 'url' => null]
    'viewAllUrl' => null,
    'viewAllLabel' => 'Lihat semua',
    'emptyText' => 'Tidak ada data.',
])

@php
    $toneMap = [
        'fit' => ['bar' => '#2E9E6B', 'bg' => '#E7F5EE', 'text' => '#22774F'],
        'due' => ['bar' => '#E0A23B', 'bg' => '#FCF3E2', 'text' => '#A66F12'],
        'overdue' => ['bar' => '#D9534F', 'bg' => '#FBEAEA', 'text' => '#B83C38'],
        'testing' => ['bar' => '#3B82F6', 'bg' => '#EAF1FE', 'text' => '#2563EB'],
        'repair' => ['bar' => '#8A9699', 'bg' => '#EFF2F2', 'text' => '#55666A'],
        'unfit' => ['bar' => '#B83C38', 'bg' => '#FBEAEA', 'text' => '#9A2F2C'],
    ];
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col flex-1 justify-between gap-2']) }}>
    @if (empty($items))
        <p class="text-[13px] text-[#6B7C80] py-6 text-center">{{ $emptyText }}</p>
    @else
        <ul class="divide-y divide-[#E3EAEA]">
            @foreach ($items as $item)
                @php
                    $colors = $toneMap[$item['tone'] ?? 'repair'] ?? $toneMap['repair'];
                    $tag = ! empty($item['url']) ? 'a' : 'div';
                @endphp
                <li>
                    <{{ $tag }}
                        @if (! empty($item['url'])) href="{{ $item['url'] }}" @endif
                        class="flex items-center justify-between gap-3 py-2.5 pl-3 relative rounded-[8px] transition-colors {{ ! empty($item['url']) ? 'hover:bg-[#F4F7F7]' : '' }}"
                    >
                        <span
                            class="absolute left-0 top-2 bottom-2 w-[3px] rounded-full"
                            style="background-color: {{ $colors['bar'] }};"
                            aria-hidden="true"
                        ></span>

                        <div class="min-w-0">
                            <div class="text-[13px] leading-[18px] font-semibold text-[#13262B] truncate">
                                {{ $item['title'] }}
                            </div>
                            @if (! empty($item['subtitle']))
                                <div class="text-[11.5px] leading-[15px] text-[#6B7C80] truncate">
                                    {{ $item['subtitle'] }}
                                </div>
                            @endif
                        </div>

                        @if (! empty($item['chip']))
                            <span
                                class="shrink-0 rounded-full px-2.5 py-0.5 text-[11px] leading-[14px] font-semibold tabular-nums whitespace-nowrap"
                                style="background-color: {{ $colors['bg'] }}; color: {{ $colors['text'] }};"
                            >
                                {{ $item['chip'] }}
                            </span>
                        @endif
                    </{{ $tag }}>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($viewAllUrl)
        <div class="pt-2 border-t border-[#E3EAEA] text-right">
            <a href="{{ $viewAllUrl }}" class="text-[12.5px] font-semibold text-[#0E7C7B] hover:text-[#0B6463]">
                {{ $viewAllLabel }} →
            </a>
        </div>
    @endif
</div>
