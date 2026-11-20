@php
    use App\Filament\Resources\Equipment\EquipmentResource;
    use App\Filament\Resources\Repairs\RepairResource;

    $total = $metrics['total_equipment'];
    $comp = $metrics['compliance'];
    $up = $metrics['uptime'];
    $rep = $metrics['repair_duration'];
    $over = $metrics['overdue'];

    $compDir = $comp['delta'] === null || $comp['delta'] == 0 ? 'flat' : ($comp['delta'] > 0 ? 'up' : 'down');
    $repDir = $rep['delta'] === null || $rep['delta'] == 0 ? 'flat' : ($rep['delta'] > 0 ? 'up' : 'down');
    $overDir = $over['delta'] === null || $over['delta'] == 0 ? 'flat' : ($over['delta'] > 0 ? 'up' : 'down');
@endphp

<x-filament-widgets::widget>
    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
        {{-- Card 1: Total Alat Aktif (4 cols) --}}
        <x-sigap.card title="Total Alat Aktif" icon="heroicon-o-cube" class="md:col-span-6 xl:col-span-4">
            <x-slot:actions>
                <a
                    href="{{ EquipmentResource::getUrl('index') }}"
                    title="Lihat daftar alat"
                    class="text-[#97A6A9] hover:text-[#13262B] px-1 text-lg leading-none"
                >⋯</a>
            </x-slot:actions>

            <div class="flex flex-col flex-1 justify-between gap-3">
                <div class="flex items-baseline gap-2">
                    <span class="text-[28px] leading-[32px] font-bold tracking-[-0.02em] text-[#13262B] tabular-nums">
                        {{ number_format($total['count'], 0, ',', '.') }}
                    </span>
                    <span class="text-[13px] font-medium text-[#6B7C80]">
                        unit di {{ $total['room_count'] }} ruangan
                    </span>
                </div>

                <x-sigap.status-bar
                    :counts="$total['status_counts']"
                    :filter-url-base="EquipmentResource::getUrl('index')"
                />
            </div>
        </x-sigap.card>

        {{-- Card 2: Kepatuhan Kalibrasi (5 cols) --}}
        @php
            $pmRateLabel = isset($pmCompliance) && $pmCompliance['rate'] !== null
                ? number_format($pmCompliance['rate'], 1, ',', '.') . '%'
                : '–';
        @endphp
        <x-sigap.card title="Kepatuhan Kalibrasi" icon="heroicon-o-shield-check" class="md:col-span-6 xl:col-span-5">
            <x-sigap.kpi
                caption="Alat wajib kalibrasi dengan sertifikat berlaku"
                :value="$comp['formatted']"
                :delta-value="$comp['delta_label']"
                :delta-direction="$compDir"
                delta-polarity="good_up"
                :sparkline="$comp['sparkline']"
                sparkline-tone="primary"
                :stats="[
                    ['label' => 'Wajib kalibrasi', 'value' => number_format($comp['required_count'], 0, ',', '.')],
                    ['label' => 'Bersertifikat', 'value' => number_format($comp['compliant_count'], 0, ',', '.')],
                    ['label' => 'Jatuh tempo 30 hari', 'value' => number_format($comp['due_30_days'], 0, ',', '.')],
                    ['label' => 'Kepatuhan pemeliharaan', 'value' => $pmRateLabel],
                ]"
            />
        </x-sigap.card>

        {{-- Card 3: Uptime Bulan Ini (3 cols, row-span-2 on desktop) --}}
        <x-sigap.card title="Uptime Bulan Ini" icon="heroicon-o-chart-bar" class="md:col-span-12 xl:col-span-3 xl:row-span-2">
            <x-sigap.gauge
                :percent="$up['percent']"
                :target="$up['target_percent']"
                caption="rata-rata seluruh alat"
                :uptime-hours="$up['total_hours']"
                :downtime-hours="$up['downtime_hours']"
                :stats="[
                    ['label' => 'Bulan lalu', 'value' => $up['previous_percent'] !== null ? number_format($up['previous_percent'], 1, ',', '.').'%' : '–'],
                    ['label' => 'Terendah', 'value' => $up['lowest_room']],
                ]"
            />
        </x-sigap.card>

        {{-- Card 4: Rata-rata Lama Perbaikan (4 cols) --}}
        <x-sigap.card title="Rata-rata Lama Perbaikan" icon="heroicon-o-clock" class="md:col-span-6 xl:col-span-4">
            <x-slot:actions>
                <a
                    href="{{ RepairResource::getUrl('index') }}"
                    title="Lihat daftar perbaikan"
                    class="text-[#97A6A9] hover:text-[#13262B] px-1 text-lg leading-none"
                >⋯</a>
            </x-slot:actions>

            <x-sigap.kpi
                caption="Dari laporan kerusakan sampai alat selesai diperbaiki"
                :value="$rep['formatted']"
                :unit="$rep['avg_days'] !== null ? 'hari' : null"
                :delta-value="$rep['delta_label']"
                :delta-direction="$repDir"
                delta-polarity="good_down"
                :stats="[
                    ['label' => 'Selesai periode ini', 'value' => number_format($rep['finished_count'], 0, ',', '.')],
                    ['label' => 'Masih berjalan', 'value' => number_format($rep['in_progress_count'], 0, ',', '.')],
                    ['label' => 'Terlama', 'value' => $rep['longest_days'] !== null ? $rep['longest_days'].' hari' : '–'],
                ]"
            />
        </x-sigap.card>

        {{-- Card 5: Terlambat Kalibrasi (5 cols) --}}
        <x-sigap.card title="Terlambat Kalibrasi" icon="heroicon-o-exclamation-triangle" class="md:col-span-6 xl:col-span-5">
            <x-sigap.kpi
                caption="Alat dengan sertifikat yang sudah lewat"
                :value="number_format($over['count'], 0, ',', '.')"
                unit="alat"
                :delta-value="$over['delta_label']"
                :delta-direction="$overDir"
                delta-polarity="good_down"
                :sparkline="$over['sparkline']"
                sparkline-tone="danger"
                :stats="[
                    ['label' => 'Lewat 1–7 hari', 'value' => number_format($over['lte_7'], 0, ',', '.')],
                    ['label' => 'Lewat 8–30 hari', 'value' => number_format($over['days_8_30'], 0, ',', '.')],
                    ['label' => 'Lewat > 30 hari', 'value' => number_format($over['gt_30'], 0, ',', '.')],
                ]"
            />
        </x-sigap.card>
    </div>
</x-filament-widgets::widget>
