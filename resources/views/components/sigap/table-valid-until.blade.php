@php
    /** @var \App\Models\Equipment $record */
    $record = $getRecord();
    $validUntil = $record->latestValidCalibration?->valid_until;
    $daysLeft = $validUntil ? (int) today()->diffInDays($validUntil->copy()->startOfDay(), false) : null;

    $subText = match (true) {
        $daysLeft === null => null,
        $daysLeft < 0 => 'lewat '.abs($daysLeft).' hari',
        $daysLeft === 0 => 'hari ini',
        default => "dalam {$daysLeft} hari",
    };

    $subColor = match (true) {
        $daysLeft === null => '#6B7C80',
        $daysLeft <= 0 => '#B83C38',
        $daysLeft <= 30 => '#A66F12',
        default => '#6B7C80',
    };
@endphp

<div class="leading-tight tabular-nums">
    <div class="text-[13px] font-medium text-[#13262B]">
        {{ $validUntil ? $validUntil->translatedFormat('d M Y') : '–' }}
    </div>
    @if ($subText)
        <div class="text-[11.5px] font-medium mt-0.5" style="color: {{ $subColor }};">
            {{ $subText }}
        </div>
    @endif
</div>
