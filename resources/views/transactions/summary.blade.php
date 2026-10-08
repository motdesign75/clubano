@extends('layouts.app')

@section('title', 'Einnahmen & Ausgaben')

@section('content')
@php
    $startLabel = \Carbon\Carbon::parse($start)->format('d.m.Y');
    $endLabel = \Carbon\Carbon::parse($end)->format('d.m.Y');
    $periodLabel = $startLabel . ' bis ' . $endLabel;
@endphp

<div class="mx-auto max-w-7xl space-y-8">
    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-6 bg-slate-950 px-6 py-7 text-white md:px-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl space-y-3">
                <p class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-300">Finanzüberblick</p>
                <div class="space-y-2">
                    <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Einnahmen und Ausgaben im Zusammenhang</h1>
                    <p class="max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                        Nicht nur Summen, sondern ein echter Überblick darüber, was erledigt ist und wo noch geprüft werden sollte.
                    </p>
                </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 px-5 py-4">
                <div class="text-2xl font-semibold">{{ $transactions->count() }}</div>
                <div class="text-sm text-slate-300">Buchungen im Zeitraum</div>
                <div class="mt-1 text-xs text-slate-400">{{ $periodLabel }}</div>
            </div>
        </div>

        <form class="grid gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end md:px-8">
            <div>
                <label for="start" class="mb-1 block text-sm font-medium text-slate-600">Von</label>
                <input id="start" type="date" name="start" value="{{ $start }}" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
            </div>
            <div>
                <label for="end" class="mb-1 block text-sm font-medium text-slate-600">Bis</label>
                <input id="end" type="date" name="end" value="{{ $end }}" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
            </div>
            <button class="inline-flex items-center justify-center rounded-full bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                Aktualisieren
            </button>
        </form>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Einnahmen</div>
            <div class="mt-2 text-2xl font-semibold text-emerald-600">{{ number_format($totalIncome ?? 0, 2, ',', '.') }} €</div>
            <div class="mt-1 text-xs text-slate-500">Gebuchte Zuflüsse im Zeitraum</div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Ausgaben</div>
            <div class="mt-2 text-2xl font-semibold text-rose-600">{{ number_format($totalExpense ?? 0, 2, ',', '.') }} €</div>
            <div class="mt-1 text-xs text-slate-500">Gebuchte Abflüsse im Zeitraum</div>
        </div>

        <div class="rounded-2xl border {{ ($saldo ?? 0) >= 0 ? 'border-slate-200 bg-white' : 'border-amber-200 bg-amber-50/60' }} p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Saldo</div>
            <div class="mt-2 text-2xl font-semibold {{ ($saldo ?? 0) >= 0 ? 'text-slate-900' : 'text-amber-700' }}">
                {{ number_format($saldo ?? 0, 2, ',', '.') }} €
            </div>
            <div class="mt-1 text-xs text-slate-500">Einnahmen minus Ausgaben</div>
        </div>

        <div class="rounded-2xl border {{ ($pendingCount ?? 0) > 0 || ($missingReceiptCount ?? 0) > 0 ? 'border-violet-200 bg-violet-50/60' : 'border-slate-200 bg-white' }} p-5 shadow-sm">
            <div class="text-sm font-medium {{ ($pendingCount ?? 0) > 0 || ($missingReceiptCount ?? 0) > 0 ? 'text-violet-700' : 'text-slate-500' }}">Prüfbedarf</div>
            <div class="mt-2 text-2xl font-semibold {{ ($pendingCount ?? 0) > 0 || ($missingReceiptCount ?? 0) > 0 ? 'text-violet-900' : 'text-slate-900' }}">
                {{ ($pendingCount ?? 0) + ($missingReceiptCount ?? 0) }}
            </div>
            <div class="mt-1 text-xs {{ ($pendingCount ?? 0) > 0 || ($missingReceiptCount ?? 0) > 0 ? 'text-violet-700/80' : 'text-slate-500' }}">
                {{ $pendingCount ?? 0 }} offen, {{ $missingReceiptCount ?? 0 }} ohne externen Beleg
            </div>
        </div>
    </div>

    <section class="grid gap-4 xl:grid-cols-3">
        <div class="rounded-2xl border {{ ($overdueInvoices ?? collect())->isNotEmpty() ? 'border-rose-200 bg-rose-50/60' : 'border-slate-200 bg-white' }} p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Geld ausstehend</h2>
                    <p class="mt-1 text-sm text-slate-500">Offene Rechnungen, die jetzt Aufmerksamkeit brauchen.</p>
                </div>
                <a href="{{ route('invoices.index', ['status' => 'overdue']) }}" class="shrink-0 text-sm font-medium text-slate-600 hover:text-slate-950">Öffnen</a>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl bg-white px-3 py-2">
                    <div class="text-xs text-slate-500">Überfällig</div>
                    <div class="mt-1 font-mono text-lg font-semibold text-rose-700">{{ ($overdueInvoices ?? collect())->count() }}</div>
                </div>
                <div class="rounded-xl bg-white px-3 py-2">
                    <div class="text-xs text-slate-500">Nächste 14 Tage</div>
                    <div class="mt-1 font-mono text-lg font-semibold text-amber-700">{{ ($dueSoonInvoices ?? collect())->count() }}</div>
                </div>
            </div>

            <div class="mt-4 space-y-2">
                @forelse(($overdueInvoices ?? collect())->take(3) as $invoice)
                    <a href="{{ route('invoices.show', $invoice) }}" class="block rounded-xl border border-white bg-white px-3 py-2 text-sm hover:border-rose-200">
                        <div class="flex items-center justify-between gap-3">
                            <span class="truncate font-medium text-slate-900">{{ $invoice->invoice_number }} · {{ $invoice->recipient_name }}</span>
                            <span class="font-mono text-rose-700">{{ number_format($invoice->getRemainingAmount(), 2, ',', '.') }} €</span>
                        </div>
                        <div class="mt-1 text-xs text-slate-500">Fällig {{ optional($invoice->due_date)->format('d.m.Y') }}</div>
                    </a>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 bg-white px-3 py-5 text-sm text-slate-500">
                        Keine überfälligen offenen Rechnungen.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border {{ ($receiptReviewDocuments ?? collect())->isNotEmpty() ? 'border-amber-200 bg-amber-50/60' : 'border-slate-200 bg-white' }} p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Belege prüfen</h2>
                    <p class="mt-1 text-sm text-slate-500">Hochgeladene Rechnungen und Belege, bevor daraus Buchungen werden.</p>
                </div>
                <a href="{{ route('payables.index') }}" class="shrink-0 text-sm font-medium text-slate-600 hover:text-slate-950">Öffnen</a>
            </div>

            <div class="mt-4 rounded-xl bg-white px-3 py-2 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-slate-500">Zu bezahlen / zu buchen</span>
                    <span class="font-mono font-semibold text-slate-950">{{ $payableReceiptCount ?? 0 }}</span>
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    Erkannter Gesamtbetrag: {{ number_format((float) ($payableReceiptTotal ?? 0), 2, ',', '.') }} €
                </div>
            </div>

            <div class="mt-4 space-y-2">
                @forelse(($receiptReviewDocuments ?? collect()) as $receipt)
                    <a href="{{ route('documents.show', $receipt) }}" class="block rounded-xl border border-white bg-white px-3 py-2 text-sm hover:border-amber-200">
                        <div class="flex items-center justify-between gap-3">
                            <span class="truncate font-medium text-slate-900">{{ $receipt->title }}</span>
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">{{ $receipt->receipt_status_label }}</span>
                        </div>
                        <div class="mt-1 text-xs text-slate-500">
                            {{ $receipt->recognized_vendor ?: 'Anbieter offen' }}
                            @if($receipt->recognized_amount)
                                · {{ number_format((float) $receipt->recognized_amount, 2, ',', '.') }} €
                            @endif
                        </div>
                    </a>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 bg-white px-3 py-5 text-sm text-slate-500">
                        Keine Belege im Prüfeingang.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border {{ array_sum($bankWorkStats ?? []) > 0 ? 'border-blue-200 bg-blue-50/60' : 'border-slate-200 bg-white' }} p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Bank zuordnen</h2>
                    <p class="mt-1 text-sm text-slate-500">Umsätze prüfen, vorbereiten und Dubletten im Blick behalten.</p>
                </div>
                <a href="{{ route('bank-imports.index') }}" class="shrink-0 text-sm font-medium text-slate-600 hover:text-slate-950">Öffnen</a>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-2 text-sm">
                <div class="rounded-xl bg-white px-3 py-2">
                    <div class="text-xs text-slate-500">Zuordnen</div>
                    <div class="mt-1 font-mono text-lg font-semibold text-amber-700">{{ $bankWorkStats['pending'] ?? 0 }}</div>
                </div>
                <div class="rounded-xl bg-white px-3 py-2">
                    <div class="text-xs text-slate-500">Bereit</div>
                    <div class="mt-1 font-mono text-lg font-semibold text-blue-700">{{ $bankWorkStats['ready'] ?? 0 }}</div>
                </div>
                <div class="rounded-xl bg-white px-3 py-2">
                    <div class="text-xs text-slate-500">Dubletten</div>
                    <div class="mt-1 font-mono text-lg font-semibold text-violet-700">{{ $bankWorkStats['duplicate'] ?? 0 }}</div>
                </div>
            </div>

            <div class="mt-4 space-y-2">
                @forelse(($bankWorkQueue ?? collect())->take(3) as $bankTransaction)
                    <a href="{{ route('bank-imports.index', ['status' => $bankTransaction->status]) }}" class="block rounded-xl border border-white bg-white px-3 py-2 text-sm hover:border-blue-200">
                        <div class="flex items-center justify-between gap-3">
                            <span class="truncate font-medium text-slate-900">{{ $bankTransaction->counterparty_name ?: 'Unbekannter Umsatz' }}</span>
                            <span class="font-mono {{ $bankTransaction->isCredit() ? 'text-emerald-700' : 'text-rose-700' }}">{{ number_format((float) $bankTransaction->amount, 2, ',', '.') }} €</span>
                        </div>
                        <div class="mt-1 text-xs text-slate-500">{{ optional($bankTransaction->booking_date)->format('d.m.Y') }} · {{ $bankTransaction->statusLabel() }}</div>
                    </a>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 bg-white px-3 py-5 text-sm text-slate-500">
                        Keine offenen Bankumsätze.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    @if(($cashAndBankAccounts ?? collect())->isNotEmpty())
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Bank und Kasse</h2>
                    <p class="mt-1 text-sm text-slate-500">Aktuelle Bestände der Geldkonten als schnelle Plausibilitätsprüfung.</p>
                </div>
                <a href="{{ route('transactions.cashbook') }}" class="text-sm font-medium text-slate-600 hover:text-slate-950">Zum Kassenbuch</a>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($cashAndBankAccounts as $account)
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="truncate text-sm font-semibold text-slate-900">{{ $account->name }}</div>
                            <span class="rounded-full bg-white px-2 py-1 text-xs font-medium text-slate-500">{{ ucfirst($account->type) }}</span>
                        </div>
                        <div class="mt-2 font-mono text-lg font-semibold text-slate-950">{{ number_format((float) $account->balance_current, 2, ',', '.') }} €</div>
                        @if($account->balance_date)
                            <div class="mt-1 text-xs text-slate-500">Stand {{ $account->balance_date->format('d.m.Y') }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if(($categorySummaries ?? collect())->isNotEmpty())
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Auswertung nach Haushaltsbereichen</h2>
                    <p class="mt-1 text-sm text-slate-500">Aktuelle Buchungen nach Bereich, damit du das Folgejahr schneller planen kannst.</p>
                </div>
                <a href="{{ route('budgets.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                    Zum Haushaltsplan
                </a>
            </div>

            <div class="mt-5 grid gap-3 lg:grid-cols-2">
                @foreach($categorySummaries as $categorySummary)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold text-slate-900">{{ $categorySummary['name'] }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $categorySummary['transactions_count'] }} Buchung{{ $categorySummary['transactions_count'] === 1 ? '' : 'en' }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-mono text-sm font-semibold {{ $categorySummary['result'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                    {{ number_format($categorySummary['result'], 2, ',', '.') }} €
                                </div>
                                <div class="mt-1 text-xs text-slate-500">Saldo</div>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-xl bg-white px-3 py-2">
                                <div class="text-xs text-slate-500">Einnahmen</div>
                                <div class="mt-1 font-mono font-semibold text-emerald-700">{{ number_format($categorySummary['income'], 2, ',', '.') }} €</div>
                            </div>
                            <div class="rounded-xl bg-white px-3 py-2">
                                <div class="text-xs text-slate-500">Ausgaben</div>
                                <div class="mt-1 font-mono font-semibold text-rose-700">{{ number_format($categorySummary['expense'], 2, ',', '.') }} €</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if(($pendingCount ?? 0) > 0 || ($missingReceiptCount ?? 0) > 0)
        <div class="rounded-2xl border border-violet-200 bg-violet-50 px-5 py-4 text-sm text-violet-900 shadow-sm">
            <div class="font-semibold">Was jetzt sinnvoll zuerst dran ist</div>
            <div class="mt-1">
                {{ $pendingCount ?? 0 }} Buchung{{ ($pendingCount ?? 0) === 1 ? '' : 'en' }} sind noch nicht abgeschlossen,
                {{ $missingReceiptCount ?? 0 }} haben keinen externen Beleg.
                {{ $systemReceiptCount ?? 0 }} Buchung{{ ($systemReceiptCount ?? 0) === 1 ? '' : 'en' }} stammen direkt aus Clubano-Rechnungen und gelten bereits als Systembeleg.
            </div>
        </div>
    @endif

    @if(($pendingTransactions ?? collect())->isNotEmpty() || ($missingReceiptTransactions ?? collect())->isNotEmpty())
        <section class="grid gap-4 xl:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Offene Buchungen</h2>
                        <p class="mt-1 text-sm text-slate-500">Diese Buchungen wirken noch nicht final auf den Abschluss.</p>
                    </div>
                    <a href="{{ route('transactions.index', ['year' => \Carbon\Carbon::parse($start)->year]) }}"
                       class="text-sm font-medium text-slate-600 hover:text-slate-900">
                        Zur Buchungsliste
                    </a>
                </div>

                <div class="mt-4 space-y-3">
                    @forelse(($pendingTransactions ?? collect()) as $transaction)
                        <div class="flex items-start justify-between gap-3 rounded-2xl border border-slate-200 px-4 py-3">
                            <div class="min-w-0">
                                <div class="text-sm text-slate-500">{{ optional($transaction->date)->format('d.m.Y') }}</div>
                                <div class="mt-1 font-medium text-slate-900">{{ $transaction->description }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $transaction->account_from->name ?? '—' }} → {{ $transaction->account_to->name ?? '—' }}</div>
                            </div>
                            <a href="{{ route('transactions.edit', $transaction) }}"
                               class="shrink-0 rounded-full border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                Öffnen
                            </a>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                            Keine offenen Buchungen im Zeitraum.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Belege nachreichen</h2>
                        <p class="mt-1 text-sm text-slate-500">Hier fehlt noch ein externer Nachweis für den Vorgang.</p>
                    </div>
                    <a href="{{ route('transactions.index', ['filter' => 'missing_receipt', 'year' => \Carbon\Carbon::parse($start)->year]) }}"
                       class="text-sm font-medium text-slate-600 hover:text-slate-900">
                        Nur fehlende Belege
                    </a>
                </div>

                <div class="mt-4 space-y-3">
                    @forelse(($missingReceiptTransactions ?? collect()) as $transaction)
                        <div class="flex items-start justify-between gap-3 rounded-2xl border border-violet-200 bg-violet-50/40 px-4 py-3">
                            <div class="min-w-0">
                                <div class="text-sm text-violet-700">{{ optional($transaction->date)->format('d.m.Y') }}</div>
                                <div class="mt-1 font-medium text-slate-900">{{ $transaction->description }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $transaction->account_from->name ?? '—' }} → {{ $transaction->account_to->name ?? '—' }}</div>
                            </div>
                            @if($transaction->status !== 'abgeschlossen')
                                <a href="{{ route('transactions.own-receipt', $transaction) }}"
                                   class="shrink-0 rounded-full border border-violet-200 bg-white px-3 py-1.5 text-xs font-medium text-violet-700 hover:bg-violet-100">
                                    Eigenbeleg
                                </a>
                            @else
                                <a href="{{ route('transactions.own-receipt', $transaction) }}"
                                   class="shrink-0 rounded-full border border-violet-200 bg-white px-3 py-1.5 text-xs font-medium text-violet-700 hover:bg-violet-100">
                                    Eigenbeleg
                                </a>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                            Keine fehlenden Belege im Zeitraum.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>
    @endif

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Buchungen im Zeitraum</h2>
                <p class="mt-1 text-sm text-slate-500">Mit Belegstatus, damit am Jahresende nichts gesucht werden muss.</p>
            </div>
        </div>

        <div class="hidden md:block">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Datum</th>
                        <th class="px-4 py-3 text-left font-semibold">Beschreibung</th>
                        <th class="px-4 py-3 text-left font-semibold">Konten</th>
                        <th class="px-4 py-3 text-left font-semibold">Belegstatus</th>
                        <th class="px-4 py-3 text-right font-semibold">Betrag</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($transactions as $transaction)
                        @php
                            $amount = $transaction->amount;
                            $class = 'text-slate-900';
                            $prefix = '';

                            if (optional($transaction->account_from)->type === 'einnahme') {
                                $class = 'text-emerald-700';
                            } elseif (optional($transaction->account_to)->type === 'ausgabe') {
                                $class = 'text-rose-700';
                                $prefix = '-';
                            } elseif ($transaction->isCancelled()) {
                                $class = 'text-amber-700';
                            }
                        @endphp
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-4 text-slate-600">{{ \Carbon\Carbon::parse($transaction->date)->format('d.m.Y') }}</td>
                            <td class="px-4 py-4">
                                <div class="font-medium text-slate-900">{{ $transaction->description }}</div>
                                <div class="mt-1 text-xs text-slate-500">
                                    {{ $transaction->status === 'abgeschlossen' ? 'Abgeschlossen' : 'Offen' }}
                                </div>
                            </td>
                            <td class="px-4 py-4 text-slate-700">
                                <div>{{ $transaction->account_from->name ?? '—' }}</div>
                                <div class="mt-1 text-xs text-slate-400">nach</div>
                                <div class="mt-1">{{ $transaction->account_to->name ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-4">
                                @if($transaction->hasOwnReceipt())
                                    <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">Eigenbeleg</span>
                                @elseif($transaction->hasContractReceipt())
                                    <span class="inline-flex rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">Vertrag/Dauerbeleg</span>
                                @elseif($transaction->receipt_file)
                                    <span class="inline-flex rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">Beleg vorhanden</span>
                                @elseif($transaction->hasSystemReceipt())
                                    <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Clubano-Rechnung</span>
                                @else
                                    <span class="inline-flex rounded-full border border-violet-200 bg-violet-50 px-2.5 py-1 text-xs font-medium text-violet-700">Beleg fehlt</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-right font-mono font-semibold {{ $class }}">
                                {{ $prefix }}{{ number_format($amount, 2, ',', '.') }} €
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-sm text-slate-500">Keine Buchungen im gewählten Zeitraum.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="space-y-4 p-4 md:hidden">
            @forelse($transactions as $transaction)
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm text-slate-500">{{ \Carbon\Carbon::parse($transaction->date)->format('d.m.Y') }}</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $transaction->description }}</div>
                        </div>
                        <div class="text-right font-mono font-semibold {{ optional($transaction->account_from)->type === 'einnahme' ? 'text-emerald-700' : (optional($transaction->account_to)->type === 'ausgabe' ? 'text-rose-700' : 'text-slate-900') }}">
                            {{ optional($transaction->account_to)->type === 'ausgabe' ? '-' : '' }}{{ number_format($transaction->amount, 2, ',', '.') }} €
                        </div>
                    </div>

                    <div class="mt-3 text-sm text-slate-600">
                        {{ $transaction->account_from->name ?? '—' }} → {{ $transaction->account_to->name ?? '—' }}
                    </div>

                    <div class="mt-3">
                        @if($transaction->hasOwnReceipt())
                            <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">Eigenbeleg</span>
                        @elseif($transaction->hasContractReceipt())
                            <span class="inline-flex rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">Vertrag/Dauerbeleg</span>
                        @elseif($transaction->receipt_file)
                            <span class="inline-flex rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">Beleg vorhanden</span>
                        @elseif($transaction->hasSystemReceipt())
                            <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Clubano-Rechnung</span>
                        @else
                            <span class="inline-flex rounded-full border border-violet-200 bg-violet-50 px-2.5 py-1 text-xs font-medium text-violet-700">Beleg fehlt</span>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
                    Keine Buchungen im gewählten Zeitraum.
                </div>
            @endforelse
        </div>
    </section>
</div>
@endsection
