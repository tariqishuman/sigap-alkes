<x-filament-panels::page>
    @php
        $summary = $this->getSummary();
        $uptime = $summary['uptime'];
        $compliance = $summary['compliance'];
    @endphp

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <label for="report-month" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">
                Periode Bulan
            </label>
            <select
                id="report-month"
                wire:model.live="selectedMonth"
                class="mt-1.5 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900"
            >
                @foreach ($this->getMonthOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <label for="report-room" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">
                Ruangan
            </label>
            <select
                id="report-room"
                wire:model.live="selectedRoomId"
                class="mt-1.5 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900"
            >
                <option value="">Semua Ruangan</option>
                @foreach ($this->getRoomOptions() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs font-medium text-gray-500">Uptime periode ({{ $summary['period_label'] }})</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">
                {{ $uptime['uptime_percent'] !== null ? number_format($uptime['uptime_percent'], 2, ',', '.').'%' : '–' }}
            </div>
            <div class="mt-1 text-xs text-gray-500">Rata-rata seluruh alat aktif</div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs font-medium text-gray-500">Total jam operasi</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">
                {{ number_format($uptime['total_hours'], 1, ',', '.') }} jam
            </div>
            <div class="mt-1 text-xs text-gray-500">{{ $uptime['equipment_count'] }} alat aktif dihitung</div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs font-medium text-gray-500">Total downtime</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">
                {{ number_format($uptime['downtime_hours'], 1, ',', '.') }} jam
            </div>
            <div class="mt-1 text-xs text-gray-500">Selama dalam perbaikan &amp; tidak laik pakai</div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs font-medium text-gray-500">Kepatuhan kalibrasi hari ini</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">
                {{ $compliance['formatted'] }}
            </div>
            <div class="mt-1 text-xs text-gray-500">
                {{ $compliance['compliant_count'] }} / {{ $compliance['required_count'] }} alat wajib kalibrasi ({{ $summary['room_label'] }})
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-xs text-gray-600">
        Catatan: Alat yang dimutasi di tengah bulan dihitung ke ruangan saat ini. Format kolom ekspor ASPAK bersifat sementara.
    </div>

    {{ $this->table }}
</x-filament-panels::page>
