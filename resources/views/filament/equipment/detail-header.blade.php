@php
    use App\Services\DepreciationCalculator;
    use App\Services\QrLabelService;
    use App\Services\UptimeCalculator;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Storage;

    /** @var \App\Models\Equipment $record */
    $record = $getRecord();
    $canViewCosts = Auth::user()?->canViewCosts() ?? false;

    $photoUrl = $record->photo_path
        ? Storage::disk('public')->url($record->photo_path)
        : url('/images/equipment-placeholder.svg');

    $qrDataUri = app(QrLabelService::class)->generateQrDataUri($record);
    $publicUrl = route('public.equipment.show', ['qr_token' => $record->qr_token]);

    $validUntil = $record->latestValidCalibration?->valid_until;
    $daysLeft = $validUntil ? (int) today()->diffInDays($validUntil->copy()->startOfDay(), false) : null;
    $daysLeftLabel = match (true) {
        $daysLeft === null => 'Belum ada sertifikat',
        $daysLeft < 0 => 'Lewat '.abs($daysLeft).' hari',
        $daysLeft === 0 => 'Jatuh tempo hari ini',
        default => "Dalam {$daysLeft} hari",
    };
    $daysLeftClass = match (true) {
        $daysLeft === null => 'text-[#6B7C80]',
        $daysLeft <= 0 => 'text-[#B83C38]',
        $daysLeft <= 30 => 'text-[#A66F12]',
        default => 'text-[#22774F]',
    };

    $uptime30 = app(UptimeCalculator::class)->forEquipment($record, now()->subDays(30), now());
    $uptimeLabel = $uptime30['uptime_percent'] !== null
        ? number_format($uptime30['uptime_percent'], 1, ',', '.').'%'
        : '–';

    $dep = $canViewCosts ? app(DepreciationCalculator::class)->calculateForEquipment($record) : null;
    $repairCost = $canViewCosts ? (float) $record->totalRepairCost() : null;
@endphp

<div class="space-y-4 w-full">
    {{-- 1. Kartu Identitas (§4.3) --}}
    <x-sigap.card>
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-5">
            <div class="flex items-start gap-4 min-w-0">
                <img
                    src="{{ $photoUrl }}"
                    alt="{{ $record->name }}"
                    class="h-24 w-24 rounded-[12px] object-cover border border-[#E3EAEA] bg-[#F4F7F7] shrink-0"
                >

                <div class="min-w-0 space-y-1.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-[6px] bg-[#E8F4F3] border border-[#D5ECEA] px-2 py-0.5 font-mono text-[12px] font-bold text-[#0E7C7B]">
                            {{ $record->inventory_code }}
                        </span>
                        <x-sigap.status-badge :status="$record->status" size="lg" />
                        @if (! $record->is_active)
                            <span class="inline-flex items-center rounded-full bg-[#EFF2F2] px-2.5 py-0.5 text-[11.5px] font-semibold text-[#55666A]">
                                Nonaktif
                            </span>
                        @endif
                    </div>

                    <h2 class="text-[20px] leading-[26px] font-bold text-[#13262B] tracking-[-0.015em]">
                        {{ $record->name }}
                    </h2>

                    <p class="text-[13px] text-[#6B7C80]">
                        {{ $record->room?->name ?? '–' }} · {{ $record->equipmentType?->name ?? '–' }}
                        @if ($record->brand || $record->model)
                            · {{ trim(($record->brand ?? '').' '.($record->model ?? '')) }}
                        @endif
                        @if ($record->serial_number)
                            · SN {{ $record->serial_number }}
                        @endif
                    </p>

                    @if (filled($record->notes))
                        <p class="text-[12.5px] text-[#3A4D52] pt-0.5">{{ $record->notes }}</p>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-3 md:border-l md:border-[#E3EAEA] md:pl-5 shrink-0">
                <img
                    src="{{ $qrDataUri }}"
                    alt="QR {{ $record->inventory_code }}"
                    class="h-16 w-16 rounded-[8px] border border-[#E3EAEA] p-1 bg-white"
                >
                <div class="space-y-1">
                    <div class="text-[12px] font-semibold text-[#13262B]">QR Status Publik</div>
                    <a
                        href="{{ $publicUrl }}"
                        target="_blank"
                        class="inline-flex items-center gap-1 text-[12.5px] font-semibold text-[#0E7C7B] hover:text-[#0B6463] underline"
                    >
                        Buka halaman status publik
                    </a>
                </div>
            </div>
        </div>
    </x-sigap.card>

    {{-- 2. Strip KPI (§4.3 — 4 kartu untuk role berizin biaya, 2 kartu untuk lainnya) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 {{ $canViewCosts ? 'xl:grid-cols-4' : '' }} gap-4">
        <x-sigap.card>
            <div class="text-[12px] font-medium text-[#6B7C80]">Sertifikat berlaku s.d.</div>
            <div class="mt-1 text-[20px] font-bold text-[#13262B] tabular-nums">
                {{ $validUntil ? $validUntil->translatedFormat('d F Y') : '–' }}
            </div>
            <div class="mt-1 text-[12px] font-semibold {{ $daysLeftClass }}">{{ $daysLeftLabel }}</div>
        </x-sigap.card>

        <x-sigap.card>
            <div class="text-[12px] font-medium text-[#6B7C80]">Uptime 30 hari</div>
            <div class="mt-1 text-[20px] font-bold text-[#13262B] tabular-nums">{{ $uptimeLabel }}</div>
            <div class="mt-1 text-[12px] text-[#6B7C80]">
                Downtime {{ number_format($uptime30['downtime_hours'], 1, ',', '.') }} jam
            </div>
        </x-sigap.card>

        @if ($canViewCosts && $dep)
            <x-sigap.card>
                <div class="text-[12px] font-medium text-[#6B7C80]">Nilai buku saat ini</div>
                <div class="mt-1 text-[20px] font-bold text-[#13262B] tabular-nums">
                    Rp{{ number_format((float) $dep['book_value'], 0, ',', '.') }}
                </div>
                <div class="mt-1 text-[12px] text-[#6B7C80]">
                    Penyusutan Rp{{ number_format((float) $dep['monthly_depreciation'], 0, ',', '.') }}/bln
                </div>
            </x-sigap.card>

            <x-sigap.card>
                <div class="text-[12px] font-medium text-[#6B7C80]">Total biaya perbaikan</div>
                <div class="mt-1 text-[20px] font-bold text-[#13262B] tabular-nums">
                    Rp{{ number_format((float) $repairCost, 0, ',', '.') }}
                </div>
                <div class="mt-1 text-[12px] text-[#6B7C80]">Akumulasi seluruh perbaikan</div>
            </x-sigap.card>
        @endif
    </div>
</div>
