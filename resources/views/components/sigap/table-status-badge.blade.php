@if ($getState() !== null && $getState() !== '')
    <x-sigap.status-badge :status="$getState()" size="sm" />
@else
    <span class="text-xs text-[#8A9699]">{{ $getPlaceholder() ?? '–' }}</span>
@endif
