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
        .category-card { border: 1px solid #dbe4ff; border-radius: 12px; padding: 8px 10px; margin-bottom: 3mm; background: #f8fafc; }
        .category-title { font-size: 11pt; font-weight: bold; }
        .category-grid { margin-top: 3mm; }
        .category-metric { width: 31%; display: inline-block; vertical-align: top; margin-right: 2%; font-size: 8.4pt; }
        .category-metric:nth-child(3n) { margin-right: 0; }
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

    <div class="section-title">Ergebnis nach Haushaltsbereich</div>
    <p class="section-copy">
        Bereiche zeigen, welche Teile des Vereins einen Ueberschuss erwirtschaften und wo ein Defizit entsteht.
    </p>
    @forelse($categorySummaries as $group)
        @php($categorySummary = $group['summary'])
        <div class="category-card">
            <div class="category-title">{{ $group['name'] }}</div>
            <div style="margin-top: 1mm; font-size: 8.2pt; color: #64748b;">{{ $group['items']->count() }} Positionen und Ist-Werte</div>
            <div class="category-grid">
                <div class="category-metric">
                    <div class="label">Plan Ergebnis</div>
                    <strong>{{ number_format($categorySummary['planned_result'], 2, ',', '.') }} €</strong>
                </div>
                <div class="category-metric">
                    <div class="label">Ist Ergebnis</div>
                    <strong>{{ number_format($categorySummary['actual_result'], 2, ',', '.') }} €</strong>
                </div>
                <div class="category-metric">
                    <div class="label">Abweichung</div>
                    <strong>{{ number_format($categorySummary['variance_result'], 2, ',', '.') }} €</strong>
                </div>
            </div>
        </div>
    @empty
        <p class="section-copy">Noch keine Bereiche auswertbar.</p>
    @endforelse

    @foreach (['income' => 'Einnahmen', 'expense' => 'Ausgaben'] as $type => $label)
        <div class="section-title">{{ $label }}</div>
        <p class="section-copy">
            {{ $type === 'income' ? 'Geplante Mittelzufluesse und ihr aktueller Stand im Jahr ' . $budget->year . '.' : 'Geplante Mittelabfluesse und ihre aktuelle Beanspruchung im Jahr ' . $budget->year . '.' }}
        </p>

        <table class="position-table">
            <thead>
                <tr>
                    <th style="width: 28%;">Konto</th>
                    <th style="width: 16%;">Bereich</th>
                    <th style="width: 14%;">Nr.</th>
                    <th style="width: 14%;">Rhythmus</th>
                    <th style="width: 14%;" class="right">Plan</th>
                    <th style="width: 14%;" class="right">Ist</th>
                    <th style="width: 14%;" class="right">Abweichung</th>
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
                        <td>
                            {{ $item['category_name'] }}
                            @if($item['is_unplanned_actual'])
                                <div class="note">Nicht geplant</div>
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
                        <td colspan="7">Noch keine Positionen vorhanden.</td>
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
