<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Kassenbuch</title>

<style>
@page {
    margin: 12mm;
}

body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 9pt;
    color: #111827;
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
    margin-bottom: 14px;
}

.header td {
    vertical-align: top;
}

.header .right {
    text-align: right;
    font-size: 8.5pt;
}

.summary {
    margin: 10px 0 14px;
}

.summary table {
    width: 100%;
    border-collapse: collapse;
}

.summary td {
    border: 1px solid #cbd5e1;
    padding: 8px;
}

.summary .label {
    font-size: 8pt;
    color: #475569;
}

.summary .value {
    font-size: 12pt;
    font-weight: bold;
}

table.entries {
    width: 100%;
    border-collapse: collapse;
}

table.entries th,
table.entries td {
    border: 1px solid #cbd5e1;
    padding: 5px 6px;
    vertical-align: top;
}

table.entries th {
    background: #f8fafc;
    font-size: 8pt;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.text-right {
    text-align: right;
}

.text-center {
    text-align: center;
}

.muted {
    color: #64748b;
    font-size: 8pt;
}

.positive {
    color: #047857;
}

.negative {
    color: #b91c1c;
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
            <th class="text-center">Nr.</th>
            <th>Datum</th>
            <th>Vorgang</th>
            <th>Status</th>
            <th>Beschreibung</th>
            <th>Gegenkonto</th>
            <th>Beleg-Nr.</th>
            <th class="text-right">Einnahme</th>
            <th class="text-right">Ausgabe</th>
            <th class="text-right">Bestand</th>
            <th>Erfasst von</th>
        </tr>
    </thead>
    <tbody>
        @forelse($transactions as $index => $transaction)
            @php $isIncome = $transaction->cash_delta > 0; @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $transaction->date->format('d.m.Y') }}</td>
                <td>{{ $transaction->cash_label }}</td>
                <td>
                    @if($transaction->isCancelled())
                        Storno
                    @elseif($transaction->status === 'abgeschlossen')
                        Abgeschlossen
                    @else
                        Offen
                    @endif
                </td>
                <td>{{ $transaction->description }}</td>
                <td>{{ $transaction->counter_account->name ?? '—' }}</td>
                <td>
                    @if($transaction->receipt_number)
                        {{ $transaction->receipt_number }}
                    @elseif($transaction->hasSystemReceipt())
                        Clubano-Rechnung
                    @else
                        Fehlt
                    @endif
                </td>
                <td class="text-right">{{ $isIncome ? number_format(abs((float) $transaction->cash_delta), 2, ',', '.') . ' €' : '—' }}</td>
                <td class="text-right">{{ !$isIncome ? number_format(abs((float) $transaction->cash_delta), 2, ',', '.') . ' €' : '—' }}</td>
                <td class="text-right">{{ number_format((float) $transaction->cash_balance, 2, ',', '.') }} €</td>
                <td>
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
