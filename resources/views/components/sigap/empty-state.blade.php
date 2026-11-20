{{--
    Empty state bergambar.
    Contoh: <x-sigap.empty-state image="empty-calibration" title="Belum ada kalibrasi" description="Catat hasil kalibrasi pertama untuk alat ini." />
    Gambar yang tersedia di public/images/illustrations:
    empty-equipment, empty-calibration, empty-repair, empty-search,
    empty-notifications, empty-movement, empty-report.
--}}
@props([
    'image' => 'empty-search',
    'title' => 'Belum ada data',
    'description' => null,
])

<div {{ $attributes->merge(['style' => 'display: flex; flex-direction: column; align-items: center; text-align: center; padding: 1.5rem 1rem;']) }}>
    <img
        src="{{ asset('images/illustrations/'.$image.'.svg') }}"
        alt=""
        aria-hidden="true"
        style="width: 100%; max-width: 11rem; height: auto; margin-bottom: .75rem;"
    >
    <p style="font-size: 1rem; font-weight: 600; color: #1B2B30;">{{ $title }}</p>
    @if ($description)
        <p style="font-size: .875rem; color: #6B7C80; margin-top: .25rem; max-width: 22rem; line-height: 1.5;">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div style="margin-top: 1rem;">{{ $slot }}</div>
    @endif
</div>
