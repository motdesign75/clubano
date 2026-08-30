<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Kassenbuch</title>

<style>
@page {
    size: A4 landscape;
    margin: 8mm 8mm 10mm;
}

body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 7.5pt;
    color: #111827;
    line-height: 1.25;
    margin: 0;
}

.toolbar {
    margin-bottom: 14px;
}

.toolbar button,
.toolbar a {
    display: inline-block;
    margin-right: 8px;
    padding: 6px 10px;
    border-radius: 6px;
    text-decoration: none;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #0f172a;
    font-size: 9pt;
}

.toolbar a.primary {
    background: #0f172a;
    border-color: #0f172a;
    color: #fff;
}

.header {
    width: 100%;
    margin-bottom: 8px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    border-collapse: separate;
    border-spacing: 0;
    overflow: hidden;
}

.header td {
    vertical-align: top;
    padding: 8px 10px;
    background: #0f172a;
    color: #fff;
}

.header .right {
    text-align: right;
    font-size: 7.2pt;
    color: #e2e8f0;
}

.header .muted {
    color: #cbd5e1;
}

.summary {
    margin: 0 0 8px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    overflow: hidden;
}

.summary table {
    width: 100%;
    border-collapse: collapse;
}

.summary td {
    border-right: 1px solid #e2e8f0;
    padding: 5px 8px;
}

.summary td:last-child {
    border-right: none;
}

.summary .label {
    font-size: 6.2pt;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #475569;
}

.summary .value {
    margin-top: 2px;
    font-size: 9.5pt;
    font-weight: bold;
}

table.entries {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

table.entries th,
table.entries td {
    border: 1px solid #cbd5e1;
    padding: 3px 4px;
    vertical-align: top;
    overflow-wrap: anywhere;
}

table.entries th {
    background: #f8fafc;
    font-size: 5.8pt;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.text-right {
    text-align: right;
}

.text-center {
    text-align: center;
}

.muted {
    color: #64748b;
    font-size: 6.5pt;
}

.positive {
    color: #047857;
}

.negative {
    color: #b91c1c;
}

.col-number {
    width: 3%;
}

.col-date {
    width: 7%;
}

.col-type {
    width: 10%;
}

.col-status {
    width: 8%;
}

.col-description {
    width: 22%;
}

.col-account {
    width: 12%;
}

.col-receipt {
    width: 9%;
}

.col-money {
    width: 7%;
}

.col-balance {
    width: 8%;
}

.col-user {
    width: 7%;
}

@media print {
    .toolbar {
        display: none;
    }
}
</style>
</head>
<body>
@php
    $year = $selectedYear ?? now()->year;
    $month = $selectedMonth ?? null;
    $missingReceiptCount = collect($transactions)->filter(fn ($transaction) => !$transaction->hasAnyReceipt())->count();
    $movementLabels = [
        'all' => 'Alle Bewegungen',
        'income' => 'Nur Einnahmen',
        'expense' => 'Nur Ausgaben',
        'transfer' => 'Nur Umbuchungen',
    ];
@endphp

<div class="toolbar">
    <button onclick="window.print()">Drucken</button>
    <a href="{{ route('transactions.cashbook.pdf', request()->query()) }}" class="primary">PDF herunterladen</a>
</div>

<table class="header">
    <tr>
        <td>
            <strong>{{ auth()->user()->tenant->name ?? 'Verein' }}</strong><br>
            <span class="muted">Kassenbuch – {{ $selectedCashAccount?->name ?? 'Kasse' }}</span>
        </td>
        <td class="right">
            Erstellt am {{ now()->format('d.m.Y H:i') }}<br>
            Zeitraum:
            @if($month)
                {{ \Carbon\Carbon::createFromDate($year, $month, 1)->translatedFormat('F Y') }}
            @else
                Jahr {{ $year }}
            @endif
            <br>
            Filter: {{ $movementLabels[$movement] ?? 'Alle Bewegungen' }}<br>
            Ohne Beleg: {{ $missingReceiptCount }}
        </td>
    </tr>
</table>

<div class="summary">
    <table>
        <tr>
            <td>
                <div class="label">Anfangsbestand</div>
                <div class="value">{{ number_format($openingBalance, 2, ',', '.') }} €</div>
            </td>
            <td>
                <div class="label">Einnahmen</div>
                <div class="value positive">{{ number_format($periodIncome, 2, ',', '.') }} €</div>
            </td>
            <td>
                <div class="label">Ausgaben</div>
                <div class="value negative">{{ number_format($periodExpense, 2, ',', '.') }} €</div>
            </td>
            <td>
                <div class="label">Bestand Ende Zeitraum</div>
                <div class="value">{{ number_format($closingBalance, 2, ',', '.') }} €</div>
            </td>
        </tr>
    </table>
</div>

<table class="entries">
    <thead>
        <tr>
            <th class="text-center col-number">Nr.</th>
            <th class="col-date">Datum</th>
            <th class="col-type">Vorgang</th>
            <th class="col-status">Status</th>
            <th class="col-description">Beschreibung</th>
            <th class="col-account">Gegenkonto</th>
            <th class="col-receipt">Beleg-Nr.</th>
            <th class="text-right col-money">Einnahme</th>
            <th class="text-right col-money">Ausgabe</th>
            <th class="text-right col-balance">Bestand</th>
            <th class="col-user">Erfasst von</th>
        </tr>
    </thead>
    <tbody>
        @forelse($transactions as $index => $transaction)
            @php $isIncome = $transaction->cash_delta > 0; @endphp
            <tr>
                <td class="text-center col-number">{{ $index + 1 }}</td>
                <td class="col-date">{{ $transaction->date->format('d.m.Y') }}</td>
                <td class="col-type">{{ $transaction->cash_label }}</td>
                <td class="col-status">
                    @if($transaction->isCancelled())
                        Storno
                    @elseif($transaction->status === 'abgeschlossen')
                        Abgeschlossen
                    @else
                        Offen
                    @endif
                </td>
                <td class="col-description">{{ $transaction->description }}</td>
                <td class="col-account">{{ $transaction->counter_account?->number }} {{ $transaction->counter_account->name ?? '—' }}</td>
                <td class="col-receipt">
                    @if($transaction->receipt_number)
                        {{ $transaction->receipt_number }}
                    @elseif($transaction->hasSystemReceipt())
                        Clubano-Rechnung
                    @else
                        Fehlt
                    @endif
                </td>
                <td class="text-right col-money">{{ $isIncome ? number_format(abs((float) $transaction->cash_delta), 2, ',', '.') . ' €' : '—' }}</td>
                <td class="text-right col-money">{{ !$isIncome ? number_format(abs((float) $transaction->cash_delta), 2, ',', '.') . ' €' : '—' }}</td>
                <td class="text-right col-balance">{{ number_format((float) $transaction->cash_balance, 2, ',', '.') }} €</td>
                <td class="col-user">
                    {{ $transaction->creator?->name ?? 'Unbekannt' }}<br>
                    <span class="muted">{{ optional($transaction->created_at)->format('d.m.Y H:i') ?: '—' }}</span>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="11" class="text-center">Für diesen Zeitraum gibt es noch keine Kassenbewegungen.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<script type="text/php">
    if (isset($pdf)) {
        $pdf->page_text(760, 565, "Seite {PAGE_NUM} von {PAGE_COUNT}", null, 8, array(0,0,0));
    }
</script>
</body>
</html>
