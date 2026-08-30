<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Buchungsjournal</title>

<style>
@page {
    size: A4 landscape;
    margin: 8mm 8mm 10mm;
}

body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 7.7pt;
    color: #0f172a;
    margin: 0;
    line-height: 1.25;
}

.toolbar {
    margin-bottom: 16px;
}

.toolbar button,
.toolbar a {
    display: inline-block;
    margin-right: 8px;
    padding: 8px 12px;
    border-radius: 999px;
    text-decoration: none;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #0f172a;
    font-size: 9pt;
}

.toolbar a.primary,
.toolbar button.primary {
    background: #0f172a;
    border-color: #0f172a;
    color: #fff;
}

.hero {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 8px;
}

.hero-top {
    background: #0f172a;
    color: #fff;
    padding: 9px 11px;
}

.hero-grid {
    width: 100%;
    border-collapse: collapse;
}

.hero-grid td {
    vertical-align: top;
}

.hero-title {
    font-size: 14pt;
    font-weight: bold;
}

.hero-subtitle {
    margin-top: 3px;
    font-size: 7.5pt;
    color: #cbd5e1;
    line-height: 1.25;
}

.hero-right {
    text-align: right;
    font-size: 7.5pt;
    color: #e2e8f0;
}

.summary {
    background: #f8fafc;
    border-top: 1px solid #cbd5e1;
    padding: 7px 11px;
}

.summary-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.summary-table td {
    width: 25%;
    border-right: 1px solid #e2e8f0;
    background: transparent;
    padding: 2px 10px 2px 0;
}

.summary-table td + td {
    padding-left: 10px;
}

.summary-table td:last-child {
    border-right: none;
}

.summary-label {
    font-size: 6.5pt;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: #64748b;
}

.summary-value {
    margin-top: 2px;
    font-size: 10pt;
    font-weight: bold;
    color: #0f172a;
}

.summary-note {
    margin-top: 1px;
    font-size: 6.8pt;
    color: #64748b;
}

.table-wrap {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    overflow: hidden;
}

table.entries {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

table.entries thead {
    display: table-header-group;
}

table.entries tfoot {
    display: table-footer-group;
}

table.entries th,
table.entries td {
    border-bottom: 1px solid #e2e8f0;
    padding: 4px 5px;
    vertical-align: top;
    overflow-wrap: anywhere;
}

table.entries th {
    background: #f8fafc;
    font-size: 6.2pt;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748b;
    text-align: left;
}

table.entries tbody tr:nth-child(even) {
    background: #fcfdff;
}

table.entries tbody tr:last-child td {
    border-bottom: none;
}

.text-right {
    text-align: right;
}

.text-center {
    text-align: center;
}

.muted {
    color: #64748b;
}

.amount-income {
    color: #047857;
    font-weight: bold;
}

.amount-expense {
    color: #b91c1c;
    font-weight: bold;
}

.amount-neutral {
    color: #0f172a;
    font-weight: bold;
}

.status {
    display: inline-block;
    padding: 1px 5px;
    border-radius: 999px;
    font-size: 6.5pt;
    font-weight: bold;
    border: 1px solid #cbd5e1;
    color: #334155;
    background: #fff;
}

.status.done {
    border-color: #bbf7d0;
    background: #f0fdf4;
    color: #166534;
}

.status.open {
    border-color: #bfdbfe;
    background: #eff6ff;
    color: #1d4ed8;
}

.status.cancelled {
    border-color: #fde68a;
    background: #fffbeb;
    color: #b45309;
}

.status.missing {
    border-color: #ddd6fe;
    background: #f5f3ff;
    color: #6d28d9;
}

.check-cell {
    width: 22px;
}

.check-stack {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}

.check-box {
    display: inline-block;
    width: 10px;
    height: 10px;
    border: 1px solid #94a3b8;
    border-radius: 3px;
    background: #fff;
}

.check-toggle {
    width: 12px;
    height: 12px;
    margin: 0;
    accent-color: #0f172a;
    cursor: pointer;
}

.check-toggle:disabled {
    cursor: wait;
    opacity: 0.65;
}

.check-meta {
    max-width: 70px;
    font-size: 5.8pt;
    line-height: 1.35;
    color: #64748b;
    text-align: center;
}

.desc {
    font-weight: bold;
    color: #0f172a;
    line-height: 1.25;
}

.desc-meta {
    margin-top: 2px;
    font-size: 6.5pt;
    color: #64748b;
}

.footer-total td {
    background: #f8fafc;
    font-weight: bold;
}

.footer-total .label {
    text-align: right;
    color: #475569;
}

.row-missing td {
    background: #faf5ff;
}

.audit-footer {
    margin-top: 8px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 8px 10px;
    page-break-inside: avoid;
}

.audit-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.audit-label {
    font-size: 6.3pt;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748b;
}

.audit-line {
    margin-top: 14px;
    border-top: 1px solid #94a3b8;
    height: 1px;
}

.audit-note {
    margin-top: 5px;
    font-size: 7pt;
    color: #64748b;
}

.col-check {
    width: 5%;
}

.col-number {
    width: 4%;
}

.col-date {
    width: 8%;
}

.col-receipt {
    width: 10%;
}

.col-description {
    width: 24%;
}

.col-account {
    width: 14%;
}

.col-status {
    width: 9%;
}

.col-amount {
    width: 9%;
}

.col-receipt-check {
    width: 8%;
}

@media print {
    .toolbar {
        display: none;
    }

    body {
        font-size: 7.3pt;
    }

    .hero {
        margin-bottom: 6px;
    }

    table.entries th,
    table.entries td {
        padding: 3px 4px;
    }
}
</style>
</head>

<body>
@php
    $isPdf = $isPdf ?? false;
    $missingReceiptCount = $transactions->filter(fn ($transaction) => !$transaction->hasAnyReceipt())->count();
    $periodLabel = $year ? 'Jahr ' . $year : 'Alle Jahre';
    if ($month) {
        $periodLabel .= ' · ' . \Carbon\Carbon::createFromDate((int) ($year ?: now()->year), (int) $month, 1)->translatedFormat('F');
    }
@endphp

<div class="toolbar">
    <button onclick="window.print()">Drucken</button>
    <a href="{{ route('transactions.journal.pdf', [
            'filter' => $filter,
            'year' => $year,
            'month' => $month
        ]) }}" class="primary">
        PDF herunterladen
    </a>
</div>

<section class="hero">
    <div class="hero-top">
        <table class="hero-grid">
            <tr>
                <td>
                    <div class="hero-title">Buchungsjournal</div>
                    <div class="hero-subtitle">
                        Klare Druckansicht für Prüfung, Ablage und Nachvollziehbarkeit. DIN A4 im Querformat.
                    </div>
                </td>
                <td class="hero-right">
                    <strong>{{ $tenant->name ?? 'Verein' }}</strong><br>
                    Erstellt am {{ now()->format('d.m.Y H:i') }}<br>
                    Zeitraum: {{ $periodLabel }}
                </td>
            </tr>
        </table>
    </div>

    <div class="summary">
        <table class="summary-table">
            <tr>
                <td>
                    <div class="summary-label">Buchungen</div>
                    <div class="summary-value">{{ $transactions->count() }}</div>
                    <div class="summary-note">im gewählten Zeitraum</div>
                </td>
                <td>
                    <div class="summary-label">Einnahmen</div>
                    <div class="summary-value">{{ number_format($totalIncome, 2, ',', '.') }} €</div>
                    <div class="summary-note">gebuchte Zuflüsse</div>
                </td>
                <td>
                    <div class="summary-label">Ausgaben</div>
                    <div class="summary-value">{{ number_format($totalExpense, 2, ',', '.') }} €</div>
                    <div class="summary-note">gebuchte Abflüsse</div>
                </td>
                <td>
                    <div class="summary-label">Ohne Beleg</div>
                    <div class="summary-value">{{ $missingReceiptCount }}</div>
                    <div class="summary-note">Clubano-Rechnungen ausgenommen</div>
                </td>
            </tr>
        </table>
    </div>
</section>

<div class="table-wrap">
    <table class="entries">
        <thead>
            <tr>
                <th class="text-center col-check">Geprüft</th>
                <th class="text-center col-number">Nr.</th>
                <th class="col-date">Datum</th>
                <th class="col-receipt">Beleg</th>
                <th class="col-description">Beschreibung</th>
                <th class="col-account">Von</th>
                <th class="col-account">Nach</th>
                <th class="col-status">Status</th>
                <th class="text-right col-amount">Betrag</th>
                <th class="text-center col-receipt-check">Beleg</th>
            </tr>
        </thead>

        <tbody>
        @php $i = 1; @endphp
        @foreach($transactions as $t)
            @php
                $isExpense = in_array(optional($t->account_from)->type, ['bank', 'kasse']);
                $statusClass = $t->isCancelled()
                    ? 'cancelled'
                    : ($t->status === 'abgeschlossen' ? 'done' : 'open');
                $statusLabel = $t->isCancelled()
                    ? 'Storno'
                    : ($t->status === 'abgeschlossen' ? 'Abgeschlossen' : 'Offen');
                $receiptLabel = $t->hasOwnReceipt()
                    ? 'Eigenbeleg'
                    : ($t->hasContractReceipt()
                        ? 'Vertrag/Dauerbeleg'
                        : ($t->receipt_number ?: ($t->hasSystemReceipt() ? 'Clubano' : 'Fehlt')));
                $missingReceipt = !$t->hasAnyReceipt();
                $amountClass = $isExpense ? 'amount-expense' : 'amount-income';
                $amountPrefix = $isExpense ? '-' : '';
            @endphp
            <tr class="{{ $missingReceipt ? 'row-missing' : '' }}">
                <td class="text-center col-check">
                    @if($isPdf)
                        <span class="check-stack">
                            <span class="check-box"></span>
                            @if($t->isJournalReviewed())
                                <span class="check-meta">
                                    {{ $t->journalReviewer?->name ?? 'Clubano' }}<br>
                                    {{ optional($t->journal_reviewed_at)->format('d.m. H:i') }}
                                </span>
                            @endif
                        </span>
                    @else
                        <span class="check-stack">
                            <input
                                type="checkbox"
                                class="check-toggle"
                                data-journal-check="journal_reviewed"
                                data-journal-id="{{ $t->id }}"
                                @checked($t->isJournalReviewed())
                                aria-label="Buchung {{ $t->description }} als geprüft markieren"
                            >
                            <span class="check-meta" data-journal-meta="journal_reviewed" data-journal-id="{{ $t->id }}">
                                @if($t->isJournalReviewed())
                                    {{ $t->journalReviewer?->name ?? 'Clubano' }}<br>
                                    {{ optional($t->journal_reviewed_at)->format('d.m. H:i') }}
                                @endif
                            </span>
                        </span>
                    @endif
                </td>
                <td class="text-center col-number">{{ $i++ }}</td>
                <td class="col-date">{{ \Carbon\Carbon::parse($t->date)->format('d.m.Y') }}</td>
                <td class="col-receipt">{{ $receiptLabel }}</td>
                <td class="col-description">
                    <div class="desc">{{ $t->description }}</div>
                    <div class="desc-meta">
                        {{ $t->tax_area ?: 'ohne Bereich' }}
                    </div>
                </td>
                <td class="col-account">{{ $t->account_from?->number }} {{ $t->account_from->name ?? '-' }}</td>
                <td class="col-account">{{ $t->account_to?->number }} {{ $t->account_to->name ?? '-' }}</td>
                <td class="col-status">
                    <span class="status {{ $statusClass }}">{{ $statusLabel }}</span>
                    @if($missingReceipt)
                        <span class="status missing">Beleg fehlt</span>
                    @endif
                </td>
                <td class="text-right col-amount {{ $amountClass }}">{{ $amountPrefix }}{{ number_format($t->amount, 2, ',', '.') }}</td>
                <td class="text-center col-receipt-check">
                    @if($isPdf)
                        <span class="check-stack">
                            <span class="check-box"></span>
                            @if($t->isJournalReceiptChecked())
                                <span class="check-meta">
                                    {{ $t->journalReceiptChecker?->name ?? 'Clubano' }}<br>
                                    {{ optional($t->journal_receipt_checked_at)->format('d.m. H:i') }}
                                </span>
                            @endif
                        </span>
                    @else
                        <span class="check-stack">
                            <input
                                type="checkbox"
                                class="check-toggle"
                                data-journal-check="journal_receipt_checked"
                                data-journal-id="{{ $t->id }}"
                                @checked($t->isJournalReceiptChecked())
                                aria-label="Beleg für {{ $t->description }} als geprüft markieren"
                            >
                            <span class="check-meta" data-journal-meta="journal_receipt_checked" data-journal-id="{{ $t->id }}">
                                @if($t->isJournalReceiptChecked())
                                    {{ $t->journalReceiptChecker?->name ?? 'Clubano' }}<br>
                                    {{ optional($t->journal_receipt_checked_at)->format('d.m. H:i') }}
                                @endif
                            </span>
                        </span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>

        <tfoot>
            <tr class="footer-total">
                <td colspan="8" class="label">Einnahmen</td>
                <td class="text-right amount-income">{{ number_format($totalIncome, 2, ',', '.') }} €</td>
                <td></td>
            </tr>
            <tr class="footer-total">
                <td colspan="8" class="label">Ausgaben</td>
                <td class="text-right amount-expense">-{{ number_format($totalExpense, 2, ',', '.') }} €</td>
                <td></td>
            </tr>
            <tr class="footer-total">
                <td colspan="8" class="label">Saldo</td>
                <td class="text-right amount-neutral">{{ number_format($saldo, 2, ',', '.') }} €</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<section class="audit-footer">
    <table class="audit-table">
        <tr>
            <td style="width: 34%;">
                <div class="audit-label">Kassenprüfung durchgeführt von</div>
                <div class="audit-line"></div>
            </td>
            <td style="width: 18%;">
                <div class="audit-label">Datum</div>
                <div class="audit-line"></div>
            </td>
            <td style="width: 18%;">
                <div class="audit-label">Belege vollständig</div>
                <div class="audit-note">Ja [ ] &nbsp;&nbsp; Nein [ ]</div>
            </td>
            <td style="width: 30%;">
                <div class="audit-label">Bemerkungen</div>
                <div class="audit-line"></div>
            </td>
        </tr>
    </table>
</section>

@unless($isPdf)
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const csrfToken = '{{ csrf_token() }}';

        document.querySelectorAll('[data-journal-check]').forEach((input) => {
            input.addEventListener('change', () => {
                const nextChecked = input.checked;
                const previousChecked = !nextChecked;
                input.disabled = true;

                fetch(`/transactions/${input.dataset.journalId}/journal-check`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        field: input.dataset.journalCheck,
                        checked: nextChecked,
                    }),
                })
                    .then(async (response) => {
                        if (!response.ok) {
                            throw new Error('Die Aenderung konnte nicht gespeichert werden.');
                        }

                        return response.json();
                    })
                    .then((data) => {
                        const meta = document.querySelector(`[data-journal-meta="${input.dataset.journalCheck}"][data-journal-id="${input.dataset.journalId}"]`);

                        if (!meta) {
                            return;
                        }

                        if (!data.checked) {
                            meta.innerHTML = '';
                            return;
                        }

                        meta.innerHTML = `${data.user_name}<br>${data.display_time}`;
                    })
                    .catch(() => {
                        input.checked = previousChecked;
                        window.alert('Die Pruef-Markierung konnte gerade nicht gespeichert werden.');
                    })
                    .finally(() => {
                        input.disabled = false;
                    });
            });
        });
    });
</script>
@endunless

<script type="text/php">
    if (isset($pdf)) {
        $pdf->page_text(
            725, 570,
            "Seite {PAGE_NUM} von {PAGE_COUNT}",
            null,
            8,
            array(71/255, 85/255, 105/255)
        );
    }
</script>

</body>
</html>
