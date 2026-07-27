<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Sammelbestätigung {{ $certificateNumber }}</title>
    <style>
        @page { margin: 18mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 10pt; line-height: 1.42; }
        h1 { margin: 0 0 7mm; font-size: 18pt; }
        h2 { margin: 8mm 0 3mm; font-size: 11pt; }
        .muted { color: #64748b; }
        .box { border: 1px solid #cbd5e1; border-radius: 10px; padding: 4mm; margin-bottom: 5mm; }
        .grid, .donation-table { width: 100%; border-collapse: collapse; }
        .grid td { vertical-align: top; padding: 1.8mm 0; }
        .label { width: 42mm; color: #64748b; }
        .value { font-weight: bold; }
        .amount { font-size: 14pt; font-weight: bold; }
        .donation-table { margin-top: 3mm; border: 1px solid #cbd5e1; }
        .donation-table th { background: #f1f5f9; color: #475569; font-size: 8pt; text-align: left; padding: 2mm; border-bottom: 1px solid #cbd5e1; }
        .donation-table td { padding: 2mm; border-bottom: 1px solid #e2e8f0; }
        .right { text-align: right; }
        .notice { border-top: 1px solid #cbd5e1; margin-top: 8mm; padding-top: 4mm; font-size: 8.5pt; color: #334155; }
        .signature { margin-top: 14mm; width: 75mm; border-top: 1px solid #0f172a; padding-top: 2mm; }
    </style>
</head>
<body>
    <h1>Sammelbestätigung über Geldzuwendungen</h1>
    <p class="muted">Bestätigung über Geldzuwendungen im Kalenderjahr {{ $year }} im Sinne des § 10b des Einkommensteuergesetzes.</p>

    <div class="box">
        <table class="grid">
            <tr>
                <td class="label">Aussteller</td>
                <td class="value">
                    {{ $tenant->name }}<br>
                    {{ $tenant->address }}<br>
                    {{ $tenant->zip }} {{ $tenant->city }}
                </td>
            </tr>
            <tr>
                <td class="label">Finanzamt</td>
                <td>{{ $tenant->donation_tax_office ?: 'Nicht hinterlegt' }}</td>
            </tr>
            <tr>
                <td class="label">Steuernummer</td>
                <td>{{ $tenant->donation_tax_number ?: 'Nicht hinterlegt' }}</td>
            </tr>
            <tr>
                <td class="label">Bescheinigung Nr.</td>
                <td>{{ $certificateNumber }}</td>
            </tr>
        </table>
    </div>

    <h2>Zuwendender</h2>
    <div class="box">
        <strong>{{ $donor->donor_name }}</strong><br>
        @if($donor->donor_street)
            {{ $donor->donor_street }}<br>
        @endif
        {{ $donor->donor_zip }} {{ $donor->donor_city }}
    </div>

    <h2>Zuwendungen im Kalenderjahr {{ $year }}</h2>
    <div class="box">
        <table class="grid">
            <tr>
                <td class="label">Gesamtbetrag</td>
                <td class="amount">{{ number_format($totalAmount, 2, ',', '.') }} €</td>
            </tr>
            <tr>
                <td class="label">Art der Zuwendung</td>
                <td>Geldzuwendung</td>
            </tr>
        </table>

        <table class="donation-table">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Zweck</th>
                    <th class="right">Betrag</th>
                </tr>
            </thead>
            <tbody>
                @foreach($donations as $donation)
                    <tr>
                        <td>{{ $donation->donated_at->format('d.m.Y') }}</td>
                        <td>{{ $donation->purpose ?: 'Förderung der satzungsmäßigen Zwecke' }}</td>
                        <td class="right">{{ number_format($donation->amount, 2, ',', '.') }} €</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2>Steuerbegünstigte Zwecke</h2>
    <p>
        Wir sind wegen Förderung folgender gemeinnütziger Zwecke nach dem letzten uns zugegangenen Bescheid
        {{ $tenant->donation_notice_authority ? 'des ' . $tenant->donation_notice_authority : 'der zuständigen Finanzbehörde' }}
        @if($tenant->donation_notice_date)
            vom {{ $tenant->donation_notice_date->format('d.m.Y') }}
        @endif
        nach § 5 Abs. 1 Nr. 9 KStG von der Körperschaftsteuer befreit.
    </p>
    <p>{{ $tenant->donation_purposes ?: 'Begünstigte Zwecke sind noch nicht hinterlegt.' }}</p>
    <p>Es wird bestätigt, dass die Zuwendungen nur zur Förderung der oben genannten steuerbegünstigten Zwecke verwendet werden.</p>

    <div class="signature">
        Ort, Datum und Unterschrift
    </div>

    <div class="notice">
        Diese Sammelbestätigung umfasst nur die oben aufgeführten, bisher nicht einzeln bescheinigten Zuwendungen.
        Bereits bescheinigte oder stornierte Spenden werden von Clubano nicht erneut aufgenommen.
    </div>
</body>
</html>
