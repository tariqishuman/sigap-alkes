<x-filament-panels::page>
    @php
        $vapidPublicKey = (string) config('webpush.vapid.public_key', '');
        $deviceCount = $this->getDeviceCount();
        $isCriticalLocked = $this->isCriticalLockedOn();
        $isMagang = $this->isMagangOnly();
    @endphp

    <div
        class="grid grid-cols-1 lg:grid-cols-2 gap-4"
        x-data="{
            supported: ('Notification' in window) && ('serviceWorker' in navigator) && ('PushManager' in window),
            permission: ('Notification' in window) ? Notification.permission : 'unsupported',
            subscribed: false,
            busy: false,
            statusMessage: '',
            vapidPublicKey: @js($vapidPublicKey),
            csrf: document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',

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
                        this.subscribed = Boolean(sub);
                    }
                } catch (e) {}
            },

            async enablePush() {
                if (!this.supported) {
                    this.statusMessage = 'Browser ini belum mendukung Web Push.';
                    return;
                }
                this.busy = true;
                this.statusMessage = '';
                try {
                    const perm = await Notification.requestPermission();
                    this.permission = perm;
                    if (perm !== 'granted') {
                        this.statusMessage = 'Izin notifikasi ditolak oleh browser. Aktifkan izin notifikasi di pengaturan browser.';
                        return;
                    }
                    const reg = await navigator.serviceWorker.register('/sw.js');
                    await navigator.serviceWorker.ready;

                    if (this.vapidPublicKey) {
                        const sub = await reg.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey),
                        });
                        const json = sub.toJSON();
                        await fetch(@js(route('push-subscriptions.store')), {
                            method: 'POST',
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
                    }
                    this.subscribed = true;
                    this.statusMessage = 'Notifikasi push berhasil diaktifkan di perangkat ini.';
                } catch (e) {
                    this.statusMessage = 'Gagal mengaktifkan push: ' + (e?.message || e);
                } finally {
                    this.busy = false;
                }
            },

            async disablePush() {
                this.busy = true;
                this.statusMessage = '';
                try {
                    const reg = await navigator.serviceWorker.getRegistration('/sw.js');
                    const sub = reg ? await reg.pushManager.getSubscription() : null;
                    if (sub) {
                        const endpoint = sub.endpoint;
                        await sub.unsubscribe();
                        await fetch(@js(route('push-subscriptions.destroy')), {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ endpoint }),
                        });
                    }
                    this.subscribed = false;
                    this.statusMessage = 'Notifikasi push dinonaktifkan di perangkat ini.';
                } catch (e) {
                    this.statusMessage = 'Gagal menonaktifkan push: ' + (e?.message || e);
                } finally {
                    this.busy = false;
                }
            },

            async sendTestPush() {
                this.busy = true;
                this.statusMessage = '';
                try {
                    const res = await fetch(@js(route('push-subscriptions.test')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'Accept': 'application/json',
                        },
                    });
                    const data = await res.json();

                    if (this.supported && Notification.permission === 'granted') {
                        const reg = await navigator.serviceWorker.register('/sw.js');
                        await navigator.serviceWorker.ready;
                        await reg.showNotification(data.title || 'Notifikasi uji SIGAP-Alkes', {
                            body: data.body || 'Notifikasi push pada perangkat ini aktif.',
                            icon: '/images/brand/logo-mark.png',
                            tag: 'sigap-test-push',
                            data: { url: data.url || '/admin' },
                        });
                    }
                    this.statusMessage = 'Notifikasi uji berhasil dikirim ke perangkat ini dan lonceng notifikasi.';
                } catch (e) {
                    this.statusMessage = 'Gagal mengirim notifikasi uji: ' + (e?.message || e);
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

                <div class="rounded-lg border border-[#E3EAEA] bg-[#F4F7F7] p-3 text-xs space-y-1">
                    <div>Status izin browser: <strong class="text-[#13262B]" x-text="permission"></strong></div>
                    <div>Perangkat terdaftar di akun Anda: <strong class="text-[#13262B]">{{ $deviceCount }} perangkat</strong></div>
                </div>

                @if ($isMagang)
                    <div class="rounded-lg border border-[#F3D9A4] bg-[#FCF3E2] p-3 text-xs text-[#A66F12]">
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
                            x-bind:disabled="busy"
                            @click="sendTestPush()"
                            class="inline-flex items-center rounded-full border border-[#0E7C7B] bg-[#E8F4F3] px-4 py-2 text-xs font-semibold text-[#0E7C7B] hover:bg-[#D5ECEA] disabled:opacity-50 cursor-pointer"
                        >
                            Kirim notifikasi uji
                        </button>
                    </div>
                @endif

                <template x-if="statusMessage">
                    <div class="rounded-lg border border-[#D5ECEA] bg-[#E8F4F3] px-3 py-2 text-xs font-medium text-[#0E7C7B]" x-text="statusMessage"></div>
                </template>
            </div>
        </x-sigap.card>

        {{-- Card 2: Preferensi Tingkat Alert --}}
        <x-sigap.card title="Preferensi Tingkat Notifikasi Push" icon="heroicon-o-adjustments-horizontal">
            <form wire:submit="savePreferences" class="space-y-4 text-sm text-[#3A4D52]">
                <div class="rounded-lg border border-[#F3C4C2] bg-[#FBEAEA]/60 p-3">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <div class="font-semibold text-[#9A2F2C]">Kritis (Alat Kritis Tidak Aman / Terlambat / Eskalasi)</div>
                            <div class="text-xs text-[#6B7C80]">
                                @if ($isCriticalLocked)
                                    Selalu aktif untuk Teknisi &amp; Admin IPSRS agar keselamatan pasien tetap terjaga.
                                @else
                                    Aktif sesuai peran Anda (Manajemen hanya menerima eskalasi kritis).
                                @endif
                            </div>
                        </div>
                        <span class="rounded-full bg-[#FBEAEA] px-2.5 py-0.5 text-xs font-semibold text-[#B83C38]">Selalu aktif</span>
                    </div>
                </div>

                <label class="flex items-start gap-3 rounded-lg border border-[#E3EAEA] p-3 cursor-pointer">
                    <input type="checkbox" wire:model="pushPrefImportant" class="mt-0.5 rounded border-gray-300 text-[#0E7C7B] focus:ring-[#0E7C7B]">
                    <div>
                        <div class="font-semibold text-[#13262B]">Penting lewat Push</div>
                        <div class="text-xs text-[#6B7C80]">Laporan kerusakan lain, verifikasi magang, kalibrasi H-7 &amp; H-0, pemeliharaan preventif H-0.</div>
                    </div>
                </label>

                <label class="flex items-start gap-3 rounded-lg border border-[#E3EAEA] p-3 cursor-pointer">
                    <input type="checkbox" wire:model="pushPrefInfo" class="mt-0.5 rounded border-gray-300 text-[#0E7C7B] focus:ring-[#0E7C7B]">
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
