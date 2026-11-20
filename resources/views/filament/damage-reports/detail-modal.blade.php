<div class="space-y-4 text-sm text-gray-800 dark:text-gray-200">
    <div class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 sm:grid-cols-2 dark:border-gray-700 dark:bg-gray-900/40">
        <div>
            <div class="text-xs text-gray-500">Alat</div>
            <div class="font-semibold">{{ $record->equipment?->name ?? '-' }}</div>
            <div class="text-xs text-gray-500">{{ $record->equipment?->inventory_code ?? '-' }} • {{ $record->equipment?->room?->name ?? '-' }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-500">Pelapor & Waktu</div>
            <div class="font-medium">{{ $record->reportedByUser?->name ?? '-' }}</div>
            <div class="text-xs text-gray-500">{{ $record->reported_at?->translatedFormat('d M Y H:i') ?? '-' }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-500">Keamanan Pemakaian</div>
            @if ($record->is_safe_to_use)
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Masih aman dipakai</span>
            @else
                <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700">Tidak aman dipakai</span>
            @endif
        </div>
        <div>
            <div class="text-xs text-gray-500">Status Laporan</div>
            <div class="font-medium">{{ $record->status?->getLabel() ?? '-' }}</div>
        </div>
    </div>

    <div>
        <div class="mb-1 text-xs font-medium text-gray-500">Keluhan Awal</div>
        <div class="rounded-lg border border-gray-200 bg-white p-3 whitespace-pre-line dark:border-gray-700 dark:bg-gray-800">{{ $record->complaint }}</div>
    </div>

    @if (! empty($record->photos))
        <div>
            <div class="mb-1.5 text-xs font-medium text-gray-500">Foto Laporan ({{ count($record->photos) }})</div>
            <div class="grid grid-cols-3 gap-2">
                @foreach ($record->photos as $index => $photo)
                    <a href="{{ route('damage-reports.photo', ['damageReport' => $record->id, 'index' => $index]) }}" target="_blank" class="block overflow-hidden rounded-lg border border-gray-200">
                        <img
                            src="{{ route('damage-reports.photo', ['damageReport' => $record->id, 'index' => $index]) }}"
                            alt="Foto kerusakan {{ $index + 1 }}"
                            class="h-24 w-full object-cover"
                        >
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if ($record->verified_at)
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
            <div class="text-xs font-medium text-gray-500">Verifikasi</div>
            <div class="mt-0.5">
                Oleh <strong>{{ $record->verifiedByUser?->name ?? '-' }}</strong> pada {{ $record->verified_at->translatedFormat('d M Y H:i') }}.
            </div>
            @if ($record->rejected_reason)
                <div class="mt-1 text-xs text-red-700">Alasan penolakan: {{ $record->rejected_reason }}</div>
            @endif
        </div>
    @endif

    @if ($record->closed_without_repair_reason)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50/70 p-3">
            <div class="text-xs font-medium text-emerald-800">Ditutup Tanpa Perbaikan</div>
            <div class="mt-0.5 text-emerald-900">{{ $record->closed_without_repair_reason }}</div>
            <div class="mt-1 text-xs text-emerald-700">
                Oleh {{ $record->closedByUser?->name ?? '-' }} pada {{ $record->closed_at?->translatedFormat('d M Y H:i') ?? '-' }}
            </div>
        </div>
    @endif

    @if ($record->notes->isNotEmpty())
        <div>
            <div class="mb-2 text-xs font-medium text-gray-500">Keterangan Tambahan ({{ $record->notes->count() }})</div>
            <div class="space-y-2">
                @foreach ($record->notes as $note)
                    <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
                        <div class="mb-1 flex items-center justify-between gap-2 text-xs text-gray-500">
                            <span><strong>{{ $note->reportedByUser?->name ?? 'Pengguna' }}</strong> • {{ $note->reported_at?->translatedFormat('d M Y H:i') }}</span>
                            @if (! $note->is_safe_to_use)
                                <span class="rounded-full bg-red-50 px-2 py-0.5 font-medium text-red-700">Tidak aman</span>
                            @endif
                        </div>
                        <div class="whitespace-pre-line">{{ $note->content }}</div>
                        @if (! empty($note->photos))
                            <div class="mt-2 grid grid-cols-3 gap-2">
                                @foreach ($note->photos as $index => $photo)
                                    <a href="{{ route('damage-reports.photo', ['damageReport' => $record->id, 'index' => $index, 'note' => $note->id]) }}" target="_blank" class="block overflow-hidden rounded border border-gray-200">
                                        <img
                                            src="{{ route('damage-reports.photo', ['damageReport' => $record->id, 'index' => $index, 'note' => $note->id]) }}"
                                            alt="Foto tambahan {{ $index + 1 }}"
                                            class="h-20 w-full object-cover"
                                        >
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
