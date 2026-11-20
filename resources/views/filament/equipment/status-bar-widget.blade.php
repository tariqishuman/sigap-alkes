<x-filament-widgets::widget>
    <x-sigap.card>
        <div class="flex flex-wrap items-center justify-between gap-2 mb-2.5">
            <div class="text-[13px] font-semibold text-[#13262B]">
                Distribusi Status Alat Aktif
                <span class="ml-1.5 text-[12px] font-medium text-[#6B7C80] tabular-nums">({{ number_format($total, 0, ',', '.') }} unit)</span>
            </div>
            <span class="text-[11.5px] text-[#6B7C80]">Klik segmen untuk menyaring status</span>
        </div>

        <x-sigap.status-bar :counts="$counts" :filter-url-base="$filterUrlBase" />
    </x-sigap.card>
</x-filament-widgets::widget>
