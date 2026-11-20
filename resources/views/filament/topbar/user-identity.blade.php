@php
    use App\Enums\UserRole;

    $user = auth()->user();
    $roleSlug = $user?->getRoleNames()->first();
    $roleLabel = $roleSlug ? (UserRole::tryFrom($roleSlug)?->getLabel() ?? $roleSlug) : null;
@endphp

@if ($user)
    <div class="hidden md:flex flex-col text-left leading-tight -ml-1">
        <span class="text-[13px] font-semibold text-[#13262B] max-w-[150px] truncate">{{ $user->name }}</span>
        @if ($roleLabel)
            <span class="text-[11.5px] font-normal text-[#6B7C80]">{{ $roleLabel }}</span>
        @endif
    </div>
@endif
