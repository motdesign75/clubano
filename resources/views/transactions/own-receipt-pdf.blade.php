<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Eigenbeleg</title>
    <style>
        @page { margin: 18mm 16mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #0f172a; line-height: 1.45; }
        .header-table, .meta-table, .sign-table { width: 100%; border-collapse: collapse; }
        .header-table td, .meta-table td, .sign-table td { vertical-align: top; }
        .brand { font-size: 18pt; font-weight: bold; color: #0f172a; }
        .brand-meta { margin-top: 3mm; color: #475569; font-size: 9pt; }
        .logo { max-height: 48px; }
        .title { margin: 10mm 0 4mm; font-size: 20pt; font-weight: bold; }
        .subtitle { color: #64748b; font-size: 9pt; margin-bottom: 8mm; }
        .box { border: 1px solid #dbe4ff; border-radius: 14px; padding: 10px 12px; background: #f8fafc; }
        .label { font-size: 7.8pt; font-weight: bold; letter-spacing: 0.18em; text-transform: uppercase; color: #64748b; margin-bottom: 3mm; }
        .section { margin-top: 7mm; }
        .copy { white-space: pre-line; }
        .amount { font-size: 18pt; font-weight: bold; }
        .line { margin-top: 18mm; border-top: 1px solid #94a3b8; height: 1px; }
        .sign-label { margin-top: 3mm; font-size: 8pt; color: #64748b; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 62%;">
                <div class="brand">{{ $tenant->name }}</div>
                <div class="brand-meta">
                    {{ $tenant->address }}<br>
                    {{ $tenant->zip }} {{ $tenant->city }}<br>
                    @if($tenant->email){{ $tenant->email }}@endif
                </div>
            </td>
            <td style="width: 38%; text-align: right;">
                @if($logoPath)
                    <img src="{{ $logoPath }}" class="logo" alt="Vereinslogo">
                @endif
            </td>
        </tr>
    </table>

    <div class="title">Eigenbeleg</div>
    <div class="subtitle">Vereinsinterner Beleg für einen Vorgang ohne externen Nachweis</div>

    <table class="meta-table">
        <tr>
            <td style="width: 55%; padding-right: 6mm;">
                <div class="box">
                    <div class="label">Buchung</div>
                    <div><strong>Beschreibung:</strong> {{ $transaction->description }}</div>
                    <div><strong>Datum:</strong> {{ $transaction->date->format('d.m.Y') }}</div>
                    <div><strong>Betrag:</strong> {{ number_format((float) $transaction->amount, 2, ',', '.') }} €</div>
                    <div><strong>Kontierung:</strong> {{ $transaction->account_from->name ?? '—' }} → {{ $transaction->account_to->name ?? '—' }}</div>
                    <div><strong>Buchungsnummer:</strong> {{ $transaction->receipt_number ?: '—' }}</div>
                </div>
            </td>
            <td style="width: 45%;">
                <div class="box">
                    <div class="label">Eigenbeleg</div>
                    <div><strong>Belegnummer:</strong> {{ $receiptDocumentNumber }}</div>
                    <div><strong>Erstellt am:</strong> {{ now()->format('d.m.Y H:i') }}</div>
                    <div><strong>Aussteller:</strong> {{ $receiptMeta['issuer_name'] }}</div>
                    @if(!empty($receiptMeta['issuer_role']))
                        <div><strong>Funktion:</strong> {{ $receiptMeta['issuer_role'] }}</div>
                    @endif
                    @if(!empty($receiptMeta['location']))
                        <div><strong>Ort:</strong> {{ $receiptMeta['location'] }}</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="box">
            <div class="label">Wofür wurde das Geld ausgegeben?</div>
            <div class="copy">{{ $receiptMeta['expense_reason'] }}</div>
        </div>
    </div>

    <div class="section">
        <div class="box">
            <div class="label">Warum liegt kein externer Beleg vor?</div>
            <div class="copy">{{ $receiptMeta['missing_receipt_reason'] }}</div>
        </div>
    </div>

    @if(!empty($receiptMeta['notes']))
        <div class="section">
            <div class="box">
                <div class="label">Zusätzliche Notizen</div>
                <div class="copy">{{ $receiptMeta['notes'] }}</div>
            </div>
        </div>
    @endif

    <table class="sign-table" style="margin-top: 14mm;">
        <tr>
            <td style="width: 50%; padding-right: 8mm;">
                <div class="line"></div>
                <div class="sign-label">Unterschrift Aussteller</div>
            </td>
            <td style="width: 50%;">
                <div class="line"></div>
                <div class="sign-label">
                    Freigabe / geprüft von
                    @if(!empty($receiptMeta['approved_by']))
                        : {{ $receiptMeta['approved_by'] }}
                    @endif
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
