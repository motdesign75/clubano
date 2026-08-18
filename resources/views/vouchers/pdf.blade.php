<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Gutschein {{ $voucher->code }}</title>
    <style>
        @page { margin: 0; size: {{ $pageWidthMm }}mm {{ $pageHeightMm }}mm; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #0f172a; }
        .page { position: relative; width: {{ $pageWidthMm }}mm; height: {{ $pageHeightMm }}mm; overflow: hidden; background: #f8fafc; }
        .template { position: absolute; left: 0; top: 0; width: {{ $pageWidthMm }}mm; height: {{ $pageHeightMm }}mm; }
        .fallback { position: absolute; inset: 0; padding: 22mm; background: linear-gradient(135deg, #0f172a, #0f766e); color: white; box-sizing: border-box; }
        .fallback-title { margin-top: 18mm; font-size: 52pt; font-weight: bold; letter-spacing: .02em; }
        .fallback-subtitle { margin-top: 7mm; font-size: 18pt; }
        .fallback-value { margin-top: 18mm; font-size: 42pt; font-weight: bold; }
        .fallback-tenant { position: absolute; left: 22mm; bottom: 18mm; font-size: 15pt; }
        .overlay { position: absolute; padding: 3.5mm 4mm; width: 62mm; border-radius: 4mm; background: #fff; box-shadow: 0 3mm 12mm rgba(15,23,42,.22); color: {{ $codeColor }}; box-sizing: border-box; }
        .bottom-right { right: 8mm; bottom: 22mm; }
        .bottom-left { left: 8mm; bottom: 22mm; }
        .top-right { right: 8mm; top: 8mm; }
        .top-left { left: 8mm; top: 8mm; }
        .label { font-size: 6.5pt; font-weight: bold; letter-spacing: .24em; text-transform: uppercase; color: #64748b; }
        .code { margin-top: 1.4mm; font-size: 10pt; font-weight: bold; letter-spacing: .04em; line-height: 1.25; color: #0f172a; word-break: break-all; }
        .meta { margin-top: 1.8mm; font-size: 7.2pt; color: #475569; line-height: 1.4; }
        .qr { position: absolute; right: 4mm; top: 4mm; width: 18mm; height: 18mm; }
        .with-qr { padding-right: 25mm; min-height: 25mm; }
    </style>
</head>
<body>
    <div class="page">
        @if($templatePath)
            <img src="{{ $templatePath }}" alt="Gutscheinvorlage" class="template">
        @else
            <div class="fallback">
                <div class="label" style="color: rgba(255,255,255,.72);">Gutschein</div>
                <div class="fallback-title">{{ $voucher->title ?: 'Gutschein' }}</div>
                <div class="fallback-subtitle">Einlösbar bei {{ $tenant->name }}</div>
                <div class="fallback-value">{{ number_format((float) $voucher->original_amount, 2, ',', '.') }} {{ strtoupper($voucher->currency ?: 'EUR') }}</div>
                <div class="fallback-tenant">{{ $tenant->name }} · {{ trim(($tenant->zip ?? '') . ' ' . ($tenant->city ?? '')) }}</div>
            </div>
        @endif

        <div class="overlay {{ $positionClass }} {{ $qrCodeDataUri ? 'with-qr' : '' }}">
            @if($qrCodeDataUri)
                <img src="{{ $qrCodeDataUri }}" alt="QR-Code" class="qr">
            @endif
            <div class="label">Gutscheincode</div>
            <div class="code">{{ $voucher->code }}</div>
            <div class="meta">
                Wert: {{ number_format((float) $voucher->original_amount, 2, ',', '.') }} {{ strtoupper($voucher->currency ?: 'EUR') }}<br>
                @if($voucher->recipient_name)
                    Für: {{ $voucher->recipient_name }}<br>
                @endif
                @if($voucher->expires_at)
                    Gültig bis: {{ $voucher->expires_at->format('d.m.Y') }}<br>
                @endif
                Nur mit diesem Code einlösbar.
            </div>
        </div>
    </div>
</body>
</html>
