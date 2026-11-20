@php
    use App\Filament\Pages\AppSettings;
    use App\Services\SettingsService;

    $canSettings = AppSettings::canAccess();
    $settingsUrl = $canSettings ? AppSettings::getUrl() : null;
    $isSettingsActive = request()->routeIs('filament.admin.pages.pengaturan-aplikasi');
    $settings = app(SettingsService::class);
    $hospitalPhone = $settings->get('general.hospital_phone', '(031) 5994251');
@endphp

<div x-data="{ helpOpen: false }" class="mt-auto border-t border-[#E3EAEA] px-3 py-3 space-y-1 bg-white">
    @if ($canSettings && $settingsUrl)
        <a
            href="{{ $settingsUrl }}"
            class="flex items-center gap-2.5 h-9 px-2.5 rounded-[8px] text-[13.5px] transition-colors {{ $isSettingsActive ? 'bg-[#E8F4F3] text-[#0E7C7B] font-semibold' : 'text-[#3A4D52] font-medium hover:bg-[#F4F7F7]' }}"
        >
            <x-filament::icon
                :icon="$isSettingsActive ? 'heroicon-s-cog-6-tooth' : 'heroicon-o-cog-6-tooth'"
                class="h-[18px] w-[18px] shrink-0 {{ $isSettingsActive ? 'text-[#0E7C7B]' : 'text-[#6B7C80]' }}"
            />
            <span class="fi-sidebar-item-label truncate">Pengaturan</span>
        </a>
    @endif

    <button
        type="button"
        @click="helpOpen = true"
        class="w-full flex items-center justify-start text-left gap-2.5 h-9 px-2.5 rounded-[8px] text-[13.5px] font-medium text-[#3A4D52] hover:bg-[#F4F7F7] transition-colors cursor-pointer"
    >
        <x-filament::icon icon="heroicon-o-question-mark-circle" class="h-[18px] w-[18px] shrink-0 text-[#6B7C80]" />
        <span class="fi-sidebar-item-label truncate">Bantuan</span>
    </button>

    <template x-teleport="body">
        <div
            x-show="helpOpen"
            x-cloak
            @keydown.escape.window="helpOpen = false"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#13262B]/40 backdrop-blur-[1px] p-4"
        >
            <div
                @click.outside="helpOpen = false"
                class="w-full max-w-md rounded-[14px] bg-white border border-[#E3EAEA] shadow-[0_12px_32px_-12px_rgba(19,38,43,0.18)] p-5 space-y-3"
            >
                <div class="flex items-center justify-between">
                    <h3 class="text-[15px] font-bold text-[#13262B]">Bantuan Cepat</h3>
                    <button type="button" @click="helpOpen = false" class="text-[#6B7C80] hover:text-[#13262B] text-sm font-semibold cursor-pointer">Tutup</button>
                </div>
                <ul class="space-y-2 text-[13px] text-[#3A4D52]">
                    <li>• Tekan <kbd class="rounded border border-[#E3EAEA] bg-[#F4F7F7] px-1.5 py-0.5 text-[11px] font-semibold">Ctrl K</kbd> untuk mencari alat, kode inventaris, atau nomor seri.</li>
                    <li>• Gunakan tombol <strong>+ Tambah</strong> di pojok kanan atas untuk mencatat kerusakan, kalibrasi, atau mutasi secara cepat.</li>
                    <li>• Kontak IPSRS: <strong>{{ $hospitalPhone }}</strong>.</li>
                </ul>
            </div>
        </div>
    </template>
</div>
