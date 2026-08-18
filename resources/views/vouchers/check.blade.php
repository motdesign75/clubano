@extends('layouts.app')

@section('title', 'Gutschein prüfen')

@section('content')
<div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-3xl bg-slate-950 px-6 py-7 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">Gutscheinprüfung</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Scannen. Prüfen. Sicher einlösen.</h1>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300 sm:text-base">
                    Scanne den QR-Code auf dem Gutschein oder gib den Code manuell ein. Clubano zeigt sofort, ob der Gutschein gültig ist.
                </p>
            </div>
            <a href="{{ route('vouchers.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/20 px-5 text-sm font-semibold text-white hover:bg-white/10">
                Zur Gutscheinliste
            </a>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-950">Code prüfen</h2>
                    <p class="mt-1 text-sm text-slate-500">Manuell eingeben oder Kamera öffnen.</p>
                </div>
                <button type="button" id="startScanner" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                    QR-Code scannen
                </button>
            </div>

            <form method="GET" action="{{ route('vouchers.check') }}" class="mt-5 grid gap-3 sm:grid-cols-[1fr_auto]">
                <input id="voucherCodeInput" type="text" name="code" value="{{ $code }}" placeholder="z. B. CLB-2026-ABC123" class="rounded-xl border-slate-300 font-mono text-sm uppercase shadow-sm focus:border-slate-500 focus:ring-slate-300">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Prüfen
                </button>
            </form>

            <div id="scannerPanel" class="mt-5 hidden rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <video id="scannerVideo" class="aspect-video w-full rounded-xl bg-slate-950 object-cover" muted playsinline></video>
                <div id="scannerMessage" class="mt-3 text-sm text-slate-600">Kamera wird gestartet ...</div>
                <button type="button" id="stopScanner" class="mt-3 inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-white">
                    Scanner schließen
                </button>
            </div>
        </div>

        <aside class="rounded-3xl border {{ $voucher?->is_redeemable ? 'border-emerald-200 bg-emerald-50' : ($code ? 'border-rose-200 bg-rose-50' : 'border-slate-200 bg-white') }} p-5 shadow-sm sm:p-6">
            @if($voucher)
                <div class="text-xs font-semibold uppercase tracking-[0.18em] {{ $voucher->is_redeemable ? 'text-emerald-700' : 'text-rose-700' }}">
                    {{ $voucher->is_redeemable ? 'Gültig' : 'Nicht einlösbar' }}
                </div>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ $voucher->title }}</h2>
                <div class="mt-4 rounded-2xl bg-white/80 p-4">
                    <div class="font-mono text-lg font-semibold text-slate-950">{{ $voucher->code }}</div>
                    <div class="mt-3 grid gap-3 text-sm text-slate-600">
                        <div class="flex justify-between gap-4">
                            <span>Restwert</span>
                            <strong class="text-slate-950">{{ number_format((float) $voucher->remaining_amount, 2, ',', '.') }} {{ strtoupper($voucher->currency ?: 'EUR') }}</strong>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span>Ursprungswert</span>
                            <strong class="text-slate-950">{{ number_format((float) $voucher->original_amount, 2, ',', '.') }} {{ strtoupper($voucher->currency ?: 'EUR') }}</strong>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span>Status</span>
                            <strong class="text-slate-950">{{ $voucher->status_label }}</strong>
                        </div>
                        @if($voucher->expires_at)
                            <div class="flex justify-between gap-4">
                                <span>Gültig bis</span>
                                <strong class="text-slate-950">{{ $voucher->expires_at->format('d.m.Y') }}</strong>
                            </div>
                        @endif
                        @if($voucher->recipient_name)
                            <div class="flex justify-between gap-4">
                                <span>Empfänger</span>
                                <strong class="text-right text-slate-950">{{ $voucher->recipient_name }}</strong>
                            </div>
                        @endif
                    </div>
                </div>

                @if($voucher->redemptions->isNotEmpty())
                    <div class="mt-4 text-sm font-semibold text-slate-950">Letzte Einlösungen</div>
                    <div class="mt-2 space-y-2">
                        @foreach($voucher->redemptions as $redemption)
                            <div class="rounded-xl bg-white/80 px-3 py-2 text-sm text-slate-600">
                                {{ $redemption->created_at->format('d.m.Y H:i') }} · {{ number_format((float) $redemption->amount, 2, ',', '.') }} {{ strtoupper($voucher->currency ?: 'EUR') }}
                            </div>
                        @endforeach
                    </div>
                @endif
            @elseif($code)
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-700">Nicht gefunden</div>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950">Kein Gutschein zu diesem Code</h2>
                <p class="mt-3 text-sm leading-6 text-rose-900">
                    Prüfe Tippfehler oder ob der Gutschein zu einem anderen Verein gehört. Clubano zeigt hier bewusst keine fremden Gutscheine an.
                </p>
            @else
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Bereit</div>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950">Noch kein Code geprüft</h2>
                <p class="mt-3 text-sm leading-6 text-slate-500">
                    Öffne die Kamera oder gib den Code ein. Das Ergebnis erscheint hier sofort.
                </p>
            @endif
        </aside>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const startButton = document.getElementById('startScanner');
    const stopButton = document.getElementById('stopScanner');
    const panel = document.getElementById('scannerPanel');
    const video = document.getElementById('scannerVideo');
    const message = document.getElementById('scannerMessage');
    const input = document.getElementById('voucherCodeInput');
    let stream = null;
    let scannerTimer = null;

    const stopScanner = () => {
        if (scannerTimer) {
            clearInterval(scannerTimer);
            scannerTimer = null;
        }

        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
            stream = null;
        }

        panel.classList.add('hidden');
    };

    const extractCode = (value) => {
        try {
            const url = new URL(value);
            return url.searchParams.get('code') || value;
        } catch (error) {
            return value;
        }
    };

    startButton?.addEventListener('click', async () => {
        if (!('BarcodeDetector' in window)) {
            message.textContent = 'Dieser Browser unterstützt QR-Scanning nicht. Bitte den Code manuell eingeben.';
            panel.classList.remove('hidden');
            return;
        }

        panel.classList.remove('hidden');
        message.textContent = 'Kamera wird gestartet ...';

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
                audio: false,
            });
            video.srcObject = stream;
            await video.play();

            const detector = new BarcodeDetector({ formats: ['qr_code'] });
            message.textContent = 'QR-Code vor die Kamera halten.';

            scannerTimer = setInterval(async () => {
                const codes = await detector.detect(video);

                if (codes.length === 0) {
                    return;
                }

                const detectedCode = extractCode(codes[0].rawValue || '');
                input.value = detectedCode;
                stopScanner();
                window.location.href = '{{ route('vouchers.check') }}?code=' + encodeURIComponent(detectedCode);
            }, 600);
        } catch (error) {
            message.textContent = 'Kamera konnte nicht gestartet werden. Bitte den Code manuell eingeben.';
        }
    });

    stopButton?.addEventListener('click', stopScanner);
});
</script>
@endsection
