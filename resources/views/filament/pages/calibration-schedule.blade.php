<x-filament-panels::page>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="inline-flex items-center rounded-full bg-white border border-[#E3EAEA] p-[3px]" role="tablist" aria-label="Mode tampilan jadwal">
            <button
                type="button"
                role="tab"
                aria-selected="{{ $viewMode === 'grouped' ? 'true' : 'false' }}"
                wire:click="setViewMode('grouped')"
                class="rounded-full px-3.5 py-1.5 text-[12.5px] font-semibold transition-all cursor-pointer {{ $viewMode === 'grouped' ? 'bg-[#E8F4F3] text-[#0E7C7B]' : 'text-[#6B7C80] hover:text-[#13262B]' }}"
            >
                Tampilan Kelompok
            </button>
            <button
                type="button"
                role="tab"
                aria-selected="{{ $viewMode === 'table' ? 'true' : 'false' }}"
                wire:click="setViewMode('table')"
                class="rounded-full px-3.5 py-1.5 text-[12.5px] font-semibold transition-all cursor-pointer {{ $viewMode === 'table' ? 'bg-[#E8F4F3] text-[#0E7C7B]' : 'text-[#6B7C80] hover:text-[#13262B]' }}"
            >
                Tampilan Tabel &amp; Ekspor
            </button>
        </div>
    </div>

    @if ($viewMode === 'grouped')
        @php $groups = $this->getGroupedSchedule(); @endphp
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach ($groups as $group)
                <x-sigap.card :title="$group['title'] . ' (' . count($group['items']) . ')'" icon="heroicon-o-calendar-days">
                    <x-sigap.accent-list
                        :items="array_slice($group['items'], 0, 8)"
                        header-left="Alat"
                        header-right="Jatuh tempo"
                        empty-text="Tidak ada alat pada kelompok ini."
                    />
                </x-sigap.card>
            @endforeach
        </div>
    @endif

    <div class="{{ $viewMode === 'table' ? '' : 'hidden' }}">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
