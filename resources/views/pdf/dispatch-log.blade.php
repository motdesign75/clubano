<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Versandprotokoll</title>
    <style>
        @page { margin: 22px; }

        body {
            color: #111827;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.35;
        }

        h1 {
            font-size: 23px;
            margin: 0 0 6px;
        }

        h2 {
            font-size: 13px;
            margin: 18px 0 8px;
        }

        p {
            margin: 0;
        }

        .topline {
            color: #2563eb;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .muted {
            color: #64748b;
        }

        .header {
            border-bottom: 2px solid #bfdbfe;
            padding-bottom: 12px;
        }

        .summary {
            margin-top: 12px;
            width: 100%;
        }

        .summary td {
            border: 1px solid #dbeafe;
            padding: 8px;
            width: 20%;
        }

        .label {
            color: #64748b;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .value {
            font-size: 18px;
            font-weight: bold;
            margin-top: 2px;
        }

        .notice {
            background: #fffbeb;
            border: 1px solid #facc15;
            color: #713f12;
            margin-top: 12px;
            padding: 8px;
        }

        .filters {
            border: 1px solid #e2e8f0;
            margin-top: 12px;
            padding: 8px;
        }

        .filters span {
            display: inline-block;
            margin-right: 14px;
        }

        table.log {
            border-collapse: collapse;
            margin-top: 10px;
            width: 100%;
        }

        table.log th {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #334155;
            font-size: 8px;
            padding: 6px;
            text-align: left;
            text-transform: uppercase;
        }

        table.log td {
            border: 1px solid #e2e8f0;
            padding: 6px;
            vertical-align: top;
        }

        .strong {
            font-weight: bold;
        }

        .small {
            font-size: 8px;
        }

        .pill {
            border: 1px solid #cbd5e1;
            display: inline-block;
            font-size: 8px;
            font-weight: bold;
            padding: 2px 5px;
        }

        .mail {
            background: #dcfce7;
            border-color: #86efac;
            color: #166534;
        }

        .letter {
            background: #fef3c7;
            border-color: #fcd34d;
            color: #92400e;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="topline">Clubano Versandnachweis</div>
        <h1>Versandprotokoll</h1>
        <p class="muted">
            Verein: <span class="strong">{{ $tenant?->name ?? 'Unbekannter Verein' }}</span>
            · Erstellt am {{ $generatedAt->format('d.m.Y H:i') }}
            · Erstellt von {{ $generatedBy?->name ?? 'System' }}
        </p>

        <table class="summary">
            <tr>
                <td>
                    <div class="label">Einträge</div>
                    <div class="value">{{ $stats['total'] }}</div>
                </td>
                <td>
                    <div class="label">Mails</div>
                    <div class="value">{{ $stats['mail'] }}</div>
                </td>
                <td>
                    <div class="label">Briefe</div>
                    <div class="value">{{ $stats['letter'] }}</div>
                </td>
                <td>
                    <div class="label">Geöffnet</div>
                    <div class="value">{{ $stats['opened'] }}</div>
                </td>
                <td>
                    <div class="label">Geklickt</div>
                    <div class="value">{{ $stats['clicked'] }}</div>
                </td>
            </tr>
        </table>

        <div class="notice">
            Hinweis: Öffnungen und Klicks sind technische Trackingwerte. Sie können durch blockierte Bilder,
            Datenschutzfunktionen in Mailprogrammen oder automatische Vorabrufe unvollständig oder verzerrt sein.
        </div>

        <div class="filters">
            <span><strong>Suche:</strong> {{ $filters['search'] !== '' ? $filters['search'] : 'alle' }}</span>
            <span><strong>Kanal:</strong> {{ $filters['channel'] !== '' ? $filters['channel'] : 'alle' }}</span>
            <span><strong>Vorlage:</strong> {{ $filters['template_id'] ?: 'alle' }}</span>
            <span><strong>Von:</strong> {{ $filters['date_from'] ?: 'offen' }}</span>
            <span><strong>Bis:</strong> {{ $filters['date_to'] ?: 'offen' }}</span>
        </div>
    </div>

    <h2>Nachweispositionen</h2>

    <table class="log">
        <thead>
            <tr>
                <th style="width: 10%;">Zeitpunkt</th>
                <th style="width: 8%;">Kanal</th>
                <th style="width: 16%;">Vorlage</th>
                <th style="width: 22%;">Empfänger</th>
                <th style="width: 27%;">Betreff / Aktion</th>
                <th style="width: 17%;">Tracking</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>
                        <div class="strong">{{ optional($log->dispatched_at)->format('d.m.Y') ?: '–' }}</div>
                        <div class="muted small">{{ optional($log->dispatched_at)->format('H:i') ?: '' }}</div>
                    </td>
                    <td>
                        <span class="pill {{ $log->channel === 'mail' ? 'mail' : 'letter' }}">
                            {{ $log->channel === 'mail' ? 'Mail' : 'Brief' }}
                        </span>
                        <div class="muted small">{{ ucfirst($log->recipient_type) }}</div>
                    </td>
                    <td>
                        <div class="strong">{{ $log->template?->name ?? 'Ohne Vorlage' }}</div>
                        <div class="muted small">{{ $log->creator?->name ?? 'System' }}</div>
                    </td>
                    <td>
                        <div class="strong">{{ $log->recipient_name ?: 'Ohne Namen' }}</div>
                        @if($log->recipient_reference)
                            <div class="muted small">{{ $log->recipient_reference }}</div>
                        @endif
                    </td>
                    <td>
                        <div class="strong">{{ $log->subject ?: 'Ohne Betreff' }}</div>
                        <div class="muted small">{{ $log->message_excerpt ?: ($log->channel === 'mail' ? 'Versendet' : 'PDF erzeugt') }}</div>
                    </td>
                    <td>
                        @if($log->channel === 'mail')
                            <div>{{ $log->open_count > 0 ? $log->open_count . 'x geöffnet' : 'nicht geöffnet' }}</div>
                            <div>{{ $log->click_count > 0 ? $log->click_count . 'x geklickt' : 'kein Klick' }}</div>
                            @if($log->last_opened_at)
                                <div class="muted small">Letzte Öffnung: {{ $log->last_opened_at->format('d.m.Y H:i') }}</div>
                            @endif
                            @if($log->last_clicked_at)
                                <div class="muted small">Letzter Klick: {{ $log->last_clicked_at->format('d.m.Y H:i') }}</div>
                            @endif
                        @else
                            <span class="muted">kein Mailtracking</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">Keine Versand- oder Druckvorgänge für diese Auswahl gefunden.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
