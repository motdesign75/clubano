<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Zuwendungsbestätigung {{ $donation->certificate_number }}</title>
    <style>
        @page { margin: 18mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 10pt; line-height: 1.42; }
        h1 { margin: 0 0 7mm; font-size: 18pt; }
        h2 { margin: 8mm 0 3mm; font-size: 11pt; }
        .muted { color: #64748b; }
        .box { border: 1px solid #cbd5e1; border-radius: 10px; padding: 4mm; margin-bottom: 5mm; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { vertical-align: top; padding: 1.8mm 0; }
        .label { width: 42mm; color: #64748b; }
        .value { font-weight: bold; }
        .amount { font-size: 14pt; font-weight: bold; }
        .notice { border-top: 1px solid #cbd5e1; margin-top: 8mm; padding-top: 4mm; font-size: 8.5pt; color: #334155; }
        .signature { margin-top: 16mm; width: 75mm; border-top: 1px solid #0f172a; padding-top: 2mm; }
    </style>
</head>
<body>
    <h1>Zuwendungsbestätigung</h1>
    <p class="muted">Bestätigung über Geldzuwendungen im Sinne des § 10b des Einkommensteuergesetzes an eine steuerbegünstigte Körperschaft.</p>

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
                <td>{{ $donation->certificate_number }}</td>
            </tr>
        </table>
    </div>

    <h2>Zuwendender</h2>
    <div class="box">
        <strong>{{ $donation->donor_name }}</strong><br>
        @if($donation->donor_street)
            {{ $donation->donor_street }}<br>
        @endif
        {{ $donation->donor_zip }} {{ $donation->donor_city }}
    </div>

    <h2>Zuwendung</h2>
    <div class="box">
        <table class="grid">
            <tr>
                <td class="label">Betrag</td>
                <td class="amount">{{ number_format($donation->amount, 2, ',', '.') }} €</td>
            </tr>
            <tr>
                <td class="label">Tag der Zuwendung</td>
                <td>{{ $donation->donated_at->format('d.m.Y') }}</td>
            </tr>
            <tr>
                <td class="label">Art der Zuwendung</td>
                <td>Geldzuwendung</td>
            </tr>
            <tr>
                <td class="label">Verwendungszweck</td>
                <td>{{ $donation->purpose ?: 'Förderung der satzungsmäßigen Zwecke' }}</td>
            </tr>
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

    <p>
        Es wird bestätigt, dass die Zuwendung nur zur Förderung der oben genannten steuerbegünstigten Zwecke verwendet wird.
    </p>

    <div class="signature">
        Ort, Datum und Unterschrift
    </div>

    <div class="notice">
        Hinweis: Diese PDF wird von Clubano aus den hinterlegten Vereins- und Spendendaten erzeugt. Die Angaben zum Freistellungsbescheid,
        zu den steuerbegünstigten Zwecken und zur Zulässigkeit der Ausstellung sind vor Verwendung durch den Verein zu prüfen.
    </div>
</body>
</html>
