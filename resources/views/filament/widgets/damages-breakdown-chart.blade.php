@php
    use Filament\Support\Facades\FilamentAsset;

    $breakdown = $this->getBreakdown();
    $activeTab = $this->filter ?? 'type';
    $type = $this->getType();
    $maxHeight = $this->getMaxHeight();
@endphp

<x-filament-widgets::widget class="fi-wi-chart h-full">
    <x-sigap.card
        :title="$activeTab === 'room' ? 'Kerusakan per Ruangan' : 'Kerusakan per Jenis Alat'"
        icon="heroicon-o-wrench-screwdriver"
        class="h-full"
    >
        <x-slot:actions>
            <div class="inline-flex items-center rounded-full bg-[#F4F7F7] p-[3px]" role="tablist" aria-label="Kelompokkan kerusakan">
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $activeTab === 'type' ? 'true' : 'false' }}"
                    wire:click="setGroupBy('type')"
                    class="rounded-full px-3 py-1 text-[12px] font-semibold transition-all cursor-pointer {{ $activeTab === 'type' ? 'bg-white text-[#13262B] shadow-[0_1px_2px_rgba(19,38,43,0.08)]' : 'text-[#6B7C80] hover:text-[#13262B]' }}"
                >
                    Jenis alat
                </button>
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $activeTab === 'room' ? 'true' : 'false' }}"
                    wire:click="setGroupBy('room')"
                    class="rounded-full px-3 py-1 text-[12px] font-semibold transition-all cursor-pointer {{ $activeTab === 'room' ? 'bg-white text-[#13262B] shadow-[0_1px_2px_rgba(19,38,43,0.08)]' : 'text-[#6B7C80] hover:text-[#13262B]' }}"
                >
                    Ruangan
                </button>
            </div>
        </x-slot:actions>

        {{-- Summary strip (§3.9) --}}
        <div class="flex flex-wrap items-center justify-between gap-4 pt-1 pb-3">
            <div class="flex flex-wrap items-center divide-x divide-[#E3EAEA]">
                <div class="pr-5">
                    <div class="text-[11.5px] text-[#6B7C80]">Paling sering rusak</div>
                    <div class="text-[14px] font-bold text-[#13262B]">{{ $breakdown['most_damaged_type'] }}</div>
                </div>
                <div class="px-5">
                    <div class="text-[11.5px] text-[#6B7C80]">Masih terbuka</div>
                    <div class="text-[14px] font-bold text-[#13262B] tabular-nums">{{ number_format($breakdown['open_reports_count'], 0, ',', '.') }} laporan</div>
                </div>
                <div class="pl-5">
                    <div class="text-[11.5px] text-[#6B7C80]">Ruangan terbanyak</div>
                    <div class="text-[14px] font-bold text-[#13262B]">{{ $breakdown['top_room'] }}</div>
                </div>
            </div>

            <div class="flex items-center gap-4 text-[12px] font-medium text-[#3A4D52]">
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-2 w-2 rounded-full bg-[#0E7C7B]"></span>
                    <span>Selesai</span>
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-2 w-2 rounded-full bg-[#5CC8C4]"></span>
                    <span>Masih terbuka</span>
                </span>
            </div>
        </div>

        {{-- Chart.js canvas --}}
        <div class="fi-wi-chart-image flex-1 min-h-[220px]" role="img" aria-label="Grafik kerusakan alat">
            <div
                x-load
                x-load-src="{{ FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                wire:ignore
                data-chart-type="{{ $type }}"
                x-data="chart({
                    cachedData: @js($this->getCachedData()),
                    options: @js($this->getOptions()),
                    type: @js($type),
                })"
                class="fi-wi-chart-frame fi-wi-chart-canvas-ctn fi-wi-chart-frame-no-aspect-ratio"
                style="height: {{ $maxHeight }};"
            >
                <canvas x-ref="canvas" aria-hidden="true" style="width: 100%; height: 100%; max-height: {{ $maxHeight }};"></canvas>
                <span aria-hidden="true" x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
                <span aria-hidden="true" x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
                <span aria-hidden="true" x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
                <span aria-hidden="true" x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
                <span aria-hidden="true" x-ref="tooltipBackgroundColorElement" class="fi-wi-chart-tooltip-bg-color"></span>
                <span aria-hidden="true" x-ref="tooltipTextColorElement" class="fi-wi-chart-tooltip-text-color"></span>
                <span aria-hidden="true" x-ref="tooltipBorderColorElement" class="fi-wi-chart-tooltip-border-color"></span>
            </div>
        </div>
    </x-sigap.card>
</x-filament-widgets::widget>
