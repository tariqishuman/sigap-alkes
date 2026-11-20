<x-filament-panels::page>
    @php
        $summary = $this->getSummary();
        $uptime = $summary['uptime'];
        $compliance = $summary['compliance'];
    @endphp

    <x-sigap.card>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label for="report-month" class="block text-[12px] font-semibold text-[#3A4D52]">
                    Periode Bulan
                </label>
                <select
                    id="report-month"
                    wire:model.live="selectedMonth"
                    class="mt-1.5 block w-full h-10 rounded-[8px] border border-[#E3EAEA] bg-white px-3 text-[13.5px] text-[#13262B] focus:border-[#0E7C7B] focus:outline-none"
                >
                    @foreach ($this->getMonthOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="report-room" class="block text-[12px] font-semibold text-[#3A4D52]">
                    Ruangan
                </label>
                <select
                    id="report-room"
                    wire:model.live="selectedRoomId"
                    class="mt-1.5 block w-full h-10 rounded-[8px] border border-[#E3EAEA] bg-white px-3 text-[13.5px] text-[#13262B] focus:border-[#0E7C7B] focus:outline-none"
                >
                    <option value="">Semua Ruangan</option>
                    @foreach ($this->getRoomOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-sigap.card>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-sigap.card title="Uptime Periode" icon="heroicon-o-chart-bar">
            <x-sigap.kpi
                :caption="$summary['period_label'] . ' · rata-rata seluruh alat aktif'"
                :value="$uptime['uptime_percent'] !== null ? number_format($uptime['uptime_percent'], 2, ',', '.').'%' : '–'"
            />
        </x-sigap.card>

        <x-sigap.card title="Total Jam Operasi" icon="heroicon-o-clock">
            <x-sigap.kpi
                :caption="$uptime['equipment_count'] . ' alat aktif dihitung'"
                :value="number_format($uptime['total_hours'], 1, ',', '.')"
                unit="jam"
            />
        </x-sigap.card>

        <x-sigap.card title="Total Downtime" icon="heroicon-o-wrench-screwdriver">
            <x-sigap.kpi
                caption="Selama dalam perbaikan dan tidak laik pakai"
                :value="number_format($uptime['downtime_hours'], 1, ',', '.')"
                unit="jam"
            />
        </x-sigap.card>

        <x-sigap.card title="Kepatuhan Kalibrasi" icon="heroicon-o-shield-check">
            <x-sigap.kpi
                :caption="$compliance['compliant_count'] . ' dari ' . $compliance['required_count'] . ' alat wajib (' . $summary['room_label'] . ')'"
                :value="$compliance['formatted']"
            />
        </x-sigap.card>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2 rounded-[12px] border border-[#E3EAEA] bg-white px-4 py-2.5 text-[12.5px] text-[#6B7C80]">
        <span>Catatan: Alat yang dimutasi di tengah bulan dihitung ke ruangan saat ini. Format kolom ekspor ASPAK bersifat sementara.</span>
        <span class="inline-flex items-center rounded-full bg-[#FCF3E2] px-2.5 py-0.5 text-[11px] font-semibold text-[#A66F12]">
            Format sementara
        </span>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
