<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>{{ $budget->title }}</title>
    <style>
        @page { margin: 11mm 10mm 12mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.6pt;
            color: #111827;
            line-height: 1.25;
        }
        .title {
            text-align: center;
            color: #263f8f;
            font-size: 20pt;
            font-weight: 800;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            margin: 0 0 1mm;
        }
        .year {
            text-align: center;
            font-size: 12pt;
            font-weight: 800;
            margin-bottom: 4mm;
        }
        .meta-table,
        .budget-table,
        .overview-table,
        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td {
            border: 1px solid #111827;
            padding: 5px 7px;
            height: 22px;
            vertical-align: middle;
        }
        .meta-label {
            width: 13%;
            font-weight: 800;
            background: #f8fafc;
        }
        .meta-value {
            background: #e6f1f7;
        }
        .section-bar {
            margin-top: 7mm;
            padding: 8px 0;
            color: #fff;
            font-weight: 800;
            text-align: center;
            text-transform: uppercase;
            font-size: 12pt;
        }
        .income-bar { background: #16b981; }
        .expense-bar { background: #ef3f3f; }
        .blue { background: #263f8f; color: #fff; }
        .green { background: #16b981; color: #fff; }
        .red { background: #ef3f3f; color: #fff; }
        .budget-table th,
        .budget-table td,
        .overview-table th,
        .overview-table td {
            border: 1px solid #111827;
            padding: 4px 5px;
        }
        .budget-table th,
        .overview-table th {
            background: #263f8f;
            color: #fff;
            font-weight: 800;
            text-align: center;
        }
        .budget-table tbody tr:nth-child(even) td,
        .overview-table tbody tr:nth-child(even) td {
            background: #f1f2f4;
        }
        .nr { width: 5%; text-align: center; }
        .label-col { width: 31%; }
        .amount { text-align: right; white-space: nowrap; }
        .category {
            margin-top: 1px;
            font-size: 7.2pt;
            color: #475569;
        }
        .sum-row td {
            font-weight: 800;
            color: #fff;
        }
        .result-row td {
            font-weight: 800;
            color: #fff;
        }
        .small-note {
            margin-top: 3mm;
            color: #475569;
            font-size: 7.4pt;
        }
        .page-break { page-break-before: always; }
        .overview-title {
            color: #263f8f;
            font-size: 18pt;
            font-weight: 800;
            text-align: center;
            text-transform: uppercase;
            margin-bottom: 5mm;
        }
        .overview-wrap {
            width: 58%;
        }
        .chart-row {
            margin-top: 8mm;
        }
        .chart-box {
            display: inline-block;
            vertical-align: top;
            width: 48%;
            min-height: 72mm;
            border: 1px solid #94a3b8;
            padding: 8px 10px;
            box-sizing: border-box;
        }
        .chart-box + .chart-box {
            margin-left: 2%;
        }
        .chart-title {
            text-align: center;
            font-size: 14pt;
            font-weight: 800;
            margin-bottom: 7mm;
        }
        .bar-line {
            margin-bottom: 5px;
            font-size: 7.4pt;
        }
        .bar-label {
            display: inline-block;
            width: 22mm;
        }
        .bar-track {
            display: inline-block;
            width: 82mm;
            height: 6px;
            background: #e5e7eb;
            vertical-align: middle;
        }
        .bar-fill-income { height: 6px; background: #16b981; }
        .bar-fill-expense { height: 6px; background: #ef3f3f; }
        .signature-table {
            margin-top: 8mm;
        }
        .signature-table td {
            width: 33.3%;
            padding-top: 13mm;
            border-bottom: 1px solid #111827;
        }
        .footer {
            margin-top: 5mm;
            font-size: 7pt;
            color: #475569;
            text-align: right;
        }
    </style>
</head>
<body>
@php
    $money = fn ($value) => number_format((float) $value, 2, ',', '.') . ' €';
    $maxMonthly = max(1, collect($pdfData['monthly'])->max(fn ($row) => max(abs($row['income']), abs($row['expense']), abs($row['saldo']))));
@endphp

<div class="title">Haushaltsplan fuer Vereine</div>
<div class="year">Geschaeftsjahr: {{ $budget->year }}</div>

<table class="meta-table">
    <tr>
        <td class="meta-label">Verein</td>
        <td class="meta-value" colspan="3">{{ $tenant->name }}</td>
        <td class="meta-label">Erstellt am</td>
        <td class="meta-value amount">{{ now()->format('d.m.Y') }}</td>
    </tr>
    <tr>
        <td class="meta-label">Status</td>
        <td class="meta-value">{{ $budget->isReleased() ? 'Freigegeben' : 'Entwurf' }}</td>
        <td class="meta-label">Titel</td>
        <td class="meta-value" colspan="3">{{ $budget->title }}</td>
    </tr>
</table>

@if($budget->notes)
    <div class="small-note">{{ $budget->notes }}</div>
@endif

<div class="section-bar income-bar">Einnahmen</div>
<table class="budget-table">
    <thead>
        <tr>
            <th class="nr">Nr.</th>
            <th class="label-col">Einnahmeart</th>
            <th>Q1 (Jan-Maerz)</th>
            <th>Q2 (Apr-Jun)</th>
            <th>Q3 (Jul-Sep)</th>
            <th>Q4 (Okt-Dez)</th>
            <th>Gesamt</th>
        </tr>
    </thead>
    <tbody>
        @forelse($pdfData['incomeRows'] as $row)
            <tr>
                <td class="nr">{{ $row['number'] }}</td>
                <td>
                    <strong>{{ $row['label'] }}</strong>
                    <div class="category">{{ $row['category'] }}</div>
                </td>
                @foreach($row['quarters'] as $amount)
                    <td class="amount">{{ $money($amount) }}</td>
                @endforeach
                <td class="amount"><strong>{{ $money($row['total']) }}</strong></td>
            </tr>
        @empty
            <tr><td colspan="7">Noch keine Einnahmen geplant.</td></tr>
        @endforelse
        <tr class="sum-row">
            <td class="green" colspan="2">Summe Einnahmen</td>
            @foreach($pdfData['incomeTotals']['quarters'] as $amount)
                <td class="green amount">{{ $money($amount) }}</td>
            @endforeach
            <td class="green amount">{{ $money($pdfData['incomeTotals']['total']) }}</td>
        </tr>
    </tbody>
</table>

<div class="section-bar expense-bar">Ausgaben</div>
<table class="budget-table">
    <thead>
        <tr>
            <th class="nr">Nr.</th>
            <th class="label-col">Ausgabeart</th>
            <th>Q1 (Jan-Maerz)</th>
            <th>Q2 (Apr-Jun)</th>
            <th>Q3 (Jul-Sep)</th>
            <th>Q4 (Okt-Dez)</th>
            <th>Gesamt</th>
        </tr>
    </thead>
    <tbody>
        @forelse($pdfData['expenseRows'] as $row)
            <tr>
                <td class="nr">{{ $row['number'] }}</td>
                <td>
                    <strong>{{ $row['label'] }}</strong>
                    <div class="category">{{ $row['category'] }}</div>
                </td>
                @foreach($row['quarters'] as $amount)
                    <td class="amount">{{ $money($amount) }}</td>
                @endforeach
                <td class="amount"><strong>{{ $money($row['total']) }}</strong></td>
            </tr>
        @empty
            <tr><td colspan="7">Noch keine Ausgaben geplant.</td></tr>
        @endforelse
        <tr class="sum-row">
            <td class="red" colspan="2">Summe Ausgaben</td>
            @foreach($pdfData['expenseTotals']['quarters'] as $amount)
                <td class="red amount">{{ $money($amount) }}</td>
            @endforeach
            <td class="red amount">{{ $money($pdfData['expenseTotals']['total']) }}</td>
        </tr>
        <tr class="result-row">
            <td class="blue" colspan="2">Jahresergebnis</td>
            @foreach($pdfData['quarterResult'] as $amount)
                <td class="green amount">{{ $money($amount) }}</td>
            @endforeach
            <td class="green amount">{{ $money($pdfData['annualResult']) }}</td>
        </tr>
    </tbody>
</table>

<div class="small-note">
    Hinweis: Da Planpositionen in Clubano noch keinen konkreten Faelligkeitsmonat speichern, werden Jahreswerte fuer die Quartals- und Monatsuebersicht anteilig verteilt.
</div>

<div class="page-break"></div>

<div class="overview-title">Jahresuebersicht</div>
<div class="overview-wrap">
    <table class="overview-table">
        <thead>
            <tr>
                <th>Monat</th>
                <th>Einnahmen</th>
                <th>Ausgaben</th>
                <th>Saldo</th>
                <th>Kumuliert</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pdfData['monthly'] as $row)
                <tr>
                    <td><strong>{{ $row['month'] }}</strong></td>
                    <td class="amount">{{ $money($row['income']) }}</td>
                    <td class="amount">{{ $money($row['expense']) }}</td>
                    <td class="amount">{{ $money($row['saldo']) }}</td>
                    <td class="amount"><strong>{{ $money($row['cumulative']) }}</strong></td>
                </tr>
            @endforeach
            <tr class="sum-row">
                <td class="blue">Gesamt</td>
                <td class="blue amount">{{ $money($pdfData['incomeTotals']['total']) }}</td>
                <td class="blue amount">{{ $money($pdfData['expenseTotals']['total']) }}</td>
                <td class="blue amount">{{ $money($pdfData['annualResult']) }}</td>
                <td class="blue amount">{{ $money($pdfData['annualResult']) }}</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="chart-row">
    <div class="chart-box">
        <div class="chart-title">Finanzentwicklung im Jahresverlauf</div>
        @foreach($pdfData['monthly'] as $row)
            @php
                $incomeWidth = min(100, abs($row['income']) / $maxMonthly * 100);
                $expenseWidth = min(100, abs($row['expense']) / $maxMonthly * 100);
            @endphp
            <div class="bar-line">
                <span class="bar-label">{{ mb_substr($row['month'], 0, 3) }}</span>
                <span class="bar-track"><span class="bar-fill-income" style="display:block;width: {{ $incomeWidth }}%;"></span></span>
            </div>
            <div class="bar-line">
                <span class="bar-label"></span>
                <span class="bar-track"><span class="bar-fill-expense" style="display:block;width: {{ $expenseWidth }}%;"></span></span>
            </div>
        @endforeach
        <div class="small-note">Gruen: Einnahmen, Rot: Ausgaben</div>
    </div>
    <div class="chart-box">
        <div class="chart-title">Verteilung Einnahmen vs. Ausgaben</div>
        @php
            $totalVolume = max(1, $pdfData['incomeTotals']['total'] + $pdfData['expenseTotals']['total']);
            $incomeShare = $pdfData['incomeTotals']['total'] / $totalVolume * 100;
            $expenseShare = $pdfData['expenseTotals']['total'] / $totalVolume * 100;
        @endphp
        <div style="margin-top: 18mm;">
            <div style="font-size: 12pt; font-weight: 800;">Einnahmen</div>
            <div class="bar-track" style="width: 100%; height: 12px;"><span class="bar-fill-income" style="display:block;width: {{ $incomeShare }}%; height: 12px;"></span></div>
            <div class="amount">{{ $money($pdfData['incomeTotals']['total']) }} ({{ number_format($incomeShare, 1, ',', '.') }} %)</div>
        </div>
        <div style="margin-top: 12mm;">
            <div style="font-size: 12pt; font-weight: 800;">Ausgaben</div>
            <div class="bar-track" style="width: 100%; height: 12px;"><span class="bar-fill-expense" style="display:block;width: {{ $expenseShare }}%; height: 12px;"></span></div>
            <div class="amount">{{ $money($pdfData['expenseTotals']['total']) }} ({{ number_format($expenseShare, 1, ',', '.') }} %)</div>
        </div>
    </div>
</div>

<table class="signature-table">
    <tr>
        <td>Ort, Datum</td>
        <td>Vorstand</td>
        <td>Kasse</td>
    </tr>
</table>

<div class="footer">
    Erstellt mit Clubano am {{ now()->format('d.m.Y H:i') }}
</div>
</body>
</html>
