@extends('layouts.app')

@section('title', 'Bankumsätze')
@section('help-key', 'bank-imports.index')

@section('content')
@php
    $statusOptions = [
        'offen' => 'Offen',
        \App\Models\BankTransaction::STATUS_PENDING => 'Zuordnen',
        \App\Models\BankTransaction::STATUS_READY => 'Bereit',
        \App\Models\BankTransaction::STATUS_BOOKED => 'Gebucht',
        \App\Models\BankTransaction::STATUS_IGNORED => 'Ignoriert',
        \App\Models\BankTransaction::STATUS_DUPLICATE => 'Dubletten',
        'alle' => 'Alle',
    ];

    $statusClass = [
        \App\Models\BankTransaction::STATUS_PENDING => 'bg-amber-50 text-amber-800 border-amber-200',
        \App\Models\BankTransaction::STATUS_READY => 'bg-blue-50 text-blue-800 border-blue-200',
        \App\Models\BankTransaction::STATUS_BOOKED => 'bg-emerald-50 text-emerald-800 border-emerald-200',
        \App\Models\BankTransaction::STATUS_IGNORED => 'bg-slate-100 text-slate-600 border-slate-200',
        \App\Models\BankTransaction::STATUS_DUPLICATE => 'bg-violet-50 text-violet-800 border-violet-200',
    ];

    $accountGroups = $postingAccounts->groupBy('type');
    $accountOptions = $postingAccounts->mapWithKeys(fn ($account) => [
        $account->id => trim($account->number . ' · ' . $account->name),
    ]);
    $accountTypeLabels = [
        'bank' => 'Bank & Kasse',
        'kasse' => 'Bank & Kasse',
        'einnahme' => 'Einnahmen',
        'ausgabe' => 'Ausgaben',
    ];
@endphp

<div class="mx-auto max-w-7xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
    <datalist id="bank-import-account-options">
        @foreach($postingAccounts as $account)
            <option value="{{ $accountOptions[$account->id] }}" data-account-id="{{ $account->id }}"></option>
        @endforeach
    </datalist>

    <section class="rounded-3xl bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Bankumsätze</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Kontoauszug rein. Buchungen sauber raus.</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                    Importiere CAMT.053 oder CSV, prüfe die Umsätze in Ruhe und entscheide erst dann, welches Buchungskonto passt.
                </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-4 lg:min-w-[520px]">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Zuordnen</div>
                    <div class="mt-2 text-3xl font-semibold">{{ $summary['pending'] }}</div>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Bereit</div>
                    <div class="mt-2 text-3xl font-semibold">{{ $summary['ready'] }}</div>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Gebucht</div>
                    <div class="mt-2 text-3xl font-semibold">{{ $summary['booked'] }}</div>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Ignoriert</div>
                    <div class="mt-2 text-3xl font-semibold">{{ $summary['ignored'] }}</div>
                </div>
            </div>
        </div>
    </section>

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
            <div class="font-semibold">Bitte prüfe die Eingaben.</div>
            <ul class="mt-2 list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="grid gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)]">
        <form method="POST" action="{{ route('bank-imports.store') }}" enctype="multipart/form-data" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            <div class="flex items-start gap-4">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-slate-950 text-white">
                    <x-heroicon-o-arrow-down-tray class="size-6" />
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-slate-950">Umsätze importieren</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">
                        CAMT.053 ist die beste Wahl. CSV und MT940/MTA funktionieren als Übergang, wenn deine Bank keine CAMT-Datei anbietet.
                    </p>
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <div>
                    <label for="account_id" class="mb-1 block text-sm font-semibold text-slate-700">Bankkonto</label>
                    <select id="account_id" name="account_id" required class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                        <option value="">Konto wählen</option>
                        @foreach($bankAccounts as $account)
                            <option value="{{ $account->id }}" @selected(old('account_id') == $account->id)>
                                {{ $account->number }} · {{ $account->name }}
                            </option>
                        @endforeach
                    </select>
                    @if($bankAccounts->isEmpty())
                        <p class="mt-2 text-xs text-rose-700">Bitte zuerst unter Konten & Kassen ein Bankkonto anlegen.</p>
                    @endif
                </div>

                <div>
                    <label for="statement_file" class="mb-1 block text-sm font-semibold text-slate-700">Datei</label>
                    <input id="statement_file" name="statement_file" type="file" required class="block w-full rounded-2xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-800 hover:file:bg-slate-200">
                    <p class="mt-2 text-xs text-slate-500">Erlaubt: CAMT/XML, CSV, TXT, MT940, STA und MTA. PDF ist hier nicht geeignet, PDF bleibt nur Beleg.</p>
                </div>
            </div>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-xs leading-5 text-slate-500">
                    Dubletten werden anhand Datum, Betrag, Empfänger und Verwendungszweck erkannt.
                </div>
                <button type="submit" @disabled($bankAccounts->isEmpty()) class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-300">
                    Import prüfen
                </button>
            </div>
        </form>

        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-slate-950">Letzte Importe</h2>
            <div class="mt-4 space-y-3">
                @forelse($imports as $import)
                    <a href="{{ route('bank-imports.index', ['import' => $import->id, 'status' => 'alle']) }}" class="block rounded-2xl border border-slate-200 px-4 py-3 transition hover:border-slate-300 hover:bg-slate-50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-slate-950">{{ $import->filename }}</div>
                                <div class="mt-1 text-xs text-slate-500">
                                    {{ $import->format }} · {{ $import->account?->name }} · {{ $import->created_at->format('d.m.Y H:i') }}
                                </div>
                            </div>
                            <div class="shrink-0 text-right text-sm font-semibold text-slate-900">{{ $import->imported_count }}</div>
                        </div>
                        <div class="mt-2 text-xs text-slate-500">{{ $import->duplicate_count }} Dubletten, {{ $import->booked_count }} gebucht</div>
                    </a>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500">
                        Noch kein Bankumsatz importiert.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-950">Arbeitsliste</h2>
                    <p class="mt-1 text-sm text-slate-500">Erst Gegenkonto wählen, dann als Buchungsentwurf übernehmen.</p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <form method="GET" action="{{ route('bank-imports.index') }}" class="flex flex-col gap-3 sm:flex-row">
                        <select name="status" class="rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <select name="import" class="rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                            <option value="">Alle Importe</option>
                            @foreach($imports as $import)
                                <option value="{{ $import->id }}" @selected((string) $importId === (string) $import->id)>{{ $import->created_at->format('d.m.') }} · {{ $import->filename }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-2xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Anzeigen
                        </button>
                    </form>

                    <form id="bulk-book-form" method="POST" action="{{ route('bank-imports.transactions.bulk-book') }}">
                        @csrf
                    </form>
                    <button type="submit" form="bulk-book-form" class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
                        Ausgewählte buchen
                    </button>
                </div>
            </div>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($bankTransactions as $bankTransaction)
                @php
                    $transactionTitle = $bankTransaction->counterparty_name
                        ?: $bankTransaction->purpose
                        ?: $bankTransaction->counterparty_iban
                        ?: 'Bankumsatz ohne Beschreibung';
                    $showPurpose = filled($bankTransaction->purpose) && $bankTransaction->purpose !== $transactionTitle;
                    $selectedInvoiceId = old('invoice_id', $bankTransaction->receipt_meta['invoice_id'] ?? null);
                @endphp
                <article id="bank-transaction-{{ $bankTransaction->id }}" class="scroll-mt-24 p-5 target:bg-blue-50/70">
                    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(360px,430px)]">
                        <div class="min-w-0">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-semibold text-slate-950">{{ $bankTransaction->booking_date?->format('d.m.Y') }}</span>
                                        <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $statusClass[$bankTransaction->status] ?? 'border-slate-200 bg-slate-100 text-slate-700' }}">
                                            {{ $bankTransaction->statusLabel() }}
                                        </span>
                                        @if($bankTransaction->status === \App\Models\BankTransaction::STATUS_READY)
                                            <label class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-800">
                                                <input type="checkbox" form="bulk-book-form" name="bank_transaction_ids[]" value="{{ $bankTransaction->id }}" class="rounded border-blue-300 text-blue-600 focus:ring-blue-500">
                                                auswählen
                                            </label>
                                        @endif
                                    </div>
                                    <h3 class="mt-2 break-words text-lg font-semibold leading-6 text-slate-950">{{ $transactionTitle }}</h3>
                                    @if(blank($bankTransaction->counterparty_name))
                                        <div class="mt-2 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500">
                                            Name im Export nicht enthalten
                                        </div>
                                    @endif
                                    @if($showPurpose)
                                        <p class="mt-2 max-w-3xl break-words text-sm leading-6 text-slate-500">{{ $bankTransaction->purpose }}</p>
                                    @endif
                                </div>

                                <div class="shrink-0 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-left sm:text-right">
                                    <div class="text-xl font-semibold {{ $bankTransaction->amount >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                        {{ number_format((float) $bankTransaction->amount, 2, ',', '.') }} {{ $bankTransaction->currency }}
                                    </div>
                                    <div class="mt-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ $bankTransaction->amount >= 0 ? 'Eingang' : 'Ausgang' }}</div>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Bankkonto</div>
                                    <div class="mt-1 text-sm font-semibold text-slate-950">{{ $bankTransaction->account?->number }} · {{ $bankTransaction->account?->name }}</div>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">IBAN</div>
                                    <div class="mt-1 break-all text-sm text-slate-700">{{ $bankTransaction->counterparty_iban ?: 'Nicht enthalten' }}</div>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Beleg</div>
                                    <div class="mt-1 text-sm font-semibold text-slate-950">
                                        @if($bankTransaction->receipt_kind === 'vertrag')
                                            Vertrag/Dauerbeleg
                                        @elseif($bankTransaction->receipt_kind === 'system_invoice')
                                            Clubano-Rechnung
                                        @elseif($bankTransaction->receipt_file)
                                            Datei vorbereitet
                                        @else
                                            Offen
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <aside class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            @if($bankTransaction->status !== \App\Models\BankTransaction::STATUS_BOOKED)
                                <form method="POST" action="{{ route('bank-imports.transactions.update', $bankTransaction) }}" enctype="multipart/form-data" class="space-y-4">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="source_account_id" value="{{ $bankTransaction->account_id }}">

                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Gegenkonto</label>
                                        <input type="hidden"
                                               name="selected_account_id"
                                               value="{{ old('selected_account_id', $bankTransaction->selected_account_id) }}"
                                               data-account-id-input>
                                        <input type="search"
                                               value="{{ $accountOptions[old('selected_account_id', $bankTransaction->selected_account_id)] ?? '' }}"
                                               list="bank-import-account-options"
                                               autocomplete="off"
                                               placeholder="Kontonummer oder Name suchen"
                                               data-account-search
                                               class="w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                        <p class="mt-2 text-xs text-slate-500">Tippe z. B. „1200“, „Miete“ oder „Versicherung“.</p>
                                    </div>

                                    <details class="rounded-2xl border border-slate-200 bg-white p-3">
                                        <summary class="cursor-pointer text-sm font-semibold text-slate-800">Beleg, Rechnung oder Vertrag</summary>
                                        <div class="mt-3 space-y-3">
                                            <select name="receipt_kind" class="w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                                <option value="none" @selected(blank($bankTransaction->receipt_kind))>Kein Beleg hinterlegen</option>
                                                <option value="system_invoice" @selected($bankTransaction->receipt_kind === 'system_invoice')>Clubano-Rechnung als Beleg</option>
                                                <option value="upload" @selected($bankTransaction->receipt_kind === 'upload')>Einzelbeleg hochladen</option>
                                                <option value="vertrag" @selected($bankTransaction->receipt_kind === 'vertrag')>Vertrag / Dauerbeleg</option>
                                            </select>

                                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                                                <label class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.16em] text-emerald-700">Rechnung auswählen</label>
                                                <select name="invoice_id" class="w-full rounded-xl border-emerald-200 bg-white text-xs shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                                    <option value="">Keine Rechnung verknüpfen</option>
                                                    @foreach($invoices as $invoice)
                                                        @php
                                                            $total = (float) $invoice->getTotal();
                                                            $paid = (float) ($invoice->paid_amount ?? 0);
                                                            $remaining = max($total - $paid, 0);
                                                        @endphp
                                                        <option value="{{ $invoice->id }}" @selected((string) $selectedInvoiceId === (string) $invoice->id)>
                                                            {{ $invoice->invoice_number }} · {{ $invoice->recipient_name ?: 'Ohne Empfänger' }} · {{ number_format($remaining, 2, ',', '.') }} € offen
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <input name="receipt_file" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full rounded-xl border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-slate-700">

                                            <div class="grid gap-2">
                                                <input name="contract_reference" value="{{ old('contract_reference', $bankTransaction->receipt_meta['contract_reference'] ?? '') }}" placeholder="Vertrag / Grundlage" class="rounded-xl border-slate-300 bg-white text-xs shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                                <div class="grid gap-2 sm:grid-cols-2">
                                                    <input name="contract_location" value="{{ old('contract_location', $bankTransaction->receipt_meta['contract_location'] ?? '') }}" placeholder="Ablageort" class="rounded-xl border-slate-300 bg-white text-xs shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                                    <input name="contract_date" type="date" value="{{ old('contract_date', $bankTransaction->receipt_meta['contract_date'] ?? '') }}" class="rounded-xl border-slate-300 bg-white text-xs shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                                </div>
                                            </div>
                                        </div>
                                    </details>

                                    <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                        Zuordnung speichern
                                    </button>
                                </form>

                                @if($bankTransaction->status === \App\Models\BankTransaction::STATUS_READY)
                                    <form method="POST" action="{{ route('bank-imports.transactions.book', $bankTransaction) }}" class="mt-2">
                                        @csrf
                                        <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                                            Buchen
                                        </button>
                                    </form>
                                @endif

                                @if(! in_array($bankTransaction->status, [\App\Models\BankTransaction::STATUS_BOOKED, \App\Models\BankTransaction::STATUS_IGNORED], true))
                                    <form method="POST" action="{{ route('bank-imports.transactions.ignore', $bankTransaction) }}" class="mt-2">
                                        @csrf
                                        <button type="submit" class="inline-flex min-h-10 w-full items-center justify-center rounded-xl px-3 text-sm font-semibold text-slate-500 hover:bg-slate-100">
                                            Ignorieren
                                        </button>
                                    </form>
                                @endif
                            @else
                                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Gebuchte Zuordnung</div>
                                <div class="mt-2 text-sm font-semibold text-slate-950">{{ $bankTransaction->selectedAccount?->number }} · {{ $bankTransaction->selectedAccount?->name }}</div>
                                @if($bankTransaction->transaction)
                                    <a href="{{ route('transactions.edit', $bankTransaction->transaction) }}" class="mt-4 inline-flex min-h-10 w-full items-center justify-center rounded-xl bg-slate-950 px-3 text-sm font-semibold text-white hover:bg-slate-800">
                                        Buchung öffnen
                                    </a>
                                @endif
                            @endif
                        </aside>
                    </div>
                </article>
            @empty
                <div class="px-5 py-12 text-center">
                    <div class="text-sm font-semibold text-slate-900">Keine Bankumsätze in dieser Ansicht.</div>
                    <div class="mt-1 text-sm text-slate-500">Importiere eine CAMT.053- oder CSV-Datei oder ändere den Filter.</div>
                </div>
            @endforelse
        </div>

        @if($bankTransactions->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $bankTransactions->links() }}
            </div>
        @endif
    </section>
</div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('input', (event) => {
            const search = event.target.closest('[data-account-search]');

            if (! search) {
                return;
            }

            const form = search.closest('form');
            const hidden = form?.querySelector('[data-account-id-input]');
            const options = document.getElementById('bank-import-account-options')?.options ?? [];

            if (! hidden) {
                return;
            }

            hidden.value = '';

            for (const option of options) {
                if (option.value === search.value) {
                    hidden.value = option.dataset.accountId ?? '';
                    return;
                }
            }
        });
    </script>
@endpush
