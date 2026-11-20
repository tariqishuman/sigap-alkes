@php
    $settings = app(\App\Services\SettingsService::class);
    $appName = $settings->appName();
    $parts = explode('-', $appName, 2);
@endphp
<div class="flex items-center gap-2.5">
    <div class="flex h-7 w-7 shrink-0 items-center justify-center">
        <img
            src="{{ $settings->logoUrl() }}"
            alt="Logo {{ $appName }}"
            class="h-7 w-7 object-contain"
        />
    </div>
    <div class="flex items-baseline leading-tight">
        @if (count($parts) === 2)
            <span class="text-[16px] font-bold tracking-tight text-[#13262B]">{{ $parts[0] }}</span><span class="text-[16px] font-bold tracking-tight text-[#0E7C7B]">-{{ $parts[1] }}</span>
        @else
            <span class="text-[16px] font-bold tracking-tight text-[#13262B]">{{ $appName }}</span>
        @endif
    </div>
</div>
