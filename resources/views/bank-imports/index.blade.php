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
    $accountTypeLabels = [
        'bank' => 'Bank & Kasse',
        'kasse' => 'Bank & Kasse',
        'einnahme' => 'Einnahmen',
        'ausgabe' => 'Ausgaben',
    ];
@endphp

<div class="mx-auto max-w-7xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
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
                        CAMT.053 ist die beste Wahl. CSV funktioniert als Übergang, wenn deine Bank keine CAMT-Datei anbietet.
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
                    <input id="statement_file" name="statement_file" type="file" accept=".xml,.camt,.csv,.txt" required class="block w-full rounded-2xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-800 hover:file:bg-slate-200">
                    <p class="mt-2 text-xs text-slate-500">PDF ist hier nicht geeignet, PDF bleibt nur Beleg.</p>
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

        <div class="overflow-x-auto">
            <table class="min-w-[1100px] divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="w-12 px-5 py-3 text-left"></th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Umsatz</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Betrag</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Bankkonto</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Gegenkonto</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Status</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Aktion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($bankTransactions as $bankTransaction)
                        <tr class="align-top">
                            <td class="px-5 py-4">
                                @if($bankTransaction->status === \App\Models\BankTransaction::STATUS_READY)
                                    <input type="checkbox" form="bulk-book-form" name="bank_transaction_ids[]" value="{{ $bankTransaction->id }}" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                @endif
                            </td>
                            <td class="max-w-md px-5 py-4">
                                <div class="text-sm font-semibold text-slate-950">{{ $bankTransaction->booking_date?->format('d.m.Y') }}</div>
                                <div class="mt-1 text-sm text-slate-700">{{ $bankTransaction->counterparty_name ?: 'Ohne Namen' }}</div>
                                <div class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">{{ $bankTransaction->purpose ?: 'Kein Verwendungszweck' }}</div>
                                @if($bankTransaction->counterparty_iban)
                                    <div class="mt-1 text-xs text-slate-400">{{ $bankTransaction->counterparty_iban }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="text-sm font-semibold {{ $bankTransaction->amount >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                    {{ number_format((float) $bankTransaction->amount, 2, ',', '.') }} {{ $bankTransaction->currency }}
                                </div>
                                <div class="mt-1 text-xs text-slate-500">{{ $bankTransaction->amount >= 0 ? 'Eingang' : 'Ausgang' }}</div>
                            </td>
                            <td class="px-5 py-4 text-sm text-slate-700">
                                <div class="font-semibold text-slate-900">{{ $bankTransaction->account?->number }}</div>
                                <div>{{ $bankTransaction->account?->name }}</div>
                            </td>
                            <td class="w-80 px-5 py-4">
                                @if($bankTransaction->status !== \App\Models\BankTransaction::STATUS_BOOKED)
                                    <form method="POST" action="{{ route('bank-imports.transactions.update', $bankTransaction) }}" class="space-y-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="source_account_id" value="{{ $bankTransaction->account_id }}">
                                        <select name="selected_account_id" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                            <option value="">Gegenkonto wählen</option>
                                            @foreach($accountGroups as $type => $accounts)
                                                <optgroup label="{{ $accountTypeLabels[$type] ?? ucfirst((string) $type) }}">
                                                    @foreach($accounts as $account)
                                                        @continue($account->id === $bankTransaction->account_id)
                                                        <option value="{{ $account->id }}" @selected($bankTransaction->selected_account_id === $account->id)>
                                                            {{ $account->number }} · {{ $account->name }}
                                                        </option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-xl border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            Zuordnung speichern
                                        </button>
                                    </form>
                                @else
                                    <div class="text-sm font-semibold text-slate-900">{{ $bankTransaction->selectedAccount?->number }} · {{ $bankTransaction->selectedAccount?->name }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $statusClass[$bankTransaction->status] ?? 'border-slate-200 bg-slate-100 text-slate-700' }}">
                                    {{ $bankTransaction->statusLabel() }}
                                </span>
                                @if($bankTransaction->transaction)
                                    <a href="{{ route('transactions.edit', $bankTransaction->transaction) }}" class="mt-2 block text-xs font-semibold text-blue-700 hover:text-blue-900">
                                        Buchung öffnen
                                    </a>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex flex-col items-end gap-2">
                                    @if($bankTransaction->status === \App\Models\BankTransaction::STATUS_READY)
                                        <form method="POST" action="{{ route('bank-imports.transactions.book', $bankTransaction) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-xl bg-slate-950 px-3 text-xs font-semibold text-white hover:bg-slate-800">
                                                Buchen
                                            </button>
                                        </form>
                                    @endif

                                    @if(! in_array($bankTransaction->status, [\App\Models\BankTransaction::STATUS_BOOKED, \App\Models\BankTransaction::STATUS_IGNORED], true))
                                        <form method="POST" action="{{ route('bank-imports.transactions.ignore', $bankTransaction) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-xl px-3 text-xs font-semibold text-slate-500 hover:bg-slate-100">
                                                Ignorieren
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center">
                                <div class="text-sm font-semibold text-slate-900">Keine Bankumsätze in dieser Ansicht.</div>
                                <div class="mt-1 text-sm text-slate-500">Importiere eine CAMT.053- oder CSV-Datei oder ändere den Filter.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($bankTransactions->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $bankTransactions->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
