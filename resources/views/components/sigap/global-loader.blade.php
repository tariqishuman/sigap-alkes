<div
    x-data="{
        active: false,
        progress: 0,
        slowPill: false,
        timer: null,
        pillTimer: null,
        start() {
            this.active = true;
            this.progress = 18;
            clearInterval(this.timer);
            clearTimeout(this.pillTimer);
            this.timer = setInterval(() => {
                if (this.progress < 88) {
                    this.progress += Math.max(2, (90 - this.progress) * 0.14);
                }
            }, 140);
            this.pillTimer = setTimeout(() => {
                if (this.active) this.slowPill = true;
            }, 350);
        },
        done() {
            clearInterval(this.timer);
            clearTimeout(this.pillTimer);
            this.progress = 100;
            this.slowPill = false;
            setTimeout(() => {
                this.active = false;
                this.progress = 0;
            }, 220);
        },
        init() {
            document.addEventListener('livewire:navigate', () => this.start());
            document.addEventListener('livewire:navigated', () => this.done());
            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                let pending = 0;
                window.Livewire.hook('commit', ({ respond, succeed, fail }) => {
                    pending++;
                    const delay = setTimeout(() => {
                        if (pending > 0 && !this.active) this.start();
                    }, 160);
                    const finish = () => {
                        clearTimeout(delay);
                        pending = Math.max(0, pending - 1);
                        if (pending === 0 && this.active) this.done();
                    };
                    succeed(finish);
                    fail(finish);
                });
            }
        }
    }"
    aria-live="polite"
>
    {{-- Top slim progress bar --}}
    <div
        x-show="active"
        x-transition.opacity.duration.150ms
        style="display: none; position: fixed; top: 0; left: 0; right: 0; height: 3px; z-index: 9999; pointer-events: none;"
    >
        <div
            :style="'width: ' + progress + '%; height: 100%; background: linear-gradient(90deg, #0E7C7B 0%, #5CC8C4 100%); box-shadow: 0 0 8px rgba(14, 124, 123, 0.45); transition: width 160ms cubic-bezier(0.22, 1, 0.36, 1);'"
        ></div>
    </div>

    {{-- Subtle bottom-right loader pill only if request takes > 350ms --}}
    <div
        x-show="slowPill"
        x-transition.opacity.duration.150ms
        style="display: none; position: fixed; bottom: 1.25rem; right: 1.25rem; z-index: 9998; pointer-events: none;"
        class="inline-flex items-center gap-2 rounded-full border border-[#D5ECEA] bg-white/95 px-3.5 py-2 text-xs font-semibold text-[#0E7C7B] shadow-md backdrop-blur-xs"
    >
        <svg class="h-3.5 w-3.5 animate-spin text-[#0E7C7B]" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4z"></path>
        </svg>
        <span>Memuat data…</span>
    </div>
</div>
