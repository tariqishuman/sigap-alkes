@php
    $settings = app(\App\Services\SettingsService::class);
@endphp
<div class="flex items-center gap-3">
    <div class="flex h-9 w-9 shrink-0 items-center justify-center p-2">
        <img
            src="{{ $settings->logoUrl() }}"
            alt="Logo {{ $settings->appName() }}"
            class="h-full w-full object-contain"
        />
    </div>
    <div class="flex flex-col leading-tight">
        <span class="text-base font-bold tracking-tight text-primary">
            {{ $settings->appName() }}
        </span>
    </div>
</div>
