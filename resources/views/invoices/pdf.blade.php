<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->getDocumentLabel() }} {{ $invoice->invoice_number }}</title>

    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #0f172a; line-height: 1.45; }
        .document { position: relative; min-height: 297mm; box-sizing: border-box; padding: 24mm 18mm 34mm 18mm; }
        .letterhead-image { position: fixed; top: 0; left: 0; width: 210mm; height: 297mm; z-index: -1; }
        .header-table,.meta-table,.items-table,.summary-table,.footer-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .brand { font-size: 18pt; font-weight: bold; color: #0f172a; }
        .brand-meta { margin-top: 4mm; color: #475569; font-size: 9pt; }
        .logo { max-height: 48px; }
        .recipient-block,.meta-box { border: 1px solid #dbe4ff; border-radius: 14px; padding: 10px 12px; background: #f8fafc; }
        .section-label { font-size: 7.8pt; font-weight: bold; letter-spacing: 0.18em; text-transform: uppercase; color: #64748b; margin-bottom: 4mm; }
        .invoice-title { margin: 10mm 0 4mm; font-size: 20pt; font-weight: bold; }
        .copy { margin: 0 0 5mm; white-space: pre-line; }
        .items-table { margin-top: 6mm; border: 1px solid #dbe4ff; border-radius: 14px; overflow: hidden; }
        .items-table th { padding: 8px 10px; text-align: left; background: #eef2ff; color: #334155; font-size: 8pt; font-weight: bold; letter-spacing: 0.12em; text-transform: uppercase; border-bottom: 1px solid #dbe4ff; }
        .items-table td { padding: 9px 10px; border-bottom: 1px solid #e2e8f0; }
        .items-table tr:last-child td { border-bottom: none; }
        .right { text-align: right; }
        .summary-wrap { margin-top: 7mm; margin-left: auto; width: 78mm; page-break-inside: avoid; }
        .summary-table td { padding: 3px 0; }
        .summary-table .total td { padding-top: 5px; border-top: 1px solid #cbd5e1; font-size: 11pt; font-weight: bold; }
        .payment-copy { margin-top: 8mm; page-break-inside: avoid; }
        .payment-qr-box { margin-top: 6mm; border: 1px solid #dbe4ff; border-radius: 14px; padding: 10px 12px; background: #f8fafc; page-break-inside: avoid; }
        .payment-qr-table { width: 100%; border-collapse: collapse; }
        .payment-qr-table td { vertical-align: top; }
        .payment-qr { width: 34mm; }
        .payment-detail { font-size: 8.7pt; color: #475569; }
        .payment-detail strong { color: #0f172a; }
        .closing { margin-top: 10mm; white-space: pre-line; page-break-inside: avoid; }
        .footer { position: fixed; left: 18mm; right: 18mm; bottom: 8mm; border-top: 1px solid #cbd5e1; padding-top: 3mm; font-size: 8pt; color: #475569; }
        .footer-table td { width: 33.33%; vertical-align: top; }
    </style>
</head>
<body>
    @php
        $positions = $invoice->items ?? collect();
    @endphp
    <div class="document">
    @if(!empty($showLetterheadImage) && !empty($letterheadImagePath))
        <img src="{{ $letterheadImagePath }}" alt="Briefbogen" class="letterhead-image">
    @endif

    @unless(!empty($usesLetterhead))
        <table class="header-table">
            <tr>
                <td style="width: 62%;">
                    <div class="brand">{{ $tenant->name }}</div>
                    <div class="brand-meta">
                        {{ $tenant->address }}<br>
                        {{ $tenant->zip }} {{ $tenant->city }}
                    </div>
                </td>
                <td class="right" style="width: 38%;">
                    @if($tenant->logo_storage_path && file_exists(storage_path('app/public/' . $tenant->logo_storage_path)))
                        <img src="{{ storage_path('app/public/' . $tenant->logo_storage_path) }}" class="logo">
                    @elseif($tenant->logo && file_exists(storage_path('app/public/' . $tenant->logo)))
                        <img src="{{ storage_path('app/public/' . $tenant->logo) }}" class="logo">
                    @endif
                </td>
            </tr>
        </table>
    @endunless

    <table class="meta-table" style="margin-top: 8mm;">
        <tr>
            <td style="width: 55%; padding-right: 8mm;">
                <div class="recipient-block">
                    <div class="section-label">Rechnung an</div>
                    @foreach ($invoice->getRecipientAddressLines() as $line)
                        <div>{{ $line }}</div>
                    @endforeach
                    @if ($invoice->recipient_email)
                        <div style="margin-top: 3mm;">{{ $invoice->recipient_email }}</div>
                    @endif
                </div>
            </td>
            <td style="width: 45%;">
                <div class="meta-box">
                    <div class="section-label">Rechnungsdaten</div>
                    <div>{{ $invoice->isOffer() ? 'Angebotsnummer' : 'Rechnungsnummer' }}: {{ $invoice->invoice_number }}</div>
                    <div>{{ $invoice->isOffer() ? 'Angebotsdatum' : 'Rechnungsdatum' }}: {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d.m.Y') }}</div>
                    <div>{{ $invoice->isOffer() ? 'Gueltig bis' : 'Faellig am' }}: {{ $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('d.m.Y') : '—' }}</div>
                    <div>Status: {{ ucfirst($invoice->status) }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="invoice-title">{{ $invoice->getDocumentLabel() }} {{ $invoice->invoice_number }}</div>

    <p class="copy">{{ $invoice->intro_text }}</p>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 7%;">Pos.</th>
                <th>Beschreibung</th>
                <th style="width: 14%;" class="right">Menge</th>
                <th style="width: 14%;">Einheit</th>
                <th style="width: 18%;" class="right">Einzelpreis</th>
                <th style="width: 18%;" class="right">Summe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($positions as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        <div>{{ $item->description }}</div>
                        @if(!blank($item->details))
                            <div style="margin-top: 2mm; font-size: 8.8pt; color: #475569; white-space: pre-line;">{{ $item->details }}</div>
                        @endif
                    </td>
                    <td class="right">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                    <td>{{ $item->unit ?: '—' }}</td>
                    <td class="right">{{ number_format($item->unit_price, 2, ',', '.') }} €</td>
                    <td class="right">{{ number_format($item->quantity * $item->unit_price, 2, ',', '.') }} €</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary-wrap">
        <table class="summary-table">
            <tr>
                <td>Zwischensumme</td>
                <td class="right">{{ number_format($invoice->getSubtotal(), 2, ',', '.') }} €</td>
            </tr>
            <tr>
                <td>Rabatt</td>
                <td class="right">-{{ number_format($invoice->getDiscountAmount(), 2, ',', '.') }} €</td>
            </tr>
            <tr>
                <td>USt. {{ number_format($invoice->tax_rate ?? 0, 2, ',', '.') }} %</td>
                <td class="right">{{ number_format($invoice->getTaxAmount(), 2, ',', '.') }} €</td>
            </tr>
            <tr class="total">
                <td>Gesamt</td>
                <td class="right">{{ number_format($invoice->getTotal(), 2, ',', '.') }} €</td>
            </tr>
        </table>
    </div>

    <p class="copy payment-copy">{{ $invoice->payment_text }}</p>

    @if(!empty($paymentQrPayload))
        @php($paymentQrCode = str_replace(["\r\n", "\r", "\n"], '\n', $paymentQrPayload))
        <div class="payment-qr-box">
            <table class="payment-qr-table">
                <tr>
                    <td style="width: 40mm; padding-right: 8mm;">
                        <barcode code="{{ e($paymentQrCode) }}" type="QR" size="1.25" error="M" disableborder="1" />
                    </td>
                    <td>
                        <div class="section-label">Per Banking-App zahlen</div>
                        <div class="payment-detail">
                            QR-Code scannen und Überweisung prüfen.<br>
                            <strong>Empfänger:</strong> {{ $tenant->name }}<br>
                            <strong>IBAN:</strong> {{ $tenant->iban }}<br>
                            @if($tenant->bic)
                                <strong>BIC:</strong> {{ $tenant->bic }}<br>
                            @endif
                            <strong>Betrag:</strong> {{ number_format($invoice->getTotal(), 2, ',', '.') }} €<br>
                            <strong>Verwendungszweck:</strong> Rechnung {{ $invoice->invoice_number }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    @endif

    <div class="closing">{{ $invoice->closing_text }}</div>
    </div>

    @unless(!empty($usesLetterhead))
        <div class="footer">
            <table class="footer-table">
                <tr>
                    <td>
                        {{ $tenant->name }}<br>
                        {{ $tenant->address }}<br>
                        {{ $tenant->zip }} {{ $tenant->city }}
                    </td>
                    <td>
                        IBAN: {{ $tenant->iban ?? '—' }}<br>
                        BIC: {{ $tenant->bic ?? '—' }}
                    </td>
                    <td class="right">
                        {{ $tenant->email }}<br>
                        {{ $tenant->phone ?? '' }}
                    </td>
                </tr>
            </table>
        </div>
    @endunless
</body>
</html>
