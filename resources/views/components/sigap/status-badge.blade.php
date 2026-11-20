@props([
    'status',
    'size' => 'sm', // 'sm' | 'lg'
])

@php
    use App\Enums\EquipmentStatus;

    $enum = $status instanceof EquipmentStatus
        ? $status
        : EquipmentStatus::tryFrom((string) $status);

    $palette = match ($enum) {
        EquipmentStatus::FitForUse => ['dot' => '#2E9E6B', 'bg' => '#E7F5EE', 'text' => '#22774F'],
        EquipmentStatus::CalibrationDue => ['dot' => '#E0A23B', 'bg' => '#FCF3E2', 'text' => '#A66F12'],
        EquipmentStatus::Overdue => ['dot' => '#D9534F', 'bg' => '#FBEAEA', 'text' => '#B83C38'],
        EquipmentStatus::UnderTesting => ['dot' => '#3B82F6', 'bg' => '#EAF1FE', 'text' => '#2563EB'],
        EquipmentStatus::UnderRepair => ['dot' => '#8A9699', 'bg' => '#EFF2F2', 'text' => '#55666A'],
        EquipmentStatus::Unfit => ['dot' => '#B83C38', 'bg' => '#FBEAEA', 'text' => '#9A2F2C'],
        default => ['dot' => '#8A9699', 'bg' => '#EFF2F2', 'text' => '#55666A'],
    };

    $label = $enum?->getLabel() ?? (string) $status;

    $sizeClasses = $size === 'lg'
        ? 'px-3 py-1.5 text-[13px] leading-[18px] gap-2'
        : 'px-2.5 py-1 text-[11.5px] leading-[14px] gap-1.5';

    $dotSize = $size === 'lg' ? 'h-2 w-2' : 'h-1.5 w-1.5';
@endphp

<span
    {{ $attributes->merge(['class' => "inline-flex items-center rounded-full font-semibold whitespace-nowrap {$sizeClasses}"]) }}
    style="background-color: {{ $palette['bg'] }}; color: {{ $palette['text'] }};"
>
    <span
        class="rounded-full shrink-0 {{ $dotSize }}"
        style="background-color: {{ $palette['dot'] }};"
        aria-hidden="true"
    ></span>
    <span>{{ $label }}</span>
</span>
