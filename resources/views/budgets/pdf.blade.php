<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>{{ $budget->title }}</title>
    <style>
        @page { margin: 16mm 14mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #0f172a; line-height: 1.45; }
        .header-table, .summary-table, .position-table, .footer-table { width: 100%; border-collapse: collapse; }
        .header-table td, .footer-table td { vertical-align: top; }
        .brand { font-size: 18pt; font-weight: bold; }
        .brand-meta { margin-top: 3mm; font-size: 9pt; color: #475569; }
        .title { margin: 8mm 0 3mm; font-size: 20pt; font-weight: bold; }
        .subtitle { font-size: 9pt; color: #64748b; margin-bottom: 7mm; }
        .summary-grid { margin-top: 5mm; }
        .summary-box {
            width: 31.5%;
            display: inline-block;
            vertical-align: top;
            margin-right: 2.3%;
            margin-bottom: 4mm;
            border: 1px solid #dbe4ff;
            border-radius: 12px;
            padding: 10px 11px;
            box-sizing: border-box;
            background: #f8fafc;
        }
        .summary-box:nth-child(3n) { margin-right: 0; }
        .label { font-size: 7.6pt; font-weight: bold; letter-spacing: 0.16em; text-transform: uppercase; color: #64748b; }
        .value { margin-top: 2mm; font-size: 15pt; font-weight: bold; }
        .section-title { margin: 9mm 0 3mm; font-size: 13pt; font-weight: bold; }
        .section-copy { margin: 0 0 4mm; font-size: 8.7pt; color: #475569; }
        .position-table { border: 1px solid #dbe4ff; border-radius: 12px; overflow: hidden; }
        .position-table th { padding: 8px 9px; text-align: left; background: #eef2ff; color: #334155; font-size: 7.8pt; font-weight: bold; letter-spacing: 0.12em; text-transform: uppercase; border-bottom: 1px solid #dbe4ff; }
        .position-table td { padding: 8px 9px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .position-table tr:last-child td { border-bottom: none; }
        .right { text-align: right; }
        .note { margin-top: 1.5mm; font-size: 8.3pt; color: #475569; white-space: pre-line; }
        .footer { margin-top: 10mm; border-top: 1px solid #cbd5e1; padding-top: 3mm; font-size: 8pt; color: #475569; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 68%;">
                <div class="brand">{{ $tenant->name }}</div>
                <div class="brand-meta">
                    {{ $tenant->address }}<br>
                    {{ $tenant->zip }} {{ $tenant->city }}
                    @if($tenant->email)<br>{{ $tenant->email }}@endif
                </div>
            </td>
            <td style="width: 32%; text-align: right;">
                <div class="label">Stand</div>
                <div style="margin-top: 2mm; font-size: 10pt;">{{ now()->format('d.m.Y H:i') }}</div>
                <div style="margin-top: 4mm;" class="label">Status</div>
                <div style="margin-top: 2mm; font-size: 10pt;">{{ $budget->isReleased() ? 'Freigegeben' : 'Entwurf' }}</div>
            </td>
        </tr>
    </table>

    <div class="title">{{ $budget->title }}</div>
    <div class="subtitle">
        Haushaltsplan fuer das Jahr {{ $budget->year }} mit direktem Vergleich zu den abgeschlossenen Buchungen in Clubano.
    </div>

    @if($budget->notes)
        <div style="margin-bottom: 6mm; font-size: 9pt; color: #334155; white-space: pre-line;">{{ $budget->notes }}</div>
    @endif

    <div class="summary-grid">
        <div class="summary-box">
            <div class="label">Plan Einnahmen</div>
            <div class="value">{{ number_format($summary['planned_income'], 2, ',', '.') }} €</div>
        </div>
        <div class="summary-box">
            <div class="label">Plan Ausgaben</div>
            <div class="value">{{ number_format($summary['planned_expense'], 2, ',', '.') }} €</div>
        </div>
        <div class="summary-box">
            <div class="label">Plan Ergebnis</div>
            <div class="value">{{ number_format($summary['planned_result'], 2, ',', '.') }} €</div>
        </div>
        <div class="summary-box">
            <div class="label">Ist Einnahmen</div>
            <div class="value">{{ number_format($summary['actual_income'], 2, ',', '.') }} €</div>
        </div>
        <div class="summary-box">
            <div class="label">Ist Ausgaben</div>
            <div class="value">{{ number_format($summary['actual_expense'], 2, ',', '.') }} €</div>
        </div>
        <div class="summary-box">
            <div class="label">Abweichung Ergebnis</div>
            <div class="value">{{ number_format($summary['variance_result'], 2, ',', '.') }} €</div>
        </div>
    </div>

    @foreach (['income' => 'Einnahmen', 'expense' => 'Ausgaben'] as $type => $label)
        <div class="section-title">{{ $label }}</div>
        <p class="section-copy">
            {{ $type === 'income' ? 'Geplante Mittelzufluesse und ihr aktueller Stand im Jahr ' . $budget->year . '.' : 'Geplante Mittelabfluesse und ihre aktuelle Beanspruchung im Jahr ' . $budget->year . '.' }}
        </p>

        <table class="position-table">
            <thead>
                <tr>
                    <th style="width: 33%;">Konto</th>
                    <th style="width: 14%;">Nr.</th>
                    <th style="width: 16%;">Rhythmus</th>
                    <th style="width: 16%;" class="right">Plan</th>
                    <th style="width: 16%;" class="right">Ist</th>
                    <th style="width: 15%;" class="right">Abweichung</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items->where('type', $type) as $item)
                    <tr>
                        <td>
                            <strong>{{ $item['account']->name }}</strong>
                            @if($item['notes'])
                                <div class="note">{{ $item['notes'] }}</div>
                            @endif
                        </td>
                        <td>{{ $item['account']->number ?: '—' }}</td>
                        <td>{{ number_format($item['period_amount'], 2, ',', '.') }} € {{ $item['planning_cycle_label'] }}</td>
                        <td class="right">{{ number_format($item['planned_amount'], 2, ',', '.') }} €</td>
                        <td class="right">{{ number_format($item['actual_amount'], 2, ',', '.') }} €</td>
                        <td class="right">{{ number_format($item['variance'], 2, ',', '.') }} €</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">Noch keine Positionen vorhanden.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endforeach

    <div class="footer">
        <table class="footer-table">
            <tr>
                <td style="width: 55%;">
                    {{ $tenant->name }}<br>
                    Haushaltsplan {{ $budget->year }}
                </td>
                <td style="width: 45%; text-align: right;">
                    Erstellt mit Clubano am {{ now()->format('d.m.Y') }}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
