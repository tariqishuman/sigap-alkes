@php
    use App\Filament\Pages\CalibrationSchedule;
    use App\Filament\Resources\DamageReports\DamageReportResource;
    use App\Filament\Resources\Equipment\EquipmentResource;
@endphp

<x-filament-widgets::widget>
    <div class="space-y-4">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
            <x-sigap.card :title="'Alat di ' . $data['room_name']" icon="heroicon-o-cube" class="lg:col-span-8">
                <div class="flex flex-col gap-3">
                    <div class="flex items-baseline gap-2">
                        <span class="text-[28px] leading-[32px] font-bold text-[#13262B] tabular-nums">
                            {{ number_format($data['total_equipment'], 0, ',', '.') }}
                        </span>
                        <span class="text-[13px] font-medium text-[#6B7C80]">alat aktif di ruangan Anda</span>
                    </div>

                    <x-sigap.status-bar
                        :counts="$data['status_counts']"
                        :filter-url-base="EquipmentResource::getUrl('index')"
                    />
                </div>
            </x-sigap.card>

            <x-sigap.card title="Tindakan Cepat" icon="heroicon-o-exclamation-triangle" class="lg:col-span-4">
                <div class="flex flex-col flex-1 justify-between gap-3">
                    <p class="text-[13px] text-[#6B7C80]">
                        Laporkan kendala alat di {{ $data['room_name'] }} agar segera ditindaklanjuti teknisi IPSRS.
                    </p>
                    <a
                        href="{{ DamageReportResource::getUrl('create') }}"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-[#0E7C7B] hover:bg-[#0B6463] text-white font-semibold text-[13.5px] h-11 px-5 transition-colors"
                    >
                        <x-filament::icon icon="heroicon-o-plus-circle" class="h-5 w-5" />
                        <span>Lapor kerusakan</span>
                    </a>
                </div>
            </x-sigap.card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <x-sigap.card title="Jatuh Tempo Ruangan Anda" icon="heroicon-o-calendar-days">
                <x-sigap.accent-list
                    :items="$data['upcoming_calibrations']"
                    header-left="Alat"
                    header-right="Sisa"
                    :view-all-url="CalibrationSchedule::getUrl()"
                    empty-text="Tidak ada kalibrasi jatuh tempo di ruangan Anda."
                />
            </x-sigap.card>

            <x-sigap.card title="Laporan Kerusakan Anda" icon="heroicon-o-clipboard-document-list">
                <x-sigap.accent-list
                    :items="$data['my_reports']"
                    header-left="Alat & keluhan"
                    header-right="Status"
                    :view-all-url="DamageReportResource::getUrl('index')"
                    empty-text="Belum ada laporan kerusakan yang Anda buat."
                />
            </x-sigap.card>
        </div>
    </div>
</x-filament-widgets::widget>
