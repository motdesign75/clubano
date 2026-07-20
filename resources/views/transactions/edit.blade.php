@extends('layouts.app')

@section('title', 'Buchung bearbeiten')

@section('content')

@if ($errors->any())
    <div class="mx-auto mb-6 max-w-xl px-4 sm:px-0">
        <div class="bg-red-100 border border-red-300 text-red-700 p-4 rounded-xl shadow-sm">
            <ul class="list-disc pl-5 text-sm space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">

    <!-- 🔹 HEADER (kompakt & modern) -->
    <div class="mx-auto mb-6 max-w-6xl space-y-2">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
            Buchung bearbeiten
        </h1>
        <p class="text-sm text-slate-500">
            Aendere den Vorgang in Ruhe. Auf kleineren Bildschirmen bleibt der Beleg unter dem Formular, damit alles klar und lesbar bleibt.
        </p>
    </div>

    <!-- 🔥 LAYOUT -->
    <div class="mx-auto grid max-w-6xl gap-6 2xl:grid-cols-[minmax(0,540px)_minmax(0,1fr)] 2xl:gap-8">

        <!-- 🔹 FORM (JETZT SCHMAL & ANGENEHM) -->
        <div class="w-full">

            <form method="POST"
                  action="{{ route('transactions.update', $transaction) }}"
                  enctype="multipart/form-data"
                  class="space-y-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">

                @csrf
                @method('PUT')

                <!-- Basis -->
                <div class="space-y-4">

                    <div>
                        <label class="text-xs uppercase tracking-wide text-slate-500">Datum</label>
                        <input type="date" name="date"
                               value="{{ old('date', $transaction->date->format('Y-m-d')) }}"
                               class="mt-1 w-full rounded-xl border-gray-300 focus:ring-2 focus:ring-blue-500" />
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-wide text-slate-500">Beschreibung</label>
                        <input type="text" name="description"
                               value="{{ old('description', $transaction->description) }}"
                               class="mt-1 w-full rounded-xl border-gray-300 focus:ring-2 focus:ring-blue-500" />
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-wide text-slate-500">Betrag (€)</label>
                        <input type="number" name="amount"
                               value="{{ old('amount', $transaction->amount) }}"
                               step="0.01"
                               class="mt-1 w-full rounded-xl border-gray-300 focus:ring-2 focus:ring-blue-500" />
                    </div>

                </div>

                <!-- Kontierung -->
                <div class="space-y-4 border-t border-slate-200 pt-5">

                    <div>
                        <label class="text-xs uppercase tracking-wide text-slate-500">Von-Konto</label>
                        <select name="account_from_id"
                                class="mt-1 w-full rounded-xl border-gray-300 focus:ring-2 focus:ring-blue-500">
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}"
                                    {{ old('account_from_id', $transaction->account_from_id) == $account->id ? 'selected' : '' }}>
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-wide text-slate-500">Nach-Konto</label>
                        <select name="account_to_id"
                                class="mt-1 w-full rounded-xl border-gray-300 focus:ring-2 focus:ring-blue-500">
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}"
                                    {{ old('account_to_id', $transaction->account_to_id) == $account->id ? 'selected' : '' }}>
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-wide text-slate-500">Steuerbereich</label>
                        <select name="tax_area"
                                class="mt-1 w-full rounded-xl border-gray-300 focus:ring-2 focus:ring-blue-500">
                            <option value="ideell" {{ old('tax_area', $transaction->tax_area)=='ideell'?'selected':'' }}>Ideell</option>
                            <option value="zweckbetrieb" {{ old('tax_area', $transaction->tax_area)=='zweckbetrieb'?'selected':'' }}>Zweckbetrieb</option>
                            <option value="vermoegensverwaltung" {{ old('tax_area', $transaction->tax_area)=='vermoegensverwaltung'?'selected':'' }}>Vermögensverwaltung</option>
                            <option value="wirtschaftlich" {{ old('tax_area', $transaction->tax_area)=='wirtschaftlich'?'selected':'' }}>Wirtschaftlich</option>
                        </select>
                    </div>

                </div>

                <!-- Beleg -->
                <div class="space-y-2 border-t border-slate-200 pt-5">

                    <label class="text-xs uppercase tracking-wide text-slate-500">
                        Beleg
                    </label>

                    <input type="file"
                           name="receipt_file"
                           class="w-full text-sm" />

                    <div class="rounded-2xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-900">
                        Wenn kein externer Beleg vorliegt, kannst du stattdessen direkt einen Eigenbeleg erzeugen.
                        <div class="mt-3">
                            <a href="{{ route('transactions.own-receipt', $transaction) }}"
                               class="inline-flex items-center justify-center rounded-full border border-violet-200 bg-white px-4 py-2 text-sm font-medium text-violet-700 hover:bg-violet-100">
                                Eigenbeleg erstellen
                            </a>
                        </div>
                    </div>

                </div>

                <!-- Actions -->
                <div class="flex flex-col gap-3 pt-4 sm:flex-row sm:items-center sm:justify-between">

                    <a href="{{ route('transactions.index') }}"
                       class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-500 hover:bg-slate-50 hover:text-slate-700 sm:w-auto">
                        ← Zurück
                    </a>

                    <button type="submit"
                        class="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm text-white shadow-sm hover:bg-blue-700 sm:w-auto">
                        Speichern
                    </button>

                </div>

            </form>

        </div>

        <!-- 🔹 BELEG (BLEIBT GROSS = ARBEITSBEREICH) -->
        <div class="flex-1">

            <div class="flex h-[320px] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm sm:h-[520px] 2xl:h-[78vh]">

                <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3">
                    <span class="text-sm font-medium text-slate-600">
                        Beleg
                    </span>

                    @if($transaction->receipt_file)
                        <a href="{{ route('receipts.show', $transaction->receipt_file) }}"
                           target="_blank"
                           class="text-xs font-medium text-blue-600">
                            Öffnen
                        </a>
                    @endif
                </div>

                <div class="flex flex-1 items-center justify-center bg-slate-100">

                    @if($transaction->receipt_file)

                        @php
                            $ext = strtolower(pathinfo($transaction->receipt_file, PATHINFO_EXTENSION));
                            $url = route('receipts.show', $transaction->receipt_file);
                        @endphp

                        @if($ext === 'pdf')
                            <iframe src="{{ $url }}#zoom=page-width"
                                    class="w-full h-full"></iframe>
                        @else
                            <img src="{{ $url }}"
                                 class="max-w-full max-h-full object-contain">
                        @endif

                    @else
                        <div class="text-sm text-slate-400">
                            Kein Beleg vorhanden
                        </div>
                    @endif

                </div>

            </div>

        </div>

    </div>

</div>

@endsection
