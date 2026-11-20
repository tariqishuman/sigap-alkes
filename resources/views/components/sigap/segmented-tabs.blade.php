@props([
    'tabs' => [], // ['key' => 'Label', ...]
    'active' => null,
    'wireAction' => null, // e.g. 'setMode'
])

<div {{ $attributes->merge([
    'class' => 'inline-flex items-center rounded-full bg-[#F4F7F7] p-[3px] border border-[#E3EAEA]',
    'role' => 'tablist',
]) }}>
    @foreach ($tabs as $key => $label)
        @php $isActive = (string) $active === (string) $key; @endphp
        <button
            type="button"
            role="tab"
            aria-selected="{{ $isActive ? 'true' : 'false' }}"
            @if ($wireAction) wire:click="{{ $wireAction }}('{{ $key }}')" @endif
            class="rounded-full px-3 py-1 text-[12px] leading-[16px] font-semibold transition-all {{ $isActive ? 'bg-white text-[#13262B] shadow-[0_1px_2px_rgba(19,38,43,0.08)]' : 'text-[#6B7C80] hover:text-[#13262B]' }}"
        >
            {{ $label }}
        </button>
    @endforeach
</div>
