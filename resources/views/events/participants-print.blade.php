<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Teilnehmerliste {{ $event->title }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 12mm 14mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #111827;
            background: #f4f6f8;
            font-size: 9.4pt;
            line-height: 1.32;
        }
        .toolbar {
            margin: 0 auto 8mm;
            max-width: 210mm;
            padding: 8mm 8mm 0;
        }
        .toolbar button {
            border: 0;
            border-radius: 9px;
            background: #111827;
            color: white;
            padding: 10px 16px;
            font-weight: 700;
            cursor: pointer;
        }
        .sheet {
            width: 186mm;
            min-height: 270mm;
            margin: 0 auto;
            background: #fff;
            padding: 0;
        }
        .topbar {
            border-bottom: 2px solid #111827;
            padding-bottom: 5mm;
        }
        .eyebrow {
            color: #6b7280;
            font-size: 7.5pt;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
        }
        h1 {
            margin: 2mm 0 0;
            font-size: 24pt;
            line-height: 1.05;
            letter-spacing: 0;
        }
        .event-title {
            margin-top: 2mm;
            color: #374151;
            font-size: 11pt;
            font-weight: 700;
        }
        .muted { color: #6b7280; }
        .meta {
            margin-top: 5mm;
            display: grid;
            grid-template-columns: 1.2fr 1fr 1fr 1fr;
            gap: 3mm;
        }
        .meta-item {
            border-left: 1.5px solid #d1d5db;
            padding-left: 3mm;
            min-height: 12mm;
        }
        .label {
            display: block;
            color: #6b7280;
            font-size: 7.3pt;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .value {
            display: block;
            margin-top: 1.2mm;
            color: #111827;
            font-weight: 700;
        }
        .stats {
            margin-top: 6mm;
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            border: 1px solid #d1d5db;
            border-radius: 10px;
            overflow: hidden;
        }
        .stat {
            min-height: 15mm;
            padding: 3mm;
            border-right: 1px solid #e5e7eb;
            background: #fafafa;
        }
        .stat:last-child { border-right: 0; }
        .stat span {
            display: block;
            color: #6b7280;
            font-size: 7.2pt;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
        }
        .stat strong {
            display: block;
            margin-top: 1.5mm;
            color: #111827;
            font-size: 13pt;
            line-height: 1.1;
        }
        table {
            margin-top: 7mm;
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        thead { display: table-header-group; }
        tr { break-inside: avoid; page-break-inside: avoid; }
        th {
            border-top: 1px solid #111827;
            border-bottom: 1px solid #111827;
            padding: 2.5mm 1.8mm;
            color: #374151;
            text-align: left;
            font-size: 7.2pt;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        td {
            border-bottom: 1px solid #e5e7eb;
            padding: 2.6mm 1.8mm;
            vertical-align: top;
        }
        tbody tr:nth-child(even) td { background: #fafafa; }
        .name {
            color: #111827;
            font-size: 9.6pt;
            font-weight: 800;
            word-break: break-word;
        }
        .subline {
            display: block;
            margin-top: .8mm;
            color: #6b7280;
            font-size: 7.5pt;
        }
        .pill {
            display: inline-block;
            border: 1px solid #d1d5db;
            border-radius: 999px;
            padding: 1mm 2mm;
            color: #374151;
            font-size: 7.4pt;
            font-weight: 700;
        }
        .right { text-align: right; }
        .check {
            display: inline-block;
            width: 7mm;
            height: 7mm;
            border: 1.4px solid #9ca3af;
            border-radius: 3px;
        }
        .signature {
            display: block;
            margin-top: 3mm;
            border-bottom: 1px solid #9ca3af;
            height: 5mm;
        }
        .footer {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 5mm;
            margin-top: 8mm;
            padding-top: 3mm;
            border-top: 1px solid #d1d5db;
            color: #6b7280;
            font-size: 7.5pt;
        }
        @media print {
            .toolbar { display: none; }
            body { background: #fff; }
            .sheet { width: auto; min-height: auto; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Drucken</button>
    </div>

    <main class="sheet">
        <header class="topbar">
            <div class="eyebrow">Clubano Teilnehmerdokument</div>
            <h1>Teilnehmerliste</h1>
            <div class="event-title">{{ $event->title }}</div>

            <div class="meta">
                <div class="meta-item">
                    <span class="label">Verein</span>
                    <span class="value">{{ $tenant?->name ?: 'Nicht angegeben' }}</span>
                </div>
                <div class="meta-item">
                    <span class="label">Datum</span>
                    <span class="value">{{ $event->start?->format('d.m.Y H:i') }}</span>
                </div>
                <div class="meta-item">
                    <span class="label">Ort</span>
                    <span class="value">{{ $event->location ?: 'Nicht angegeben' }}</span>
                </div>
                <div class="meta-item">
                    <span class="label">Ausdruck</span>
                    <span class="value">{{ now()->format('d.m.Y H:i') }}</span>
                </div>
            </div>
        </header>

        <section class="stats" aria-label="Zusammenfassung">
            <div class="stat"><span>Teilnehmer</span><strong>{{ $stats['count'] }}</strong></div>
            <div class="stat"><span>Bezahlt</span><strong>{{ $stats['paid'] }}</strong></div>
            <div class="stat"><span>Offen</span><strong>{{ $stats['open'] }}</strong></div>
            <div class="stat"><span>Kostenfrei</span><strong>{{ $stats['free'] }}</strong></div>
            <div class="stat"><span>Summe</span><strong>{{ number_format((float) $stats['total'], 2, ',', '.') }} {{ strtoupper($event->currency ?: 'EUR') }}</strong></div>
        </section>

        <table>
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
                            <span class="name">{{ $participant->display_name ?: 'Ohne Namen' }}</span>
                            <span class="subline">{{ $participant->note ?: ($participant->source ?: 'manual') }}</span>
                        </td>
                        <td><span class="pill">{{ $participant->type_label }}</span></td>
                        <td>
                            {{ $participant->email ?: '—' }}
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
                        <td colspan="6" class="muted">Keine Teilnehmer vorhanden.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <footer class="footer">
            <div>Erzeugt mit Clubano. Zahlungsstatus und Beträge entsprechen dem Stand zum Zeitpunkt des Ausdrucks.</div>
            <div>Seite 1</div>
        </footer>
    </main>
</body>
</html>
