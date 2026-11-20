@php
    use App\Filament\Pages\CalibrationSchedule;
    use App\Filament\Resources\DamageReports\DamageReportResource;
    use App\Filament\Resources\Equipment\EquipmentResource;
@endphp

<x-filament-widgets::widget>
    <div class="space-y-4">
        <x-sigap.card title="Total Alat Aktif" icon="heroicon-o-cube">
            <div class="flex flex-col gap-3">
                <div class="flex items-baseline gap-2">
                    <span class="text-[28px] leading-[32px] font-bold text-[#13262B] tabular-nums">
                        {{ number_format($data['total_equipment'], 0, ',', '.') }}
                    </span>
                    <span class="text-[13px] font-medium text-[#6B7C80]">
                        unit di {{ $data['room_count'] }} ruangan
                    </span>
                </div>

                <x-sigap.status-bar
                    :counts="$data['status_counts']"
                    :filter-url-base="EquipmentResource::getUrl('index')"
                />
            </div>
        </x-sigap.card>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <x-sigap.card title="Jatuh Tempo Terdekat" icon="heroicon-o-calendar-days">
                <x-sigap.accent-list
                    :items="$data['upcoming_calibrations']"
                    header-left="Alat"
                    header-right="Sisa"
                    :view-all-url="CalibrationSchedule::getUrl()"
                    empty-text="Tidak ada kalibrasi jatuh tempo dalam waktu dekat."
                />
            </x-sigap.card>

            <x-sigap.card title="Laporan Kerusakan Terbuka" icon="heroicon-o-exclamation-triangle">
                <x-sigap.accent-list
                    :items="$data['open_reports']"
                    header-left="Alat & keluhan"
                    header-right="Status"
                    :view-all-url="DamageReportResource::getUrl('index')"
                    empty-text="Tidak ada kerusakan. Semua alat aman."
                />
            </x-sigap.card>
        </div>
    </div>
</x-filament-widgets::widget>
