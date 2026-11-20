<x-filament-widgets::widget class="h-full">
    <x-sigap.card title="Nilai Buku per Ruangan" icon="heroicon-o-banknotes" class="h-full">
        <p class="text-[12.5px] leading-[17px] text-[#6B7C80] mb-3.5">
            Total <strong class="font-bold text-[#13262B] tabular-nums">{{ $data['total_formatted'] }}</strong> · estimasi, bukan pembukuan resmi
        </p>

        @if (empty($data['rooms']))
            <p class="text-[13px] text-[#6B7C80] py-8 text-center">Belum ada data nilai aset.</p>
        @else
            <div class="flex flex-col gap-3 flex-1 justify-center">
                @foreach ($data['rooms'] as $room)
                    <div class="grid grid-cols-[112px_1fr_72px] items-center gap-2.5 text-[12.5px]">
                        <span class="text-[#3A4D52] truncate" title="{{ $room['name'] }}">{{ $room['name'] }}</span>
                        <div class="h-2.5 w-full rounded-full bg-[#F4F7F7] overflow-hidden">
                            <div
                                class="h-full rounded-full bg-[#0E7C7B]"
                                style="width: {{ max(4, $room['percent']) }}%;"
                            ></div>
                        </div>
                        <span class="font-bold text-[#13262B] text-right tabular-nums whitespace-nowrap">{{ $room['formatted'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-sigap.card>
</x-filament-widgets::widget>
