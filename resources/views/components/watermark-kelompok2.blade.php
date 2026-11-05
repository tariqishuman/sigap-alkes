@if (config('app.watermark_enabled', true))
    <div
        id="sigap-watermark-kelompok2"
        aria-hidden="true"
        style="
            position: fixed;
            inset: 0;
            z-index: 99999;
            pointer-events: none;
            user-select: none;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        "
    >
        {{-- Pola diagonal berulang di seluruh layar agar tidak bisa di-crop --}}
        <div
            style="
                position: absolute;
                inset: -50%;
                display: flex;
                flex-wrap: wrap;
                align-content: center;
                justify-content: center;
                gap: 4.5rem 6rem;
                transform: rotate(-24deg);
                opacity: 0.085;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                font-weight: 900;
                font-size: 1.15rem;
                letter-spacing: 0.22em;
                color: #B91C1C;
                text-transform: uppercase;
                white-space: nowrap;
            "
        >
            @for ($i = 0; $i < 48; $i++)
                <span>██ CLASSIFIED // MILIK KELOMPOK 2 // COPYRIGHT ██</span>
            @endfor
        </div>

        {{-- Stempel raksasa utama bergaya CLASSIFIED / REDACTED di tengah layar --}}
        <div
            style="
                position: relative;
                transform: rotate(-18deg);
                border: 6px solid rgba(185, 28, 28, 0.26);
                outline: 2px dashed rgba(185, 28, 28, 0.22);
                outline-offset: -12px;
                border-radius: 0.75rem;
                padding: 1.75rem 3.25rem;
                text-align: center;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                color: rgba(185, 28, 28, 0.26);
                background: rgba(254, 242, 242, 0.04);
                box-shadow: inset 0 0 0 3px rgba(185, 28, 28, 0.12);
                max-width: 92vw;
            "
        >
            <div
                style="
                    font-size: clamp(0.75rem, 1.5vw, 1.05rem);
                    font-weight: 800;
                    letter-spacing: 0.35em;
                    text-transform: uppercase;
                    margin-bottom: 0.35rem;
                "
            >
                ★ TOP SECRET // RESTRICTED ACCESS ★
            </div>

            <div
                style="
                    font-size: clamp(2.25rem, 6vw, 4.75rem);
                    font-weight: 900;
                    letter-spacing: 0.14em;
                    line-height: 1.05;
                    text-transform: uppercase;
                    border-top: 3px solid rgba(185, 28, 28, 0.24);
                    border-bottom: 3px solid rgba(185, 28, 28, 0.24);
                    padding: 0.4rem 0;
                    margin: 0.35rem 0;
                "
            >
                MILIK KELOMPOK 2
            </div>

            <div
                style="
                    font-size: clamp(0.85rem, 1.8vw, 1.25rem);
                    font-weight: 800;
                    letter-spacing: 0.25em;
                    text-transform: uppercase;
                    margin-top: 0.45rem;
                "
            >
                <span style="background: rgba(185, 28, 28, 0.22); color: transparent; padding: 0 0.4rem; border-radius: 2px;">REDACTED</span>
                &nbsp;COPYRIGHT © KELOMPOK 2 — DO NOT COPY&nbsp;
                <span style="background: rgba(185, 28, 28, 0.22); color: transparent; padding: 0 0.4rem; border-radius: 2px;">REDACTED</span>
            </div>
        </div>
    </div>
@endif
