@php
    use App\Models\SatuSehatSyncLog;
    use App\Services\SatuSehatSyncService;
    use App\Services\SettingsService;

    $logs = SatuSehatSyncLog::query()->latest('synced_at')->latest('id')->limit(20)->get();
    $ipsrsId = app(SettingsService::class)->get(SatuSehatSyncService::IPSRS_ORG_SETTING_KEY);
@endphp

<div class="space-y-3 pt-2">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <div class="text-[13.5px] font-semibold text-[#13262B]">Riwayat Sinkronisasi (20 Terakhir)</div>
            @if (filled($ipsrsId))
                <div class="text-[12px] text-[#6B7C80]">
                    ID Sub-organisasi IPSRS: <span class="font-mono font-semibold text-[#0E7C7B]">{{ $ipsrsId }}</span>
                </div>
            @endif
        </div>
    </div>

    @if ($logs->isEmpty())
        <div class="rounded-[10px] border border-[#E3EAEA] bg-[#F4F7F7] px-4 py-6 text-center text-[13px] text-[#6B7C80]">
            Belum ada riwayat sinkronisasi SatuSehat.
        </div>
    @else
        <div class="overflow-x-auto rounded-[10px] border border-[#E3EAEA]">
            <table class="w-full text-left text-[12.5px]">
                <thead class="bg-[#F4F7F7] text-[#6B7C80] border-b border-[#E3EAEA]">
                    <tr>
                        <th class="px-3 py-2 font-medium">Waktu</th>
                        <th class="px-3 py-2 font-medium">Resource</th>
                        <th class="px-3 py-2 font-medium">Aksi</th>
                        <th class="px-3 py-2 font-medium">Status</th>
                        <th class="px-3 py-2 font-medium text-right">HTTP</th>
                        <th class="px-3 py-2 font-medium">Ringkasan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E3EAEA] bg-white">
                    @foreach ($logs as $log)
                        @php
                            [$bg, $fg] = match ($log->status) {
                                'success' => ['#E7F5EE', '#22774F'],
                                'failed' => ['#FBEAEA', '#B83C38'],
                                default => ['#EFF2F2', '#55666A'],
                            };
                        @endphp
                        <tr class="hover:bg-[#F4F7F7]">
                            <td class="px-3 py-2 whitespace-nowrap text-[#6B7C80] tabular-nums">
                                {{ $log->synced_at?->translatedFormat('d M Y H:i') ?? '–' }}
                            </td>
                            <td class="px-3 py-2 font-medium text-[#13262B]">
                                {{ $log->resource }}@if ($log->local_id) #{{ $log->local_id }}@endif
                            </td>
                            <td class="px-3 py-2 text-[#3A4D52]">{{ $log->action }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold" style="background: {{ $bg }}; color: {{ $fg }};">
                                    {{ $log->status }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right font-mono tabular-nums text-[#3A4D52]">
                                {{ $log->http_status ?? '–' }}
                            </td>
                            <td class="px-3 py-2 text-[#3A4D52] max-w-xs truncate" title="{{ $log->response_summary }}">
                                {{ $log->response_summary ?? '–' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
