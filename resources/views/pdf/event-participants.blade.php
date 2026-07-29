<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Teilnehmerliste {{ $event->title }}</title>
    <style>
        @page { margin: 12mm 12mm 14mm; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #111827;
            font-size: 9.2pt;
            line-height: 1.32;
        }
        .header {
            border-bottom: 2px solid #111827;
            padding-bottom: 5mm;
        }
        .eyebrow {
            color: #6b7280;
            font-size: 7.3pt;
            font-weight: bold;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        h1 {
            margin: 2mm 0 0;
            font-size: 24pt;
            line-height: 1.05;
        }
        .event-title {
            margin-top: 2mm;
            color: #374151;
            font-size: 11pt;
            font-weight: bold;
        }
        .meta {
            width: 100%;
            margin-top: 5mm;
            border-collapse: collapse;
        }
        .meta td {
            width: 25%;
            border-left: 1.5px solid #d1d5db;
            padding: 0 3mm;
            vertical-align: top;
        }
        .label {
            display: block;
            color: #6b7280;
            font-size: 7.1pt;
            font-weight: bold;
            letter-spacing: .1em;
            text-transform: uppercase;
        }
        .value {
            display: block;
            margin-top: 1.2mm;
            color: #111827;
            font-weight: bold;
        }
        .stats {
            width: 100%;
            margin-top: 6mm;
            border: 1px solid #d1d5db;
            border-collapse: collapse;
        }
        .stats td {
            width: 20%;
            border-right: 1px solid #e5e7eb;
            background: #fafafa;
            padding: 3mm;
            vertical-align: top;
        }
        .stats td:last-child { border-right: 0; }
        .stats span {
            display: block;
            color: #6b7280;
            font-size: 7pt;
            font-weight: bold;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .stats strong {
            display: block;
            margin-top: 1.4mm;
            color: #111827;
            font-size: 12.5pt;
            line-height: 1.1;
        }
        .participants {
            width: 100%;
            margin-top: 7mm;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .participants thead { display: table-header-group; }
        .participants tr { page-break-inside: avoid; }
        .participants th {
            border-top: 1px solid #111827;
            border-bottom: 1px solid #111827;
            padding: 2.3mm 1.6mm;
            color: #374151;
            text-align: left;
            font-size: 7pt;
            font-weight: bold;
            letter-spacing: .07em;
            text-transform: uppercase;
        }
        .participants td {
            border-bottom: 1px solid #e5e7eb;
            padding: 2.4mm 1.6mm;
            vertical-align: top;
        }
        .participants tbody tr:nth-child(even) td { background: #fafafa; }
        .name {
            color: #111827;
            font-size: 9.4pt;
            font-weight: bold;
        }
        .subline {
            display: block;
            margin-top: .7mm;
            color: #6b7280;
            font-size: 7.3pt;
        }
        .pill {
            border: 1px solid #d1d5db;
            border-radius: 20px;
            padding: 1mm 2mm;
            color: #374151;
            font-size: 7.2pt;
            font-weight: bold;
        }
        .right { text-align: right; }
        .check {
            display: inline-block;
            width: 7mm;
            height: 7mm;
            border: 1.3px solid #9ca3af;
            border-radius: 3px;
        }
        .signature {
            display: block;
            margin-top: 3mm;
            border-bottom: 1px solid #9ca3af;
            height: 5mm;
        }
        .footer {
            margin-top: 8mm;
            padding-top: 3mm;
            border-top: 1px solid #d1d5db;
            color: #6b7280;
            font-size: 7.4pt;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="eyebrow">Clubano Teilnehmerdokument</div>
        <h1>Teilnehmerliste</h1>
        <div class="event-title">{{ $event->title }} · Anzeige: {{ $displayMode === 'organization' ? 'Firma / Organisation' : 'Vor- und Nachname' }}</div>

        <table class="meta">
            <tr>
                <td><span class="label">Verein</span><span class="value">{{ $tenant?->name ?: 'Nicht angegeben' }}</span></td>
                <td><span class="label">Datum</span><span class="value">{{ $event->start?->format('d.m.Y H:i') }}</span></td>
                <td><span class="label">Ort</span><span class="value">{{ $event->location ?: 'Nicht angegeben' }}</span></td>
                <td><span class="label">Ausdruck</span><span class="value">{{ now()->format('d.m.Y H:i') }}</span></td>
            </tr>
        </table>
    </header>

    <table class="stats">
        <tr>
            <td><span>Teilnehmer</span><strong>{{ $stats['count'] }}</strong></td>
            <td><span>Bezahlt</span><strong>{{ $stats['paid'] }}</strong></td>
            <td><span>Offen</span><strong>{{ $stats['open'] }}</strong></td>
            <td><span>Kostenfrei</span><strong>{{ $stats['free'] }}</strong></td>
            <td><span>Summe</span><strong>{{ number_format((float) $stats['total'], 2, ',', '.') }} {{ strtoupper($event->currency ?: 'EUR') }}</strong></td>
        </tr>
    </table>

    <table class="participants">
        <thead>
            <tr>
                <th style="width: 27%;">Teilnehmer</th>
                <th style="width: 11%;">Art</th>
                <th style="width: 20%;">Kontakt</th>
                <th style="width: 16%;">Zahlung</th>
                <th style="width: 10%;" class="right">Betrag</th>
                <th style="width: 16%;">Anwesenheit</th>
            </tr>
        </thead>
        <tbody>
            @forelse($participants as $row)
                @php($participant = $row['participant'])
                <tr>
                    <td>
                        <span class="name">{{ $row['display_name'] }}</span>
                        <span class="subline">{{ $row['display_subline'] }}</span>
                    </td>
                    <td><span class="pill">{{ $participant->type_label }}</span></td>
                    <td>
                        {{ $participant->email ?: '-' }}
                        @if($participant->phone)
                            <span class="subline">{{ $participant->phone }}</span>
                        @endif
                    </td>
                    <td>
                        {{ $participant->payment_status_label }}
                        @if($participant->payment_reason)
                            <span class="subline">{{ $participant->payment_reason }}</span>
                        @endif
                    </td>
                    <td class="right">{{ number_format((float) $participant->price_amount, 2, ',', '.') }}</td>
                    <td>
                        <span class="check"></span>
                        <span class="signature"></span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Keine Teilnehmer vorhanden.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <footer class="footer">
        Erzeugt mit Clubano. Zahlungsstatus und Beträge entsprechen dem Stand zum Zeitpunkt des Ausdrucks.
    </footer>
</body>
</html>
