<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Versandprotokoll</title>
    <style>
        @page {
            margin: 24px 24px 30px;
        }

        body {
            color: #0f172a;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.45;
        }

        h1, h2, p {
            margin: 0;
        }

        .eyebrow {
            color: #2563eb;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .header {
            border-bottom: 2px solid #dbeafe;
            padding-bottom: 16px;
        }

        .title {
            font-size: 24px;
            font-weight: 700;
            margin-top: 8px;
        }

        .muted {
            color: #64748b;
        }

        .meta {
            margin-top: 10px;
        }

        .stats {
            margin-top: 16px;
            width: 100%;
        }

        .stat {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px;
            width: 19%;
        }

        .stat-label {
            color: #64748b;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .stat-value {
            font-size: 20px;
            font-weight: 700;
            margin-top: 5px;
        }

        .notice {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            color: #78350f;
            margin-top: 14px;
            padding: 10px 12px;
        }

        .filters {
            margin-top: 14px;
        }

        .filter-pill {
            background: #eff6ff;
            border-radius: 999px;
            color: #1d4ed8;
            display: inline-block;
            font-size: 9px;
            font-weight: 700;
            margin-right: 6px;
            padding: 5px 9px;
        }

        table {
            border-collapse: collapse;
            margin-top: 16px;
            width: 100%;
        }

        th {
            background: #f1f5f9;
            border-bottom: 1px solid #cbd5e1;
            color: #475569;
            font-size: 8px;
            letter-spacing: 1px;
            padding: 8px 6px;
            text-align: left;
            text-transform: uppercase;
        }

        td {
            border-bottom: 1px solid #e2e8f0;
            padding: 8px 6px;
            vertical-align: top;
        }

        .strong {
            font-weight: 700;
        }

        .badge {
            border-radius: 999px;
            display: inline-block;
            font-size: 8px;
            font-weight: 700;
            padding: 3px 7px;
        }

        .badge-mail {
            background: #dcfce7;
            color: #166534;
        }

        .badge-letter {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-neutral {
            background: #f1f5f9;
            color: #475569;
        }

        .footer {
            bottom: -18px;
            color: #94a3b8;
            font-size: 8px;
            left: 0;
            position: fixed;
            right: 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="eyebrow">Clubano Versandnachweis</div>
        <h1 class="title">Versandprotokoll</h1>
        <p class="meta muted">
            Verein: <span class="strong">{{ $tenant?->name ?? 'Unbekannter Verein' }}</span>
            · Erstellt am {{ $generatedAt->format('d.m.Y H:i') }}
            · Erstellt von {{ $generatedBy?->name ?? 'System' }}
        </p>

        <table class="stats">
            <tr>
                <td class="stat">
                    <div class="stat-label">Einträge</div>
                    <div class="stat-value">{{ $stats['total'] }}</div>
                </td>
                <td class="stat">
                    <div class="stat-label">Mails</div>
                    <div class="stat-value">{{ $stats['mail'] }}</div>
                </td>
                <td class="stat">
                    <div class="stat-label">Briefe</div>
                    <div class="stat-value">{{ $stats['letter'] }}</div>
                </td>
                <td class="stat">
                    <div class="stat-label">Geöffnet</div>
                    <div class="stat-value">{{ $stats['opened'] }}</div>
                </td>
                <td class="stat">
                    <div class="stat-label">Geklickt</div>
                    <div class="stat-value">{{ $stats['clicked'] }}</div>
                </td>
            </tr>
        </table>

        <div class="notice">
            Hinweis: Öffnungen und Klicks sind technische Trackingwerte. Sie können durch blockierte Bilder, Datenschutzfunktionen in Mailprogrammen oder automatische Vorabrufe unvollständig oder verzerrt sein.
        </div>

        <div class="filters">
            <span class="filter-pill">Suche: {{ $filters['search'] !== '' ? $filters['search'] : 'alle' }}</span>
            <span class="filter-pill">Kanal: {{ $filters['channel'] !== '' ? $filters['channel'] : 'alle' }}</span>
            <span class="filter-pill">Vorlage: {{ $filters['template_id'] ?: 'alle' }}</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 11%;">Zeitpunkt</th>
                <th style="width: 8%;">Kanal</th>
                <th style="width: 17%;">Vorlage</th>
                <th style="width: 23%;">Empfänger</th>
                <th style="width: 23%;">Betreff / Aktion</th>
                <th style="width: 18%;">Tracking</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>
                        <div class="strong">{{ optional($log->dispatched_at)->format('d.m.Y') ?: '–' }}</div>
                        <div class="muted">{{ optional($log->dispatched_at)->format('H:i') ?: '' }}</div>
                    </td>
                    <td>
                        <span class="badge {{ $log->channel === 'mail' ? 'badge-mail' : 'badge-letter' }}">
                            {{ $log->channel === 'mail' ? 'Mail' : 'Brief' }}
                        </span>
                        <div class="muted" style="margin-top: 4px;">{{ ucfirst($log->recipient_type) }}</div>
                    </td>
                    <td>
                        <div class="strong">{{ $log->template?->name ?? 'Ohne Vorlage' }}</div>
                        <div class="muted">{{ $log->creator?->name ?? 'System' }}</div>
                    </td>
                    <td>
                        <div class="strong">{{ $log->recipient_name ?: 'Ohne Namen' }}</div>
                        @if($log->recipient_reference)
                            <div class="muted">{{ $log->recipient_reference }}</div>
                        @endif
                    </td>
                    <td>
                        <div class="strong">{{ $log->subject ?: 'Ohne Betreff' }}</div>
                        <div class="muted">{{ $log->message_excerpt ?: ($log->channel === 'mail' ? 'Versendet' : 'PDF erzeugt') }}</div>
                    </td>
                    <td>
                        @if($log->channel === 'mail')
                            <div>
                                <span class="badge {{ $log->open_count > 0 ? 'badge-mail' : 'badge-neutral' }}">
                                    {{ $log->open_count > 0 ? $log->open_count . 'x geöffnet' : 'nicht geöffnet' }}
                                </span>
                            </div>
                            <div style="margin-top: 4px;">
                                <span class="badge {{ $log->click_count > 0 ? 'badge-mail' : 'badge-neutral' }}">
                                    {{ $log->click_count > 0 ? $log->click_count . 'x geklickt' : 'kein Klick' }}
                                </span>
                            </div>
                            @if($log->last_opened_at)
                                <div class="muted" style="margin-top: 4px;">Letzte Öffnung: {{ $log->last_opened_at->format('d.m.Y H:i') }}</div>
                            @endif
                            @if($log->last_clicked_at)
                                <div class="muted">Letzter Klick: {{ $log->last_clicked_at->format('d.m.Y H:i') }}</div>
                            @endif
                        @else
                            <span class="badge badge-neutral">kein Mailtracking</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">Keine Versand- oder Druckvorgänge für diese Filter gefunden.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Clubano Versandprotokoll · {{ $tenant?->name ?? 'Verein' }} · {{ $generatedAt->format('d.m.Y H:i') }}
    </div>
</body>
</html>
