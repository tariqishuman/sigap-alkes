<x-filament-widgets::widget class="h-full">
    <x-sigap.card title="Jatuh Tempo Terdekat" icon="heroicon-o-calendar-days" class="h-full">
        <x-sigap.accent-list
            :items="$items"
            header-left="Alat"
            header-right="Sisa"
            :view-all-url="$viewAllUrl"
            view-all-label="Lihat semua"
            empty-text="Tidak ada kalibrasi jatuh tempo dalam waktu dekat."
        />
    </x-sigap.card>
</x-filament-widgets::widget>
