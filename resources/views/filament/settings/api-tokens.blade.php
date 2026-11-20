@php
    use Laravel\Sanctum\PersonalAccessToken;

    $tokens = PersonalAccessToken::query()
        ->with('tokenable')
        ->latest('id')
        ->get();
@endphp

<div class="space-y-4">
    <div class="rounded-[10px] border border-[#E3EAEA] bg-[#F4F7F7] p-4 space-y-3">
        <div class="text-[13px] font-semibold text-[#13262B]">Buat Token API Baru (HL7 FHIR R4 Read-Only)</div>
        <div class="flex flex-col sm:flex-row gap-2.5">
            <input
                type="text"
                wire:model="newTokenName"
                placeholder="Nama klien, mis. SIMRS Integrasi"
                maxlength="100"
                class="flex-1 h-10 rounded-[8px] border border-[#E3EAEA] bg-white px-3 text-[13.5px] text-[#13262B] focus:border-[#0E7C7B] focus:outline-none"
            />
            <button
                type="button"
                wire:click="createApiToken"
                class="inline-flex items-center justify-center h-10 px-4 rounded-full bg-[#0E7C7B] hover:bg-[#0B6463] text-white text-[13px] font-semibold transition-colors cursor-pointer whitespace-nowrap"
            >
                Buat token
            </button>
        </div>
        <p class="text-[12px] text-[#6B7C80]">
            Token digunakan sebagai header <code>Authorization: Bearer &lt;token&gt;</code> pada endpoint <code>/api/fhir/r4/*</code> (maks 60 permintaan/menit).
        </p>
    </div>

    @if (filled($this->plainTextTokenOnce))
        <div class="rounded-[10px] border border-[#E0A23B]/50 bg-[#FCF3E2] p-4 space-y-2">
            <div class="text-[13px] font-bold text-[#A66F12]">
                Salin token ini sekarang — token tidak akan ditampilkan lagi!
            </div>
            <div class="rounded-[8px] bg-white border border-[#E3EAEA] px-3 py-2 font-mono text-[12.5px] text-[#13262B] break-all select-all">
                {{ $this->plainTextTokenOnce }}
            </div>
        </div>
    @endif

    @if ($tokens->isEmpty())
        <div class="rounded-[10px] border border-[#E3EAEA] bg-white px-4 py-6 text-center text-[13px] text-[#6B7C80]">
            Belum ada Token API yang aktif.
        </div>
    @else
        <div class="overflow-x-auto rounded-[10px] border border-[#E3EAEA]">
            <table class="w-full text-left text-[12.5px]">
                <thead class="bg-[#F4F7F7] text-[#6B7C80] border-b border-[#E3EAEA]">
                    <tr>
                        <th class="px-3 py-2 font-medium">Nama token</th>
                        <th class="px-3 py-2 font-medium">Dibuat oleh</th>
                        <th class="px-3 py-2 font-medium">Terakhir dipakai</th>
                        <th class="px-3 py-2 font-medium">Dibuat</th>
                        <th class="px-3 py-2 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E3EAEA] bg-white">
                    @foreach ($tokens as $token)
                        <tr class="hover:bg-[#F4F7F7]">
                            <td class="px-3 py-2.5 font-semibold text-[#13262B]">{{ $token->name }}</td>
                            <td class="px-3 py-2.5 text-[#3A4D52]">{{ $token->tokenable?->name ?? '–' }}</td>
                            <td class="px-3 py-2.5 text-[#6B7C80] tabular-nums">
                                {{ $token->last_used_at?->translatedFormat('d M Y H:i') ?? 'Belum pernah' }}
                            </td>
                            <td class="px-3 py-2.5 text-[#6B7C80] tabular-nums">
                                {{ $token->created_at?->translatedFormat('d M Y H:i') ?? '–' }}
                            </td>
                            <td class="px-3 py-2.5 text-right">
                                <button
                                    type="button"
                                    wire:click="revokeApiToken({{ $token->id }})"
                                    wire:confirm="Cabut token ini? Klien yang memakainya tidak akan bisa mengakses API lagi."
                                    class="inline-flex items-center rounded-full bg-[#FBEAEA] hover:bg-[#D9534F] text-[#B83C38] hover:text-white px-3 py-1 text-[11.5px] font-semibold transition-colors cursor-pointer"
                                >
                                    Cabut
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
