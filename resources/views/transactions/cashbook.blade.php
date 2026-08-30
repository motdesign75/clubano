@extends('layouts.app')

@section('title', 'Kassenbuch')

@section('content')
@php
    $currentYear = now()->year;
    $selectedMonthLabel = $selectedMonth
        ? \Carbon\Carbon::createFromDate($selectedYear, $selectedMonth, 1)->translatedFormat('F Y')
        : 'Gesamtes Jahr ' . $selectedYear;

    $firstBankAccount = $bankAccounts->first();
    $quickLinks = $selectedCashAccount ? [
        [
            'label' => 'Bareinnahme',
            'route' => route('transactions.create', [
                'context' => 'bar-einnahme',
                'account_to_id' => $selectedCashAccount->id,
                'tax_area' => $selectedCashAccount->tax_area,
            ]),
            'classes' => 'bg-emerald-600 text-white hover:bg-emerald-700',
        ],
        [
            'label' => 'Barausgabe',
            'route' => route('transactions.create', [
                'context' => 'bar-ausgabe',
                'account_from_id' => $selectedCashAccount->id,
                'tax_area' => $selectedCashAccount->tax_area,
            ]),
            'classes' => 'bg-rose-600 text-white hover:bg-rose-700',
        ],
        [
            'label' => 'Bank -> Kasse',
            'route' => $firstBankAccount
                ? route('transactions.create', [
                    'context' => 'bank-zu-kasse',
                    'account_from_id' => $firstBankAccount->id,
                    'account_to_id' => $selectedCashAccount->id,
                    'tax_area' => $selectedCashAccount->tax_area,
                ])
                : route('accounts.create'),
            'classes' => 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
        ],
        [
            'label' => 'Kasse -> Bank',
            'route' => $firstBankAccount
                ? route('transactions.create', [
                    'context' => 'kasse-zu-bank',
                    'account_from_id' => $selectedCashAccount->id,
                    'account_to_id' => $firstBankAccount->id,
                    'tax_area' => $selectedCashAccount->tax_area,
                ])
                : route('accounts.create'),
            'classes' => 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
        ],
    ] : [];

    $movementLabels = [
        'all' => 'Alle Bewegungen',
        'income' => 'Nur Einnahmen',
        'expense' => 'Nur Ausgaben',
        'transfer' => 'Nur Umbuchungen',
    ];
    $hasDraftTransactions = collect($transactions instanceof \Illuminate\Pagination\AbstractPaginator ? $transactions->items() : $transactions)->contains(fn ($transaction) => $transaction->status === 'entwurf' && !$transaction->isCancelled());
    $missingReceiptCount = collect($transactions instanceof \Illuminate\Pagination\AbstractPaginator ? $transactions->items() : $transactions)
        ->filter(fn ($transaction) => !$transaction->hasAnyReceipt())
        ->count();
@endphp

<div class="mx-auto max-w-7xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4 2xl:flex-row 2xl:items-start 2xl:justify-between">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Kassenbuch</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-500">
                Alle Barbewegungen an einem Ort. Das Kassenbuch arbeitet auf den bestehenden Buchungen, damit nichts doppelt geführt wird und keine Finanzbewegung verloren geht.
            </p>
        </div>

        @if($selectedCashAccount)
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                @foreach($quickLinks as $link)
                    <a href="{{ $link['route'] }}"
                       class="inline-flex w-full items-center justify-center rounded-xl px-4 py-2.5 text-sm font-medium shadow-sm transition sm:w-auto {{ $link['classes'] }}">
                        {{ $link['label'] }}
                    </a>
                @endforeach
                <a href="{{ route('transactions.cashbook.print', request()->query()) }}"
                   target="_blank"
                   class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 sm:w-auto">
                    Drucken
                </a>
                <a href="{{ route('transactions.cashbook.pdf', request()->query()) }}"
                   class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 sm:w-auto">
                    PDF
                </a>
            </div>
        @endif
    </div>

    @if(!$selectedCashAccount)
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm">
            <h2 class="text-xl font-semibold text-slate-900">Noch keine Kasse angelegt</h2>
            <p class="mt-2 text-sm text-slate-500">
                Lege zuerst ein Konto vom Typ <strong>Kasse</strong> an. Danach kann Clubano daraus ein echtes Kassenbuch führen.
            </p>
            <div class="mt-5">
                <a href="{{ route('accounts.create') }}"
                   class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800">
                    Kasse anlegen
                </a>
            </div>
        </div>
    @else
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('transactions.cashbook') }}" class="grid gap-4 md:grid-cols-2 2xl:grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))]">
                <div>
                    <label for="account" class="mb-1 block text-sm font-medium text-slate-600">Kasse</label>
                    <select id="account" name="account" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach($cashAccounts as $account)
                            <option value="{{ $account->id }}" @selected($selectedCashAccount && $selectedCashAccount->id === $account->id)>
                                {{ $account->number ? $account->number . ' - ' : '' }}{{ $account->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="year" class="mb-1 block text-sm font-medium text-slate-600">Jahr</label>
                    <select id="year" name="year" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @for($y = $currentYear; $y >= $currentYear - 10; $y--)
                            <option value="{{ $y }}" @selected($selectedYear === $y)>{{ $y }}</option>
                        @endfor
                    </select>
                </div>

                <div>
                    <label for="month" class="mb-1 block text-sm font-medium text-slate-600">Monat</label>
                    <select id="month" name="month" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Alle</option>
                        @foreach(range(1, 12) as $month)
                            <option value="{{ $month }}" @selected($selectedMonth === $month)>
                                {{ \Carbon\Carbon::createFromDate($selectedYear, $month, 1)->translatedFormat('F') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="movement" class="mb-1 block text-sm font-medium text-slate-600">Bewegung</label>
                    <select id="movement" name="movement" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach($movementLabels as $value => $label)
                            <option value="{{ $value }}" @selected($movement === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row md:col-span-2 2xl:col-span-1 2xl:col-start-4">
                    <button type="submit"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800 sm:flex-1">
                        Anwenden
                    </button>
                    <a href="{{ route('transactions.cashbook', ['account' => $selectedCashAccount->id]) }}"
                       class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 sm:w-auto">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Anfangsbestand</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($openingBalance, 2, ',', '.') }} €</div>
                <div class="mt-1 text-xs text-slate-500">Stand vor {{ $selectedMonthLabel }}</div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Bareinnahmen</div>
                <div class="mt-2 text-2xl font-semibold text-emerald-600">{{ number_format($periodIncome, 2, ',', '.') }} €</div>
                <div class="mt-1 text-xs text-slate-500">{{ $selectedMonthLabel }}</div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Barausgaben</div>
                <div class="mt-2 text-2xl font-semibold text-rose-600">{{ number_format($periodExpense, 2, ',', '.') }} €</div>
                <div class="mt-1 text-xs text-slate-500">{{ $selectedMonthLabel }}</div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Bestand nach Zeitraum</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($closingBalance, 2, ',', '.') }} €</div>
                <div class="mt-1 text-xs text-slate-500">Aktive Kasse: {{ $selectedCashAccount->name }}</div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 shadow-sm">
            Das Kassenbuch nutzt dieselben Buchungen wie der allgemeine Finanzbereich. Es ist also keine zweite Datenhaltung, sondern die geführte Sicht auf alle Bewegungen eurer Kasse.
            Offene Buchungen werden bereits mitgerechnet. Der Status zeigt nur, ob eine Bewegung schon abgeschlossen oder noch in Prüfung ist.
        </div>

        @if($missingReceiptCount > 0)
            <div class="rounded-2xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-900 shadow-sm">
                {{ $missingReceiptCount }} Kassenbuchung{{ $missingReceiptCount === 1 ? '' : 'en' }} im aktuellen Zeitraum haben keinen externen Beleg.
                Clubano-Rechnungen sind dabei bereits ausgenommen.
            </div>
        @endif

        <form method="POST" action="{{ route('transactions.finalize-selected') }}" class="space-y-4">
            @csrf
            @if($hasDraftTransactions)
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <p class="text-sm text-slate-600">
                            Offene Kassenbuchungen koennen erst noch geprueft und dann gesammelt abgeschlossen werden.
                        </p>
                        <button type="submit"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800 lg:w-auto">
                            Markierte abschließen
                        </button>
                    </div>
                </div>
            @endif

            <div class="hidden rounded-2xl border border-slate-200 bg-white shadow-sm 2xl:block">
                <div class="grid grid-cols-[64px_112px_minmax(0,1.45fr)_minmax(0,1fr)_minmax(0,0.95fr)_140px] gap-4 border-b border-slate-200 px-5 py-4 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
                    <div>Mark.</div>
                    <div>Datum</div>
                    <div>Vorgang</div>
                    <div>Status & Details</div>
                    <div>Gegenkonto</div>
                    <div class="text-right">Zahlen</div>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($transactions as $transaction)
                        @php
                            $isIncome = $transaction->cash_delta > 0;
                            $badgeClasses = $isIncome
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                : 'border-rose-200 bg-rose-50 text-rose-700';
                        @endphp
                        <article class="grid grid-cols-[64px_112px_minmax(0,1.45fr)_minmax(0,1fr)_minmax(0,0.95fr)_140px] gap-4 px-5 py-5 transition hover:bg-slate-50/80">
                            <div class="pt-1">
                                @if($transaction->status === 'entwurf' && !$transaction->isCancelled())
                                    <input type="checkbox" name="transaction_ids[]" value="{{ $transaction->id }}" class="rounded border-slate-300 text-slate-900 focus:ring-slate-800">
                                @else
                                    <span class="text-xs text-slate-300">—</span>
                                @endif
                            </div>

                            <div>
                                <div class="font-semibold text-slate-900">{{ $transaction->date->format('d.m.Y') }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $selectedMonthLabel }}</div>
                            </div>

                            <div class="min-w-0">
                                <div class="flex items-start gap-3">
                                    <span class="inline-flex shrink-0 rounded-full border px-2.5 py-1 text-xs font-medium {{ $badgeClasses }}">
                                        {{ $transaction->cash_label }}
                                    </span>
                                    <div class="min-w-0">
                                        <div class="font-semibold leading-6 text-slate-900">{{ $transaction->description }}</div>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600">
                                                {{ $transaction->tax_area }}
                                            </span>
                                            @if($transaction->hasOwnReceipt())
                                                <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
                                                    Eigenbeleg
                                                </span>
                                            @elseif($transaction->hasContractReceipt())
                                                <span class="inline-flex rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">
                                                    Vertrag/Dauerbeleg
                                                </span>
                                            @elseif($transaction->receipt_file)
                                                <span class="inline-flex rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                                                    Beleg vorhanden
                                                </span>
                                            @elseif($transaction->hasSystemReceipt())
                                                <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                                    Clubano-Rechnung
                                                </span>
                                            @else
                                                <span class="inline-flex rounded-full border border-violet-200 bg-violet-50 px-2.5 py-1 text-xs font-medium text-violet-700">
                                                    Beleg fehlt
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="min-w-0 space-y-3">
                                <div>
                                    @if($transaction->isCancelled())
                                        <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">Storno</span>
                                    @elseif($transaction->status === 'abgeschlossen')
                                        <div class="space-y-1">
                                            <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Abgeschlossen</span>
                                            @if($transaction->finalized_at)
                                                <div class="text-xs leading-5 text-slate-500">
                                                    {{ $transaction->finalized_at->format('d.m.Y H:i') }}
                                                    @if($transaction->finalizer)
                                                        · {{ $transaction->finalizer->name }}
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <div class="space-y-1">
                                            <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700">Offen</span>
                                            <div class="text-xs leading-5 text-slate-500">Schon im Bestand, aber noch nicht abgeschlossen</div>
                                        </div>
                                    @endif
                                </div>

                                <dl class="space-y-2 text-sm">
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Beleg-Nr.</dt>
                                        <dd class="mt-1 break-words text-slate-700">
                                            @if($transaction->hasOwnReceipt())
                                                Eigenbeleg
                                            @elseif($transaction->hasContractReceipt())
                                                Vertrag/Dauerbeleg
                                            @elseif($transaction->receipt_number)
                                                {{ $transaction->receipt_number }}
                                            @elseif($transaction->hasSystemReceipt())
                                                Clubano-Rechnung
                                            @else
                                                Beleg fehlt
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Erfasst von</dt>
                                        <dd class="mt-1 break-words text-slate-700">
                                            <div class="font-medium text-slate-800">{{ $transaction->creator?->name ?? 'Unbekannt' }}</div>
                                            <div class="mt-1 text-xs text-slate-500">
                                                {{ optional($transaction->created_at)->format('d.m.Y H:i') ?: '—' }}
                                            </div>
                                        </dd>
                                    </div>
                                </dl>

                                <div class="flex flex-wrap gap-2">
                                    @if(!$transaction->hasAnyReceipt())
                                        <a href="{{ route('transactions.own-receipt', $transaction) }}"
                                           class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 transition hover:border-amber-300 hover:bg-amber-100">
                                            Eigenbeleg
                                        </a>
                                    @endif
                                    @if($transaction->status === 'entwurf' && !$transaction->isCancelled())
                                        <a href="{{ route('transactions.edit', $transaction) }}"
                                           class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 transition hover:border-blue-300 hover:bg-blue-100">
                                            Bearbeiten
                                        </a>
                                        <button type="submit"
                                                formaction="{{ route('transactions.finalize', $transaction) }}"
                                                class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-100">
                                            Abschließen
                                        </button>
                                    @elseif(!$transaction->isCancelled())
                                        <a href="{{ route('transactions.cancel', $transaction) }}"
                                           class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:border-rose-300 hover:bg-rose-100">
                                            Stornieren
                                        </a>
                                    @else
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-500">
                                            Stornobuchung
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="min-w-0">
                                <div class="break-words font-semibold text-slate-900">{{ $transaction->counter_account->name ?? '—' }}</div>
                                <div class="mt-1 text-sm text-slate-500">Gegenkonto der Kassenbewegung</div>
                            </div>

                            <div class="space-y-3 text-right">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Bewegung</div>
                                    <div class="mt-1 font-mono text-lg font-semibold {{ $isIncome ? 'text-emerald-700' : 'text-rose-700' }}">
                                        {{ $isIncome ? '+' : '−' }} {{ number_format(abs((float) $transaction->cash_delta), 2, ',', '.') }} €
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Bestand danach</div>
                                    <div class="mt-1 font-mono text-sm font-semibold text-slate-900">
                                        {{ number_format((float) $transaction->cash_balance, 2, ',', '.') }} €
                                    </div>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="px-5 py-12 text-center text-sm text-slate-500">
                            Für diesen Zeitraum gibt es noch keine Kassenbewegungen.
                        </div>
                    @endforelse
                </div>
            </div>

        <div class="space-y-4 2xl:hidden">
            @forelse($transactions as $transaction)
                @php $isIncome = $transaction->cash_delta > 0; @endphp
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-slate-900">{{ $transaction->date->format('d.m.Y') }}</div>
                            <div class="mt-1 break-words text-xs text-slate-500">{{ $transaction->cash_label }}</div>
                        </div>
                        <span class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-medium {{ $isIncome ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                            {{ $isIncome ? 'Einnahme' : 'Ausgabe' }}
                        </span>
                    </div>

                    <div class="mt-3 break-words text-base font-semibold text-slate-900">{{ $transaction->description }}</div>
                    <div class="mt-1 break-words text-sm text-slate-500">Gegenkonto: {{ $transaction->counter_account->name ?? '—' }}</div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @if($transaction->hasOwnReceipt())
                            <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
                                Eigenbeleg
                            </span>
                        @elseif($transaction->hasContractReceipt())
                            <span class="inline-flex rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">
                                Vertrag/Dauerbeleg
                            </span>
                        @elseif($transaction->receipt_file)
                            <span class="inline-flex rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                                Beleg vorhanden
                            </span>
                        @elseif($transaction->hasSystemReceipt())
                            <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                Clubano-Rechnung
                            </span>
                        @else
                            <span class="inline-flex rounded-full border border-violet-200 bg-violet-50 px-2.5 py-1 text-xs font-medium text-violet-700">
                                Beleg fehlt
                            </span>
                        @endif
                    </div>

                    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            @if($transaction->isCancelled())
                                <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">Storno</span>
                            @elseif($transaction->status === 'abgeschlossen')
                                <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Abgeschlossen</span>
                            @else
                                <div class="space-y-1">
                                    <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700">Offen</span>
                                    <div class="text-xs text-slate-500">Schon im Bestand, aber noch nicht abgeschlossen</div>
                                </div>
                            @endif
                        </div>
                        @if($transaction->status === 'entwurf' && !$transaction->isCancelled())
                            <label class="inline-flex items-center gap-2 text-xs text-slate-500">
                                <input type="checkbox" name="transaction_ids[]" value="{{ $transaction->id }}" class="rounded border-slate-300 text-slate-900 focus:ring-slate-800">
                                Markieren
                            </label>
                        @endif
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 rounded-2xl bg-slate-50 p-4 text-sm sm:grid-cols-2">
                        <div class="min-w-0">
                            <div class="text-slate-500">Beleg-Nr.</div>
                            <div class="mt-1 break-words font-medium text-slate-900">
                            @if($transaction->hasOwnReceipt())
                                Eigenbeleg
                            @elseif($transaction->hasContractReceipt())
                                Vertrag/Dauerbeleg
                            @elseif($transaction->receipt_number)
                                {{ $transaction->receipt_number }}
                            @elseif($transaction->hasSystemReceipt())
                                Clubano-Rechnung
                                @else
                                    Beleg fehlt
                                @endif
                            </div>
                        </div>
                        <div class="min-w-0">
                            <div class="text-slate-500">Bestand danach</div>
                            <div class="mt-1 break-words font-semibold text-slate-900">{{ number_format((float) $transaction->cash_balance, 2, ',', '.') }} €</div>
                        </div>
                        <div class="min-w-0">
                            <div class="text-slate-500">{{ $isIncome ? 'Einnahme' : 'Ausgabe' }}</div>
                            <div class="mt-1 break-words font-semibold {{ $isIncome ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ number_format(abs((float) $transaction->cash_delta), 2, ',', '.') }} €
                            </div>
                        </div>
                        <div class="min-w-0">
                            <div class="text-slate-500">Erfasst von</div>
                            <div class="mt-1 break-words font-medium text-slate-900">{{ $transaction->creator?->name ?? 'Unbekannt' }}</div>
                            <div class="text-xs text-slate-500">{{ optional($transaction->created_at)->format('d.m.Y H:i') ?: '—' }}</div>
                        </div>
                    </div>

                    @if($transaction->status === 'entwurf' && !$transaction->isCancelled())
                        <div class="mt-4 flex flex-col gap-2 text-sm sm:flex-row sm:items-center">
                            @if(!$transaction->hasAnyReceipt())
                                <a href="{{ route('transactions.own-receipt', $transaction) }}" class="inline-flex items-center justify-center rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 font-medium text-amber-700 hover:bg-amber-100 hover:text-amber-900">Eigenbeleg</a>
                            @endif
                            <a href="{{ route('transactions.edit', $transaction) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-3 py-2 font-medium text-blue-700 hover:bg-slate-50 hover:text-blue-900">Bearbeiten</a>
                            <button type="submit" formaction="{{ route('transactions.finalize', $transaction) }}" class="inline-flex items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 font-medium text-emerald-700 hover:bg-emerald-100 hover:text-emerald-900">
                                Abschließen
                            </button>
                        </div>
                    @elseif(!$transaction->isCancelled())
                        <div class="mt-4 flex flex-col gap-2 text-sm sm:flex-row sm:items-center">
                            @if(!$transaction->hasAnyReceipt())
                                <a href="{{ route('transactions.own-receipt', $transaction) }}" class="inline-flex items-center justify-center rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 font-medium text-amber-700 hover:bg-amber-100 hover:text-amber-900">Eigenbeleg</a>
                            @endif
                            <a href="{{ route('transactions.cancel', $transaction) }}" class="inline-flex items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 font-medium text-rose-700 hover:bg-rose-100 hover:text-rose-900">Stornieren</a>
                        </div>
                    @endif
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500 shadow-sm">
                    Für diesen Zeitraum gibt es noch keine Kassenbewegungen.
                </div>
            @endforelse
        </div>

        @if($transactions->hasPages())
            <div>
                {{ $transactions->links() }}
            </div>
        @endif
        </form>
    @endif
</div>
@endsection
