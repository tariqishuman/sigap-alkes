@php
    use App\Filament\Resources\PreventiveMaintenances\PreventiveMaintenanceResource;
@endphp

<x-filament-panels::page>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            {{-- Top schedule tabs: Kalibrasi | Pemeliharaan --}}
            <div class="inline-flex items-center rounded-full bg-white border border-[#E3EAEA] p-[3px]" role="tablist" aria-label="Jenis jadwal">
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $activeScheduleTab === 'calibration' ? 'true' : 'false' }}"
                    wire:click="setActiveScheduleTab('calibration')"
                    class="rounded-full px-3.5 py-1.5 text-[12.5px] font-semibold transition-all cursor-pointer {{ $activeScheduleTab === 'calibration' ? 'bg-[#0E7C7B] text-white' : 'text-[#6B7C80] hover:text-[#13262B]' }}"
                >
                    Kalibrasi
                </button>
                @if ($this->canViewPreventiveMaintenanceTab())
                    <button
                        type="button"
                        role="tab"
                        aria-selected="{{ $activeScheduleTab === 'pm' ? 'true' : 'false' }}"
                        wire:click="setActiveScheduleTab('pm')"
                        class="rounded-full px-3.5 py-1.5 text-[12.5px] font-semibold transition-all cursor-pointer {{ $activeScheduleTab === 'pm' ? 'bg-[#0E7C7B] text-white' : 'text-[#6B7C80] hover:text-[#13262B]' }}"
                    >
                        Pemeliharaan
                    </button>
                @endif
            </div>

            @if ($activeScheduleTab === 'calibration')
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
            @endif
        </div>

        @if ($activeScheduleTab === 'pm' && PreventiveMaintenanceResource::canCreate())
            <a
                href="{{ PreventiveMaintenanceResource::getUrl('create') }}"
                class="inline-flex items-center gap-1.5 rounded-full bg-[#0E7C7B] px-4 py-2 text-[12.5px] font-semibold text-white hover:bg-[#0B6665] transition-colors"
            >
                <span>+ Catat pemeliharaan</span>
            </a>
        @endif
    </div>

    @if ($activeScheduleTab === 'pm')
        @php $pmGroups = $this->getGroupedPmSchedule(); @endphp
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach ($pmGroups as $group)
                <x-sigap.card :title="$group['title'] . ' (' . count($group['items']) . ')'" icon="heroicon-o-clipboard-document-list">
                    <x-sigap.accent-list
                        :items="array_slice($group['items'], 0, 10)"
                        header-left="Alat"
                        header-right="Jadwal PM"
                        empty-text="Tidak ada alat pada kelompok pemeliharaan ini."
                    />
                </x-sigap.card>
            @endforeach
        </div>
    @else
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
    @endif
</x-filament-panels::page>
