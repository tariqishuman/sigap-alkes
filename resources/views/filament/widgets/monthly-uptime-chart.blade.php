@php
    use Filament\Support\Facades\FilamentAsset;

    $trend = $this->getTrend();
    $type = $this->getType();
    $maxHeight = $this->getMaxHeight();
@endphp

<x-filament-widgets::widget class="fi-wi-chart h-full">
    <x-sigap.card title="Uptime 12 Bulan Terakhir" icon="heroicon-o-chart-bar" class="h-full">
        <x-slot:actions>
            <div class="flex items-center gap-4 text-[12px] font-medium text-[#3A4D52]">
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-2 w-2 rounded-full bg-[#0E7C7B]"></span>
                    <span>Uptime</span>
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-2 w-2 rounded-full bg-[#97A6A9]"></span>
                    <span>Target {{ $trend['target_percent'] }}%</span>
                </span>
            </div>
        </x-slot:actions>

        <div class="fi-wi-chart-image flex-1 pt-2" role="img" aria-label="Grafik uptime 12 bulan terakhir">
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
