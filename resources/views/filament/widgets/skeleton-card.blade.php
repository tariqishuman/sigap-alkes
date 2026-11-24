@php
    $variant = $variant ?? 'chart';
    $title = $title ?? 'Memuat…';
@endphp

<div class="rounded-[14px] border border-[#E3EAEA] bg-white p-5 shadow-[0_2px_6px_rgba(19,38,43,0.045)]" aria-busy="true" aria-label="{{ $title }}">
    <div class="flex items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2.5">
            <div class="sigap-skeleton h-5 w-5 rounded-md"></div>
            <div class="sigap-skeleton h-4 w-44 rounded-md"></div>
        </div>
        <div class="sigap-skeleton h-7 w-28 rounded-full"></div>
    </div>

    @if ($variant === 'bars')
        <div class="space-y-3.5 pt-2">
            @foreach ([88, 72, 60, 48, 38, 28] as $w)
                <div class="flex items-center gap-3">
                    <div class="sigap-skeleton h-3.5 w-28 rounded"></div>
                    <div class="sigap-skeleton h-3 rounded-full" style="width: {{ $w }}%;"></div>
                </div>
            @endforeach
        </div>
    @elseif ($variant === 'line')
        <div class="space-y-3">
            <div class="sigap-skeleton h-[200px] w-full rounded-xl"></div>
            <div class="flex justify-between gap-2 pt-1">
                @for ($i = 0; $i < 6; $i++)
                    <div class="sigap-skeleton h-3 w-10 rounded"></div>
                @endfor
            </div>
        </div>
    @else
        <div class="flex items-end justify-between gap-3 h-[210px] pt-4 px-2">
            @foreach ([62, 85, 50, 74, 42, 68, 56] as $h)
                <div class="sigap-skeleton flex-1 rounded-t-lg" style="height: {{ $h }}%;"></div>
            @endforeach
        </div>
        <div class="flex justify-between gap-3 pt-3 px-2">
            @for ($i = 0; $i < 7; $i++)
                <div class="sigap-skeleton h-3 flex-1 rounded"></div>
            @endfor
        </div>
    @endif
</div>
