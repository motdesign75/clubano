@extends('layouts.app')

@section('title', 'Buchungen')
@section('help-key', 'transactions.index')

@section('content')
@php
    $currentYear = now()->year;
    $selectedYear = request('year', $currentYear);
    $selectedMonth = request('month', '');

    $monthLabel = $selectedMonth
        ? \Carbon\Carbon::createFromDate($selectedYear, (int) $selectedMonth, 1)->translatedFormat('F Y')
        : 'Gesamtes Jahr ' . $selectedYear;

    $incomeTotal = $summary['income_total'] ?? 0;
    $expenseTotal = $summary['expense_total'] ?? 0;
    $balance = $incomeTotal - $expenseTotal;
    $receiptCount = $summary['receipt_count'] ?? 0;
    $filteredCount = $summary['filtered_count'] ?? $transactions->total();
    $activeFilter = $filter ?? 'all';
    $hasDraftTransactions = collect($transactions->items())->contains(fn ($transaction) => $transaction->status === 'entwurf' && !$transaction->isCancelled());
    $draftCount = collect($transactions->items())->filter(fn ($transaction) => $transaction->status === 'entwurf' && !$transaction->isCancelled())->count();

    $filterChips = [
        'all' => ['label' => 'Alle', 'icon' => 'Alle', 'classes' => 'border-slate-200 bg-slate-100 text-slate-700'],
        'income' => ['label' => 'Einnahmen', 'icon' => 'Einnahmen', 'classes' => 'border-emerald-200 bg-emerald-50 text-emerald-700'],
        'expense' => ['label' => 'Ausgaben', 'icon' => 'Ausgaben', 'classes' => 'border-rose-200 bg-rose-50 text-rose-700'],
        'storno' => ['label' => 'Stornos', 'icon' => 'Stornos', 'classes' => 'border-amber-200 bg-amber-50 text-amber-700'],
        'missing_receipt' => ['label' => 'Beleg fehlt', 'icon' => 'Beleg fehlt', 'classes' => 'border-violet-200 bg-violet-50 text-violet-700'],
    ];
    $missingReceiptCount = $summary['missing_receipt_count'] ?? max($filteredCount - $receiptCount, 0);
    $activeFilterLabel = $filterChips[$activeFilter]['label'] ?? 'Alle';
@endphp

<div class="mx-auto max-w-7xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-3xl bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Buchungen</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $monthLabel }}</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    Einnahmen, Ausgaben und offene Prüfungen in einer ruhigen Übersicht. So wird schneller klar, was erledigt ist und was noch Aufmerksamkeit braucht.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-slate-200">
                    <div class="font-semibold text-white">{{ $filteredCount }} Buchungen im Blick</div>
                    <div class="mt-0.5 text-xs text-slate-300">{{ $draftCount }} offen, {{ $missingReceiptCount }} ohne Beleg</div>
                </div>

                <a href="{{ route('transactions.journal', ['filter' => $filter, 'year' => $selectedYear, 'month' => $selectedMonth]) }}"
                   target="_blank"
                   class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-white/15 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-white/10 sm:w-auto">
                    <span>Buchungsjournal</span>
                </a>

                <a href="{{ route('transactions.create') }}"
                   class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-white px-4 py-2.5 text-sm font-medium text-slate-950 shadow-sm transition hover:bg-slate-100 sm:w-auto">
                    <span aria-hidden="true">+</span>
                    <span>Neue Buchung</span>
                </a>
            </div>
        </div>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Einnahmen</div>
            <div class="mt-2 text-2xl font-semibold text-emerald-600">{{ number_format($incomeTotal, 2, ',', '.') }} €</div>
            <div class="mt-1 text-xs text-slate-500">{{ $monthLabel }}</div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Ausgaben</div>
            <div class="mt-2 text-2xl font-semibold text-rose-600">{{ number_format($expenseTotal, 2, ',', '.') }} €</div>
            <div class="mt-1 text-xs text-slate-500">{{ $monthLabel }}</div>
        </div>

        <div class="rounded-2xl border {{ $balance >= 0 ? 'border-slate-200 bg-white' : 'border-amber-200 bg-amber-50/60' }} p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Saldo</div>
            <div class="mt-2 text-2xl font-semibold {{ $balance >= 0 ? 'text-slate-900' : 'text-amber-700' }}">
                {{ number_format($balance, 2, ',', '.') }} €
            </div>
            <div class="mt-1 text-xs text-slate-500">Aus dem aktuell gefilterten Zeitraum</div>
        </div>

        <div class="rounded-2xl border {{ $draftCount > 0 || $missingReceiptCount > 0 ? 'border-rose-200 bg-rose-50/60' : 'border-slate-200 bg-white' }} p-5 shadow-sm">
            <div class="text-sm font-medium {{ $draftCount > 0 || $missingReceiptCount > 0 ? 'text-rose-700' : 'text-slate-500' }}">Prüfbedarf</div>
            <div class="mt-2 text-2xl font-semibold {{ $draftCount > 0 || $missingReceiptCount > 0 ? 'text-rose-900' : 'text-slate-900' }}">{{ $draftCount + $missingReceiptCount }}</div>
            <div class="mt-1 text-xs {{ $draftCount > 0 || $missingReceiptCount > 0 ? 'text-rose-700/80' : 'text-slate-500' }}">{{ $draftCount }} offen, {{ $missingReceiptCount }} ohne Beleg</div>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800 shadow-sm">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-semibold text-rose-800 shadow-sm">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800 shadow-sm">
            <div class="font-semibold">Der Import konnte nicht verarbeitet werden.</div>
            <ul class="mt-2 list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="max-w-2xl">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">DATEV-Import</div>
                <h2 class="mt-2 text-lg font-semibold text-slate-950">Buchungsstapel importieren</h2>
                <p class="mt-1 text-sm leading-6 text-slate-500">
                    Clubano ordnet Konto und Gegenkonto anhand der Kontonummer zu. Fehlende Konten werden als Importkonten angelegt und können danach sauber benannt werden.
                </p>
            </div>

            <form method="POST" action="{{ route('transactions.datev-import') }}" enctype="multipart/form-data" class="grid w-full gap-3 lg:grid-cols-[minmax(0,1fr)_180px_auto] xl:max-w-3xl">
                @csrf
                <input type="file" name="datev_file" accept=".csv,.txt"
                       class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 file:mr-4 file:rounded-full file:border-0 file:bg-white file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-800">
                <select name="status" class="rounded-2xl border-slate-200 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300">
                    <option value="entwurf">Als Entwurf prüfen</option>
                    <option value="abgeschlossen">Direkt abschließen</option>
                </select>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-full bg-slate-950 px-5 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Importieren
                </button>
            </form>
        </div>
    </section>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="space-y-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-3">
                    <div class="text-sm font-medium text-slate-500">Schnellfilter</div>
                    <p class="text-sm text-slate-500">
                        Grenze den Blick erst nach Richtung ein, dann nach Zeitraum oder Beleg.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach($filterChips as $value => $chip)
                        @php
                            $isActive = $activeFilter === $value;
                            $routeParams = ['year' => $selectedYear, 'month' => $selectedMonth];
                            if ($value !== 'all') {
                                $routeParams['filter'] = $value;
                            }
                        @endphp
                        <a href="{{ route('transactions.index', $routeParams) }}"
                           class="inline-flex items-center rounded-full border px-3 py-1.5 text-sm font-medium transition {{ $isActive ? $chip['classes'] : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                            {{ $chip['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            <form method="GET" action="{{ route('transactions.index') }}" class="grid gap-3 lg:grid-cols-2 xl:grid-cols-[minmax(16rem,1.4fr)_minmax(0,10rem)_minmax(0,10rem)_auto_auto] xl:items-end">
                @if($filter)
                    <input type="hidden" name="filter" value="{{ $filter }}">
                @endif

                <div class="lg:col-span-2 xl:col-span-1">
                    <label for="search" class="mb-1 block text-sm font-medium text-slate-600">Suche</label>
                    <input id="search"
                           type="text"
                           name="search"
                           value="{{ $search }}"
                           placeholder="Beschreibung, Beleg-Nr. oder Konto"
                           class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                </div>

                <div>
                    <label for="year" class="mb-1 block text-sm font-medium text-slate-600">Jahr</label>
                    <select id="year" name="year" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                        @for($y = $currentYear; $y >= $currentYear - 10; $y--)
                            <option value="{{ $y }}" @selected((string) $selectedYear === (string) $y)>{{ $y }}</option>
                        @endfor
                    </select>
                </div>

                <div>
                    <label for="month" class="mb-1 block text-sm font-medium text-slate-600">Monat</label>
                    <select id="month" name="month" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                        <option value="">Alle</option>
                        @foreach(range(1, 12) as $m)
                            <option value="{{ $m }}" @selected((string) $selectedMonth === (string) $m)>
                                {{ \Carbon\Carbon::createFromDate($selectedYear, $m, 1)->translatedFormat('F') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <button type="submit"
                        class="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800 xl:w-auto">
                    Anwenden
                </button>
                <a href="{{ route('transactions.index') }}"
                   class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 xl:w-auto">
                    Zurücksetzen
                </a>
            </form>
        </div>
    </div>

    <section class="space-y-4">
        <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Liste</div>
                <div class="mt-1 text-lg font-semibold text-slate-950">{{ $filteredCount }} Buchungen in {{ $activeFilterLabel }}</div>
                <div class="mt-1 text-sm text-slate-500">
                    @if($draftCount > 0 || $missingReceiptCount > 0)
                        {{ $draftCount }} offen, {{ $missingReceiptCount }} ohne Beleg. Am besten zuerst diese prüfen.
                    @else
                        Keine offenen Prüfhinweise im aktuellen Ausschnitt.
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-600">
                    {{ $monthLabel }}
                </span>
                @if($search)
                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-600">
                        Suche: {{ $search }}
                    </span>
                @endif
            </div>
        </div>

    <form method="POST" action="{{ route('transactions.finalize-selected') }}" class="space-y-4">
        @csrf
        @if($hasDraftTransactions)
            <div class="rounded-2xl border border-amber-200 bg-amber-50/60 px-4 py-3 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm text-amber-900">
                        {{ $draftCount }} offene Buchung{{ $draftCount === 1 ? '' : 'en' }} warten noch auf Pruefung und Abschluss.
                    </div>
                    <button type="submit"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800 sm:w-auto">
                        Markierte abschließen
                    </button>
                </div>
            </div>
        @endif

        @if($missingReceiptCount > 0)
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50/60 px-4 py-4 shadow-sm">
                <div class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,0.8fr)_auto] xl:items-end">
                    <div class="xl:col-span-5">
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-700">Sammelaktion</div>
                        <div class="mt-1 text-sm text-indigo-900">
                            Markiere Buchungen ohne Beleg und hinterlege einmalig den Vertrag oder Dauerbeleg, der fuer diese Zahlungen gilt.
                        </div>
                    </div>

                    <div>
                        <label for="bulk_contract_document_id" class="mb-1 block text-sm font-medium text-indigo-900">Gespeicherter Vertrag</label>
                        <select id="bulk_contract_document_id"
                                name="contract_document_id"
                                class="w-full rounded-xl border-indigo-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-300">
                            <option value="">Keinen gespeicherten Vertrag wählen</option>
                            @foreach($contractDocuments as $document)
                                <option value="{{ $document->id }}" @selected((string) old('contract_document_id') === (string) $document->id)>
                                    {{ $document->title }}{{ $document->document_date ? ' · ' . $document->document_date->format('d.m.Y') : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="bulk_contract_reference" class="mb-1 block text-sm font-medium text-indigo-900">Vertrag / Grundlage</label>
                        <input id="bulk_contract_reference"
                               name="contract_reference"
                               type="text"
                               value="{{ old('contract_reference') }}"
                               class="w-full rounded-xl border-indigo-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-300"
                               placeholder="Nur nötig ohne Auswahl">
                    </div>

                    <div>
                        <label for="bulk_contract_location" class="mb-1 block text-sm font-medium text-indigo-900">Ablageort</label>
                        <input id="bulk_contract_location"
                               name="contract_location"
                               type="text"
                               value="{{ old('contract_location') }}"
                               class="w-full rounded-xl border-indigo-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-300"
                               placeholder="z. B. Dokumente / Verträge">
                    </div>

                    <div>
                        <label for="bulk_contract_date" class="mb-1 block text-sm font-medium text-indigo-900">Vertragsdatum</label>
                        <input id="bulk_contract_date"
                               name="contract_date"
                               type="date"
                               value="{{ old('contract_date') }}"
                               class="w-full rounded-xl border-indigo-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-300">
                    </div>

                    <button type="submit"
                            formaction="{{ route('transactions.contract-receipt-selected') }}"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-800 xl:w-auto">
                        Markierte als Vertrag markieren
                    </button>
                </div>
            </div>
        @endif

    <div class="hidden rounded-2xl border border-slate-200 bg-white shadow-sm xl:block">
        <div class="grid grid-cols-[72px_minmax(0,2.25fr)_230px_220px_130px] gap-0 border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
            <div class="text-center">Mark.</div>
            <div>Buchung</div>
            <div>Status & Aktionen</div>
            <div>Konten</div>
            <div class="text-right">Betrag</div>
        </div>

        @forelse($transactions as $transaction)
            @php
                $isStorno = $transaction->isCancelled();
                $direction = 'neutral';
                $icon = '↔';
                $amountColor = 'text-slate-900';
                $amountPrefix = '';

                if ($isStorno) {
                    $direction = 'storno';
                    $icon = '↺';
                    $amountColor = 'text-amber-700';
                } elseif (in_array(optional($transaction->account_to)->type, ['bank', 'kasse'])) {
                    $direction = 'income';
                    $icon = '↓';
                    $amountColor = 'text-emerald-700';
                    $amountPrefix = '+ ';
                } elseif (in_array(optional($transaction->account_from)->type, ['bank', 'kasse'])) {
                    $direction = 'expense';
                    $icon = '↑';
                    $amountColor = 'text-rose-700';
                    $amountPrefix = '- ';
                }

                $directionBadge = match ($direction) {
                    'income' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                    'expense' => 'border-rose-200 bg-rose-50 text-rose-700',
                    'storno' => 'border-amber-200 bg-amber-50 text-amber-700',
                    default => 'border-slate-200 bg-slate-100 text-slate-600',
                };

                $taxAreaLabel = match ($transaction->tax_area) {
                    'ideell' => 'Ideell',
                    'zweckbetrieb' => 'Zweckbetrieb',
                    'vermoegensverwaltung' => 'Vermögensverwaltung',
                    'wirtschaftlich' => 'Wirtschaftlich',
                    default => ucfirst((string) $transaction->tax_area),
                };
            @endphp
            <article class="grid grid-cols-[72px_minmax(0,2.25fr)_230px_220px_130px] gap-0 border-b border-slate-100 px-4 py-4 transition hover:bg-slate-50/80 last:border-b-0">
                <div class="flex items-start justify-center pt-1">
                    @if(!$isStorno && ($transaction->status === 'entwurf' || !$transaction->hasAnyReceipt()))
                        <input type="checkbox"
                               name="transaction_ids[]"
                               value="{{ $transaction->id }}"
                               class="rounded border-slate-300 text-slate-900 focus:ring-slate-800">
                    @else
                        <span class="text-xs text-slate-300">—</span>
                    @endif
                </div>

                <div class="min-w-0 pr-6">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border text-sm font-semibold {{ $directionBadge }}">
                            {{ $icon }}
                        </span>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                <span>{{ \Carbon\Carbon::parse($transaction->date)->format('d.m.Y') }}</span>
                                <span>·</span>
                                <span>{{ $transaction->receipt_number ?: 'Ohne Beleg-Nr.' }}</span>
                            </div>
                            <div class="font-medium text-slate-900">{{ $transaction->description }}</div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium {{ $directionBadge }}">
                                    {{ $filterChips[$direction === 'neutral' ? 'all' : $direction]['label'] ?? 'Umbuchung' }}
                                </span>
                                <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600">
                                    {{ $taxAreaLabel }}
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
                                        Beleg
                                    </span>
                                @elseif($transaction->system_receipt_exists ?? false)
                                    <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                        Clubano-Rechnung
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full border border-violet-200 bg-violet-50 px-2.5 py-1 text-xs font-medium text-violet-700">
                                        Beleg fehlt
                                    </span>
                                @endif
                            </div>
                            @if($transaction->receiptEvidenceDetail())
                                <div class="mt-2 text-xs text-slate-500">{{ $transaction->receiptEvidenceDetail() }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="pr-6 text-sm text-slate-600">
                    @if($isStorno)
                        <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
                            Storno
                        </span>
                    @elseif($transaction->status === 'abgeschlossen')
                        <div class="space-y-1">
                            <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                Abgeschlossen
                            </span>
                            @if($transaction->finalized_at)
                                <div class="text-xs text-slate-500">
                                    {{ $transaction->finalized_at->format('d.m.Y H:i') }}
                                    @if($transaction->finalizer)
                                        · {{ $transaction->finalizer->name }}
                                    @endif
                                </div>
                            @endif
                        </div>
                    @else
                        <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700">
                            Offen
                        </span>
                    @endif

                    <div class="mt-3 text-xs text-slate-500">
                        <div>{{ $transaction->creator?->name ?? 'Unbekannt' }}</div>
                        <div class="mt-1">{{ optional($transaction->created_at)->format('d.m.Y H:i') ?: '—' }}</div>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        @if($transaction->receipt_file)
                            <div x-data="{ open: false }" class="inline-flex">
                                <button @click="open = true"
                                        class="inline-flex items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-xs font-medium text-blue-700 transition hover:bg-blue-100"
                                        title="Beleg öffnen">
                                    Beleg
                                </button>

                                <div x-show="open"
                                     x-transition
                                     class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4">
                                    <div class="flex h-full w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl md:h-[88vh]">
                                        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                                            <div>
                                                <div class="text-sm font-semibold text-slate-900">Belegvorschau</div>
                                                <div class="text-xs text-slate-500">{{ $transaction->description }}</div>
                                            </div>
                                            <button @click="open = false"
                                                    class="rounded-full p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900">
                                                ✕
                                            </button>
                                        </div>

                                        <div class="flex-1 bg-slate-100">
                                            @php
                                                $ext = strtolower(pathinfo($transaction->receipt_file, PATHINFO_EXTENSION));
                                                $url = route('receipts.show', $transaction->receipt_file);
                                            @endphp

                                            @if(in_array($ext, ['jpg', 'jpeg', 'png', 'webp']))
                                                <img src="{{ $url }}" class="h-full w-full object-contain" alt="Beleg">
                                            @elseif($ext === 'pdf')
                                                <iframe src="{{ $url }}#toolbar=1&navpanes=0&scrollbar=1" class="h-full w-full"></iframe>
                                            @else
                                                <div class="flex h-full items-center justify-center text-sm text-slate-500">
                                                    Keine Vorschau verfügbar
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($transaction->status === 'entwurf' && !$isStorno)
                            @if(!$transaction->hasAnyReceipt())
                                <a href="{{ route('transactions.own-receipt', $transaction) }}"
                                   class="inline-flex items-center justify-center rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-100 hover:text-amber-900">
                                    Eigenbeleg
                                </a>
                            @endif
                            <a href="{{ route('transactions.edit', $transaction) }}"
                               class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 hover:text-slate-900">
                                Bearbeiten
                            </a>
                            <button type="submit"
                                    formaction="{{ route('transactions.finalize', $transaction) }}"
                                class="inline-flex items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100 hover:text-emerald-900">
                                Abschließen
                            </button>
                        @elseif(!$isStorno)
                            @if(!$transaction->hasAnyReceipt())
                                <a href="{{ route('transactions.own-receipt', $transaction) }}"
                                   class="inline-flex items-center justify-center rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-100 hover:text-amber-900">
                                    Eigenbeleg
                                </a>
                            @endif
                            <a href="{{ route('transactions.cancel', $transaction) }}"
                               class="inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100 hover:text-rose-900">
                                Stornieren
                            </a>
                        @else
                            <span class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-medium text-slate-400">
                                Storniert
                            </span>
                        @endif
                    </div>
                </div>

                <div class="pr-6">
                    <div class="text-sm font-medium text-slate-800">{{ $transaction->account_from->name ?? '—' }}</div>
                    <div class="mt-1 text-xs text-slate-400">nach</div>
                    <div class="mt-1 text-sm font-medium text-slate-800">{{ $transaction->account_to->name ?? '—' }}</div>
                </div>

                <div class="text-right">
                    <div class="whitespace-nowrap font-mono text-base font-semibold {{ $amountColor }}">
                        {{ $amountPrefix }}{{ number_format($transaction->amount, 2, ',', '.') }} €
                    </div>
                </div>
            </article>
        @empty
            <div class="px-4 py-12 text-center">
                <div class="mx-auto max-w-md">
                    <div class="text-lg font-medium text-slate-900">Keine Buchungen im aktuellen Filter</div>
                    <div class="mt-2 text-sm text-slate-500">Passe Jahr, Monat oder Schnellfilter an oder erfasse eine neue Buchung.</div>
                </div>
            </div>
        @endforelse
    </div>

    @if($transactions->hasPages())
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-slate-500">
                    Zeige {{ $transactions->firstItem() }} bis {{ $transactions->lastItem() }} von {{ $transactions->total() }} Buchungen
                </div>
                <div>
                    {{ $transactions->onEachSide(1)->links() }}
                </div>
            </div>
        </div>
    @endif

    <div class="space-y-4 xl:hidden">
        @forelse($transactions as $transaction)
            @php
                $isStorno = $transaction->isCancelled();
                $direction = 'neutral';
                $amountColor = 'text-slate-900';
                $amountPrefix = '';

                if ($isStorno) {
                    $direction = 'storno';
                    $amountColor = 'text-amber-700';
                } elseif (in_array(optional($transaction->account_to)->type, ['bank', 'kasse'])) {
                    $direction = 'income';
                    $amountColor = 'text-emerald-700';
                    $amountPrefix = '+ ';
                } elseif (in_array(optional($transaction->account_from)->type, ['bank', 'kasse'])) {
                    $direction = 'expense';
                    $amountColor = 'text-rose-700';
                    $amountPrefix = '- ';
                }

                $directionBadge = match ($direction) {
                    'income' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                    'expense' => 'border-rose-200 bg-rose-50 text-rose-700',
                    'storno' => 'border-amber-200 bg-amber-50 text-amber-700',
                    default => 'border-slate-200 bg-slate-100 text-slate-600',
                };
            @endphp

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium {{ $directionBadge }}">
                                {{ $filterChips[$direction === 'neutral' ? 'all' : $direction]['label'] ?? 'Umbuchung' }}
                            </span>
                            <span class="text-xs text-slate-400">{{ \Carbon\Carbon::parse($transaction->date)->format('d.m.Y') }}</span>
                        </div>
                        <div class="mt-2 text-base font-semibold text-slate-900">{{ $transaction->description }}</div>
                    </div>
                    <div class="text-right font-mono text-base font-semibold {{ $amountColor }}">
                        {{ $amountPrefix }}{{ number_format($transaction->amount, 2, ',', '.') }} €
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between gap-3">
                    <div>
                        @if($isStorno)
                            <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">Storno</span>
                        @elseif($transaction->status === 'abgeschlossen')
                            <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Abgeschlossen</span>
                        @else
                            <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700">Offen</span>
                        @endif
                    </div>
                    @if(!$isStorno && ($transaction->status === 'entwurf' || !$transaction->hasAnyReceipt()))
                        <label class="inline-flex items-center gap-2 text-xs text-slate-500">
                            <input type="checkbox"
                                   name="transaction_ids[]"
                                   value="{{ $transaction->id }}"
                                   class="rounded border-slate-300 text-slate-900 focus:ring-slate-800">
                            Markieren
                        </label>
                    @endif
                </div>

                <div class="mt-4 space-y-3 text-sm text-slate-600">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-slate-400">Konten</div>
                        <div class="mt-1">{{ $transaction->account_from->name ?? '—' }} → {{ $transaction->account_to->name ?? '—' }}</div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        <span>{{ $transaction->receipt_number ?: 'Ohne Beleg-Nr.' }}</span>
                        <span>· {{ $transaction->creator?->name ?? 'Unbekannt' }}</span>
                        @if($transaction->hasOwnReceipt())
                            <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 font-medium text-amber-700">
                                Eigenbeleg
                            </span>
                        @elseif($transaction->hasContractReceipt())
                            <span class="inline-flex rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 font-medium text-indigo-700">
                                Vertrag/Dauerbeleg
                            </span>
                        @elseif($transaction->receipt_file)
                            <span class="inline-flex rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 font-medium text-blue-700">
                                Beleg vorhanden
                            </span>
                        @elseif($transaction->system_receipt_exists ?? false)
                            <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 font-medium text-emerald-700">
                                Clubano-Rechnung vorhanden
                            </span>
                        @else
                            <span class="inline-flex rounded-full border border-violet-200 bg-violet-50 px-2.5 py-1 font-medium text-violet-700">
                                Beleg fehlt
                            </span>
                        @endif
                    </div>
                    @if($transaction->receiptEvidenceDetail())
                        <div class="text-xs text-slate-500">{{ $transaction->receiptEvidenceDetail() }}</div>
                    @endif
                    @if($transaction->updated_by && $transaction->updated_by !== $transaction->created_by)
                        <div class="text-xs text-slate-500">
                            Zuletzt geändert von {{ $transaction->updater?->name ?? 'Unbekannt' }}
                            @if($transaction->updated_at)
                                am {{ $transaction->updated_at->format('d.m.Y H:i') }}
                            @endif
                        </div>
                    @endif
                </div>

                @if($transaction->status === 'entwurf' && !$isStorno)
                    <div class="mt-4 flex flex-col gap-2 text-sm sm:flex-row sm:items-center">
                        @if(!$transaction->hasAnyReceipt())
                            <a href="{{ route('transactions.own-receipt', $transaction) }}" class="inline-flex items-center justify-center rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 font-medium text-amber-700 hover:bg-amber-100 hover:text-amber-900">Eigenbeleg</a>
                        @endif
                        <a href="{{ route('transactions.edit', $transaction) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-3 py-2 font-medium text-slate-700 hover:bg-slate-50 hover:text-slate-900">Bearbeiten</a>
                        <button type="submit"
                                formaction="{{ route('transactions.finalize', $transaction) }}"
                                class="inline-flex items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 font-medium text-emerald-700 hover:bg-emerald-100 hover:text-emerald-900">
                            Abschließen
                        </button>
                    </div>
                @elseif(!$isStorno)
                    <div class="mt-4 flex flex-col gap-2 text-sm sm:flex-row sm:items-center">
                        @if(!$transaction->hasAnyReceipt())
                            <a href="{{ route('transactions.own-receipt', $transaction) }}" class="inline-flex items-center justify-center rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 font-medium text-amber-700 hover:bg-amber-100 hover:text-amber-900">Eigenbeleg</a>
                        @endif
                        <a href="{{ route('transactions.cancel', $transaction) }}" class="inline-flex items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 font-medium text-rose-700 hover:bg-rose-100 hover:text-rose-900">Stornieren</a>
                    </div>
                @endif

                @if($transaction->receipt_file)
                    <div x-data="{ open: false }" class="mt-4">
                        <button @click="open = true"
                                class="inline-flex items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-100">
                            Beleg ansehen
                        </button>

                        <div x-show="open"
                             x-transition
                             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4">
                            <div class="flex h-full w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl md:h-[88vh]">
                                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                                    <div>
                                        <div class="text-sm font-semibold text-slate-900">Belegvorschau</div>
                                        <div class="text-xs text-slate-500">{{ $transaction->description }}</div>
                                    </div>
                                    <button @click="open = false"
                                            class="rounded-full p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900">
                                        ✕
                                    </button>
                                </div>

                                <div class="flex-1 bg-slate-100">
                                    @php
                                        $mobileExt = strtolower(pathinfo($transaction->receipt_file, PATHINFO_EXTENSION));
                                        $mobileUrl = route('receipts.show', $transaction->receipt_file);
                                    @endphp

                                    @if(in_array($mobileExt, ['jpg', 'jpeg', 'png', 'webp']))
                                        <img src="{{ $mobileUrl }}" class="h-full w-full object-contain" alt="Beleg">
                                    @elseif($mobileExt === 'pdf')
                                        <iframe src="{{ $mobileUrl }}#toolbar=1&navpanes=0&scrollbar=1" class="h-full w-full"></iframe>
                                    @else
                                        <div class="flex h-full items-center justify-center text-sm text-slate-500">
                                            Keine Vorschau verfügbar
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500 shadow-sm">
                Keine Buchungen im aktuellen Filter.
            </div>
        @endforelse
    </div>
    </form>
</section>
</div>
@endsection
