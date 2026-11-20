@php
    use App\Filament\Pages\CalibrationSchedule;
    use App\Filament\Resources\Calibrations\CalibrationResource;
@endphp

<x-filament-widgets::widget>
    <div class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-sigap.card title="Alat Perlu Kalibrasi" icon="heroicon-o-calendar-days">
                <x-sigap.kpi
                    caption="Alat berstatus perlu kalibrasi, terlambat, atau dalam uji"
                    :value="number_format($data['needs_calibration_count'], 0, ',', '.')"
                    unit="alat"
                    :stats="[
                        ['label' => 'Terlambat', 'value' => number_format($data['overdue_count'], 0, ',', '.')],
                        ['label' => 'Perlu kalibrasi', 'value' => number_format($data['due_count'], 0, ',', '.')],
                    ]"
                />
            </x-sigap.card>

            <x-sigap.card title="Hasil Kalibrasi Bulan Ini" icon="heroicon-o-shield-check">
                <x-sigap.kpi
                    caption="Dicatat oleh lembaga Anda pada bulan ini"
                    :value="number_format($data['recorded_this_month'], 0, ',', '.')"
                    unit="kalibrasi"
                    :stats="[
                        ['label' => 'Laik pakai', 'value' => number_format($data['passed_this_month'], 0, ',', '.')],
                        ['label' => 'Tidak laik', 'value' => number_format($data['failed_this_month'], 0, ',', '.')],
                    ]"
                />
            </x-sigap.card>
        </div>

        <x-sigap.card title="Prioritas Kalibrasi" icon="heroicon-o-clipboard-document-check">
            <x-slot:actions>
                <a
                    href="{{ CalibrationResource::getUrl('create') }}"
                    class="inline-flex items-center gap-1.5 rounded-full bg-[#0E7C7B] hover:bg-[#0B6463] text-white font-semibold text-[12.5px] h-8 px-3.5 transition-colors"
                >
                    + Catat hasil
                </a>
            </x-slot:actions>

            <x-sigap.accent-list
                :items="$data['needs_calibration_items']"
                header-left="Alat"
                header-right="Sisa / status"
                :view-all-url="CalibrationSchedule::getUrl()"
                empty-text="Tidak ada alat yang menunggu kalibrasi."
            />
        </x-sigap.card>
    </div>
</x-filament-widgets::widget>
