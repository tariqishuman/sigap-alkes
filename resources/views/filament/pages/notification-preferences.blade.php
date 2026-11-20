<x-filament-panels::page>
    @php
        $vapidPublicKey = (string) config('webpush.vapid.public_key', '');
        $devices = $this->getDevices();
        $deviceCount = $devices->count();
        $isCriticalLocked = $this->isCriticalLockedOn();
        $isMagang = $this->isMagangOnly();
    @endphp

    <div
        class="grid grid-cols-1 lg:grid-cols-2 gap-4"
        x-data="{
            supported: ('Notification' in window) && ('serviceWorker' in navigator) && ('PushManager' in window),
            permission: ('Notification' in window) ? Notification.permission : 'unsupported',
            subscribed: {{ $deviceCount > 0 ? 'true' : 'false' }},
            busy: false,
            statusMessage: '',
            errorMessage: '',
            vapidPublicKey: @js($vapidPublicKey),
            csrf: @js(csrf_token()),

            urlBase64ToUint8Array(base64String) {
                const padding = '='.repeat((4 - base64String.length % 4) % 4);
                const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                const rawData = window.atob(base64);
                const outputArray = new Uint8Array(rawData.length);
                for (let i = 0; i < rawData.length; ++i) {
                    outputArray[i] = rawData.charCodeAt(i);
                }
                return outputArray;
            },

            async init() {
                if (!this.supported) return;
                try {
                    const reg = await navigator.serviceWorker.getRegistration('/sw.js');
                    if (reg) {
                        const sub = await reg.pushManager.getSubscription();
                        if (sub) this.subscribed = true;
                    }
                } catch (e) {}
            },

            async enablePush() {
                this.errorMessage = '';
                this.statusMessage = '';

                if (!this.supported) {
                    this.errorMessage = 'Browser ini belum mendukung Web Push (pastikan menggunakan HTTPS atau browser modern seperti Chrome/Edge/Safari iOS 16.4+ via Layar Utama).';
                    return;
                }
                if (!this.vapidPublicKey) {
                    this.errorMessage = 'Kunci VAPID server belum dikonfigurasi. Jalankan php artisan webpush:vapid.';
                    return;
                }

                this.busy = true;
                try {
                    const perm = await Notification.requestPermission();
                    this.permission = perm;
                    if (perm !== 'granted') {
                        this.errorMessage = 'Izin notifikasi ditolak oleh browser. Aktifkan izin notifikasi di pengaturan situs browser Anda.';
                        return;
                    }

                    const reg = await navigator.serviceWorker.register('/sw.js');
                    await navigator.serviceWorker.ready;

                    let sub = await reg.pushManager.getSubscription();
                    if (!sub) {
                        sub = await reg.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey),
                        });
                    }

                    const json = sub.toJSON();
                    const res = await fetch(@js(route('push-subscriptions.store')), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            endpoint: json.endpoint,
                            keys: json.keys,
                            contentEncoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0],
                        }),
                    });

                    if (!res.ok) {
                        const errBody = await res.text();
                        throw new Error('Server menolak pendaftaran perangkat (HTTP ' + res.status + '): ' + errBody.slice(0, 120));
                    }

                    this.subscribed = true;
                    await this.$wire.$refresh();
                    this.statusMessage = 'Notifikasi push berhasil diaktifkan dan perangkat ini telah terdaftar.';
                } catch (e) {
                    this.errorMessage = 'Gagal mengaktifkan push: ' + (e?.message || e);
                } finally {
                    this.busy = false;
                }
            },

            async disablePush() {
                this.busy = true;
                this.errorMessage = '';
                this.statusMessage = '';
                try {
                    const reg = await navigator.serviceWorker.getRegistration('/sw.js');
                    const sub = reg ? await reg.pushManager.getSubscription() : null;
                    if (sub) {
                        const endpoint = sub.endpoint;
                        await sub.unsubscribe();
                        const res = await fetch(@js(route('push-subscriptions.destroy')), {
                            method: 'DELETE',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ endpoint }),
                        });
                        if (!res.ok) {
                            throw new Error('HTTP ' + res.status);
                        }
                    }
                    this.subscribed = false;
                    await this.$wire.$refresh();
                    this.statusMessage = 'Notifikasi push dinonaktifkan di perangkat ini.';
                } catch (e) {
                    this.errorMessage = 'Gagal menonaktifkan push: ' + (e?.message || e);
                } finally {
                    this.busy = false;
                }
            },

            async sendTestPush() {
                this.busy = true;
                this.errorMessage = '';
                this.statusMessage = '';
                try {
                    await this.$wire.sendTestPush();
                    if (this.supported && Notification.permission === 'granted') {
                        const reg = await navigator.serviceWorker.register('/sw.js');
                        await navigator.serviceWorker.ready;
                        await reg.showNotification('Notifikasi uji SIGAP-Alkes', {
                            body: 'Notifikasi push pada perangkat ini aktif dan siap menerima peringatan alat kesehatan.',
                            icon: '/images/brand/logo-mark.svg',
                            tag: 'sigap-test-push',
                            data: { url: '/admin' },
                        });
                    }
                    this.statusMessage = 'Notifikasi uji dikirim ke perangkat terdaftar dan lonceng notifikasi.';
                } catch (e) {
                    this.errorMessage = 'Gagal mengirim notifikasi uji: ' + (e?.message || e);
                } finally {
                    this.busy = false;
                }
            }
        }"
    >
        {{-- Card 1: Perangkat ini --}}
        <x-sigap.card title="Notifikasi di Perangkat Ini" icon="heroicon-o-device-phone-mobile">
            <div class="space-y-4 text-sm text-[#3A4D52]">
                <p>
                    Aktifkan notifikasi push agar peringatan alat kesehatan kritis dan laporan kerusakan langsung muncul di perangkat ini tanpa perlu membuka tab SIGAP-Alkes.
                    Izin browser hanya diminta saat Anda menekan tombol di bawah.
                </p>

                <template x-if="!supported">
                    <div class="rounded-[10px] border border-[#F3D9A4] bg-[#FCF3E2] px-3.5 py-2.5 text-xs text-[#A66F12]">
                        Browser atau konteks saat ini belum mendukung Web Push. Gunakan Chrome, Edge, Firefox, atau Safari iOS 16.4+ (setelah ditambahkan ke Layar Utama) melalui HTTPS.
                    </div>
                </template>

                <div class="rounded-[10px] border border-[#E3EAEA] bg-[#F4F7F7] px-3.5 py-3 text-xs space-y-1">
                    <div>Status izin browser: <strong class="text-[#13262B]" x-text="permission"></strong></div>
                    <div>Perangkat terdaftar di akun Anda: <strong class="text-[#13262B]">{{ $deviceCount }} perangkat</strong></div>
                </div>

                @if ($devices->isNotEmpty())
                    <div class="space-y-1.5">
                        <div class="text-xs font-semibold text-[#6B7C80]">Perangkat Terdaftar</div>
                        @foreach ($devices as $device)
                            <div class="flex items-center justify-between gap-2 rounded-[10px] border border-[#E3EAEA] bg-white px-3 py-2 text-xs">
                                <span class="truncate text-[#13262B] font-medium">{{ \Illuminate\Support\Str::limit($device->user_agent ?: 'Browser Web Push', 52) }}</span>
                                <span class="shrink-0 text-[#6B7C80] tabular-nums">{{ $device->updated_at?->translatedFormat('d M Y H:i') }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($isMagang)
                    <div class="rounded-[10px] border border-[#F3D9A4] bg-[#FCF3E2] p-3 text-xs text-[#A66F12]">
                        Peran Magang tidak menerima notifikasi push operasional. Anda tetap dapat melihat pembaruan di lonceng notifikasi.
                    </div>
                @else
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            x-show="!subscribed"
                            x-bind:disabled="busy"
                            @click="enablePush()"
                            class="inline-flex items-center rounded-full bg-[#0E7C7B] px-4 py-2 text-xs font-semibold text-white hover:bg-[#0B6463] disabled:opacity-50 cursor-pointer"
                        >
                            Aktifkan notifikasi di perangkat ini
                        </button>

                        <button
                            type="button"
                            x-show="subscribed"
                            x-bind:disabled="busy"
                            @click="disablePush()"
                            class="inline-flex items-center rounded-full border border-[#D3DEDD] bg-white px-4 py-2 text-xs font-semibold text-[#13262B] hover:bg-[#F4F7F7] disabled:opacity-50 cursor-pointer"
                        >
                            Nonaktifkan di perangkat ini
                        </button>

                        <button
                            type="button"
                            @click="sendTestPush()"
                            x-bind:disabled="busy"
                            class="inline-flex items-center rounded-full border border-[#0E7C7B] bg-[#E8F4F3] px-4 py-2 text-xs font-semibold text-[#0E7C7B] hover:bg-[#D5ECEA] disabled:opacity-50 cursor-pointer"
                        >
                            Kirim notifikasi uji
                        </button>
                    </div>
                @endif

                <template x-if="statusMessage">
                    <div class="rounded-[10px] border border-[#D5ECEA] bg-[#E8F4F3] px-3.5 py-2.5 text-xs font-medium text-[#0E7C7B]" x-text="statusMessage"></div>
                </template>

                <template x-if="errorMessage">
                    <div class="rounded-[10px] border border-[#F3C4C2] bg-[#FBEAEA] px-3.5 py-2.5 text-xs font-medium text-[#9A2F2C]" x-text="errorMessage"></div>
                </template>
            </div>
        </x-sigap.card>

        {{-- Card 2: Preferensi Tingkat Alert --}}
        <x-sigap.card title="Preferensi Tingkat Notifikasi Push" icon="heroicon-o-adjustments-horizontal">
            <form wire:submit="savePreferences" class="space-y-3 text-sm text-[#3A4D52]">
                <div
                    class="rounded-[10px] p-3.5"
                    style="background-color: #FBEAEA; border: 1px solid #F3C4C2;"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-0.5">
                            <div class="font-semibold text-[#9A2F2C]">Kritis (Alat Kritis Tidak Aman / Terlambat / Eskalasi)</div>
                            <div class="text-xs text-[#6B7C80]">
                                @if ($isCriticalLocked)
                                    Selalu aktif untuk Teknisi &amp; Admin IPSRS agar keselamatan pasien tetap terjaga.
                                @else
                                    Aktif sesuai peran Anda (Manajemen hanya menerima eskalasi kritis).
                                @endif
                            </div>
                        </div>
                        <span class="shrink-0 rounded-full bg-white px-2.5 py-0.5 text-xs font-semibold text-[#B83C38]" style="border: 1px solid #F3C4C2;">Selalu aktif</span>
                    </div>
                </div>

                <label class="flex items-start gap-3 rounded-[10px] border border-[#E3EAEA] bg-white p-3.5 cursor-pointer">
                    <input type="checkbox" wire:model="pushPrefImportant" class="mt-0.5 rounded border-[#D3DEDD] text-[#0E7C7B] focus:ring-[#0E7C7B]">
                    <div>
                        <div class="font-semibold text-[#13262B]">Penting lewat Push</div>
                        <div class="text-xs text-[#6B7C80]">Laporan kerusakan lain, verifikasi magang, kalibrasi H-7 &amp; H-0, pemeliharaan preventif H-0.</div>
                    </div>
                </label>

                <label class="flex items-start gap-3 rounded-[10px] border border-[#E3EAEA] bg-white p-3.5 cursor-pointer">
                    <input type="checkbox" wire:model="pushPrefInfo" class="mt-0.5 rounded border-[#D3DEDD] text-[#0E7C7B] focus:ring-[#0E7C7B]">
                    <div>
                        <div class="font-semibold text-[#13262B]">Info lewat Push</div>
                        <div class="text-xs text-[#6B7C80]">Kalibrasi H-30, pemeliharaan H-7, stok menipis, perbaikan selesai (default: lonceng &amp; ringkasan harian).</div>
                    </div>
                </label>

                <div class="pt-1">
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-full bg-[#0E7C7B] px-4 py-2 text-xs font-semibold text-white hover:bg-[#0B6463] cursor-pointer"
                    >
                        Simpan preferensi
                    </button>
                </div>
            </form>
        </x-sigap.card>
    </div>
</x-filament-panels::page>
