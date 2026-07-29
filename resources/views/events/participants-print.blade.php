<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Teilnehmerliste {{ $event->title }}</title>
    <style>
        @page { margin: 14mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #0f172a; font-size: 10pt; line-height: 1.35; }
        .toolbar { margin-bottom: 8mm; }
        .toolbar button { border: 0; border-radius: 8px; background: #0f172a; color: white; padding: 9px 14px; font-weight: 700; cursor: pointer; }
        h1 { margin: 0; font-size: 20pt; }
        .muted { color: #64748b; }
        .meta { margin-top: 3mm; display: table; width: 100%; }
        .meta div { display: table-cell; width: 25%; padding-right: 4mm; }
        .stats { margin-top: 7mm; display: table; width: 100%; border-collapse: separate; border-spacing: 3mm 0; }
        .stat { display: table-cell; border: 1px solid #cbd5e1; border-radius: 8px; padding: 3mm; }
        .stat strong { display: block; margin-top: 1mm; font-size: 13pt; }
        table { margin-top: 8mm; width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; color: #475569; text-align: left; font-size: 8pt; text-transform: uppercase; letter-spacing: .08em; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 2.4mm 2mm; vertical-align: top; }
        .right { text-align: right; }
        .check { width: 10mm; height: 5mm; border: 1px solid #94a3b8; border-radius: 3px; display: inline-block; }
        .footer { margin-top: 12mm; color: #64748b; font-size: 8.5pt; }
        @media print {
            .toolbar { display: none; }
            body { font-size: 9.5pt; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Drucken</button>
    </div>

    <h1>Teilnehmerliste</h1>
    <p class="muted">{{ $event->title }}</p>

    <div class="meta">
        <div><strong>Verein</strong><br>{{ $tenant?->name }}</div>
        <div><strong>Datum</strong><br>{{ $event->start?->format('d.m.Y H:i') }}</div>
        <div><strong>Ort</strong><br>{{ $event->location ?: 'Nicht angegeben' }}</div>
        <div><strong>Ausdruck</strong><br>{{ now()->format('d.m.Y H:i') }}</div>
    </div>

    <div class="stats">
        <div class="stat">Teilnehmer<strong>{{ $stats['count'] }}</strong></div>
        <div class="stat">Bezahlt<strong>{{ $stats['paid'] }}</strong></div>
        <div class="stat">Offen<strong>{{ $stats['open'] }}</strong></div>
        <div class="stat">Kostenfrei<strong>{{ $stats['free'] }}</strong></div>
        <div class="stat">Summe<strong>{{ number_format((float) $stats['total'], 2, ',', '.') }} {{ strtoupper($event->currency ?: 'EUR') }}</strong></div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 27%;">Name</th>
                <th style="width: 12%;">Typ</th>
                <th style="width: 16%;">Kontakt</th>
                <th style="width: 13%;">Zahlung</th>
                <th style="width: 10%;" class="right">Betrag</th>
                <th style="width: 14%;">Notiz</th>
                <th style="width: 8%;">Anwesend</th>
            </tr>
        </thead>
        <tbody>
            @forelse($participants as $row)
                @php($participant = $row['participant'])
                <tr>
                    <td><strong>{{ $participant->display_name ?: 'Ohne Namen' }}</strong><br><span class="muted">{{ $participant->source ?: 'manual' }}</span></td>
                    <td>{{ $participant->type_label }}</td>
                    <td>
                        {{ $participant->email ?: '—' }}
                        @if($participant->phone)
                            <br>{{ $participant->phone }}
                        @endif
                    </td>
                    <td>{{ $participant->payment_status_label }}@if($participant->payment_reason)<br><span class="muted">{{ $participant->payment_reason }}</span>@endif</td>
                    <td class="right">{{ number_format((float) $participant->price_amount, 2, ',', '.') }}</td>
                    <td>{{ $participant->note ?: '—' }}</td>
                    <td><span class="check"></span></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">Keine Teilnehmer vorhanden.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Diese Liste wurde aus Clubano erzeugt. Zahlungsstatus und Beträge entsprechen dem Stand zum Zeitpunkt des Ausdrucks.
    </div>
</body>
</html>
