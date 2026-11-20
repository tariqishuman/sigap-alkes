@php
    use App\Filament\Pages\CalibrationSchedule;
    use App\Filament\Resources\DamageReports\DamageReportResource;
    use App\Filament\Resources\Repairs\RepairResource;
    use App\Filament\Resources\SpareParts\SparePartResource;
@endphp

<x-filament-widgets::widget>
    <div class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <x-sigap.card title="Kalibrasi 14 Hari" icon="heroicon-o-calendar-days">
                <x-sigap.kpi caption="Alat jatuh tempo dalam 14 hari" :value="number_format($data['due_14_count'], 0, ',', '.')" unit="alat" />
            </x-sigap.card>
            <x-sigap.card title="Perbaikan Aktif Anda" icon="heroicon-o-wrench-screwdriver">
                <x-sigap.kpi caption="Sedang Anda tangani" :value="number_format($data['active_repairs_count'], 0, ',', '.')" unit="tugas" />
            </x-sigap.card>
            <x-sigap.card title="Laporan Kerusakan Terbuka" icon="heroicon-o-exclamation-triangle">
                <x-sigap.kpi caption="Menunggu atau dalam penanganan" :value="number_format($data['open_reports_count'], 0, ',', '.')" unit="laporan" />
            </x-sigap.card>
            <x-sigap.card title="Stok Suku Cadang Menipis" icon="heroicon-o-archive-box">
                <x-sigap.kpi caption="Perlu permintaan pengadaan" :value="number_format($data['low_stock_count'], 0, ',', '.')" unit="item" />
            </x-sigap.card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <x-sigap.card title="Kalibrasi Jatuh Tempo 14 Hari" icon="heroicon-o-calendar-days">
                <x-sigap.accent-list
                    :items="$data['due_14_items']"
                    header-left="Alat"
                    header-right="Sisa"
                    :view-all-url="CalibrationSchedule::getUrl()"
                    empty-text="Tidak ada kalibrasi jatuh tempo dalam 14 hari."
                />
            </x-sigap.card>

            <x-sigap.card title="Perbaikan Aktif Anda" icon="heroicon-o-wrench-screwdriver">
                <x-sigap.accent-list
                    :items="$data['active_repair_items']"
                    header-left="Alat"
                    header-right="Status"
                    :view-all-url="RepairResource::getUrl('index')"
                    empty-text="Tidak ada perbaikan aktif yang ditugaskan kepada Anda."
                />
            </x-sigap.card>

            <x-sigap.card title="Laporan Kerusakan Terbuka" icon="heroicon-o-exclamation-triangle">
                <x-sigap.accent-list
                    :items="$data['open_report_items']"
                    header-left="Alat & keluhan"
                    header-right="Status"
                    :view-all-url="DamageReportResource::getUrl('index')"
                    empty-text="Tidak ada kerusakan. Semua alat aman."
                />
            </x-sigap.card>

            <x-sigap.card title="Stok Suku Cadang Menipis" icon="heroicon-o-archive-box">
                <x-sigap.accent-list
                    :items="$data['low_stock_items']"
                    header-left="Suku cadang"
                    header-right="Sisa stok"
                    :view-all-url="SparePartResource::getUrl('index')"
                    empty-text="Semua stok suku cadang masih aman."
                />
            </x-sigap.card>
        </div>
    </div>
</x-filament-widgets::widget>
