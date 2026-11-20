@php
    use App\Support\QuickCreateMenu;

    $user = auth()->user();
    $actions = QuickCreateMenu::items($user);
    $primary = $actions[0] ?? null;
@endphp

@if ($primary)
    <div x-data="{ open: false }" class="relative inline-flex items-center">
        <div class="inline-flex items-center rounded-full bg-[#0E7C7B] text-white shadow-[0_1px_2px_rgba(19,38,43,0.08)] overflow-hidden">
            <a
                href="{{ $primary['url'] }}"
                class="inline-flex items-center gap-1.5 pl-3.5 pr-3 h-9 text-[13px] font-semibold hover:bg-[#0B6463] transition-colors whitespace-nowrap"
            >
                <span aria-hidden="true">+</span>
                <span>Tambah</span>
            </a>
            @if (count($actions) > 1)
                <button
                    type="button"
                    @click="open = !open"
                    @click.outside="open = false"
                    @keydown.escape.window="open = false"
                    aria-label="Menu tambah cepat"
                    :aria-expanded="open"
                    class="inline-flex items-center justify-center h-9 w-8 border-l border-white/25 hover:bg-[#0B6463] transition-colors cursor-pointer"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                    </svg>
                </button>
            @endif
        </div>

        @if (count($actions) > 1)
            <div
                x-show="open"
                x-cloak
                x-transition
                class="absolute right-0 top-full mt-2 w-52 rounded-[14px] bg-white border border-[#E3EAEA] shadow-[0_12px_32px_-12px_rgba(19,38,43,0.18)] p-1.5 z-50"
            >
                @foreach ($actions as $action)
                    <a
                        href="{{ $action['url'] }}"
                        class="flex items-center gap-2.5 rounded-[8px] px-3 py-2 text-[13px] font-medium text-[#13262B] hover:bg-[#E8F4F3] hover:text-[#0E7C7B] transition-colors"
                    >
                        <x-filament::icon :icon="$action['icon']" class="h-4 w-4 shrink-0 text-[#6B7C80]" />
                        <span>{{ $action['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endif
