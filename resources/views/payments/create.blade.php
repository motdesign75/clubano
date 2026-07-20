@extends('layouts.app')

@section('content')

<div class="mx-auto max-w-4xl px-4 py-6 sm:px-6">
    <div class="rounded-3xl bg-slate-950 px-6 py-6 text-white shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Zahlungseingang</div>
        <h1 class="mt-3 text-2xl font-semibold sm:text-3xl">Zahlung fuer {{ $invoice->invoice_number }} buchen</h1>
        <p class="mt-2 text-sm text-slate-300">
            Hier wird nicht nur der Geldeingang erfasst, sondern auch das passende Ertragskonto fuer die Buchung festgelegt.
        </p>
    </div>

    <form method="POST" action="{{ route('payments.store', $invoice) }}" class="mt-6 space-y-6">
        @csrf

        <div class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
            <section class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Rechnung und Verbuchung</h2>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Dokument</div>
                            <div class="mt-2 text-lg font-semibold text-slate-900">{{ $invoice->invoice_number }}</div>
                            <div class="mt-1 text-sm text-slate-500">{{ $invoice->getRecipientDisplayName() }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Offener Betrag</div>
                            <div class="mt-2 text-lg font-semibold text-slate-900">{{ number_format($invoice->getTotal(), 2, ',', '.') }} €</div>
                            <div class="mt-1 text-sm text-slate-500">Bitte bei Teilzahlungen den tatsaechlichen Geldeingang eintragen.</div>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="income_account_id" class="mb-1 block text-sm font-medium text-slate-700">Ertragskonto</label>
                            @if($invoice->incomeAccount)
                                <input type="hidden" name="income_account_id" value="{{ $invoice->incomeAccount->id }}">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <div class="font-semibold text-slate-900">
                                        {{ $invoice->incomeAccount->number ? $invoice->incomeAccount->number . ' - ' : '' }}{{ $invoice->incomeAccount->name }}
                                    </div>
                                    <div class="mt-1 text-sm text-slate-500">
                                        Steuerbereich: {{ $invoice->incomeAccount->taxAreaLabel }}
                                    </div>
                                </div>
                            @else
                                <select name="income_account_id" id="income_account_id" class="w-full rounded-2xl border-slate-300" required>
                                    <option value="">Bitte auswaehlen</option>
                                    @foreach($incomeAccounts as $acc)
                                        <option value="{{ $acc->id }}" @selected(old('income_account_id', $suggestedIncomeAccount?->id) == $acc->id)>
                                            {{ $acc->number ? $acc->number . ' - ' : '' }}{{ $acc->name }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                            @error('income_account_id')
                                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="account_id" class="mb-1 block text-sm font-medium text-slate-700">Geldkonto</label>
                            <select name="account_id" id="account_id" class="w-full rounded-2xl border-slate-300" required>
                                @foreach($accounts as $acc)
                                    <option value="{{ $acc->id }}" @selected(old('account_id') == $acc->id)>
                                        {{ $acc->number ? $acc->number . ' - ' : '' }}{{ $acc->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('account_id')
                                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="amount" class="mb-1 block text-sm font-medium text-slate-700">Betrag</label>
                            <input type="number" step="0.01" min="0" name="amount" id="amount" value="{{ old('amount', $invoice->getTotal()) }}" class="w-full rounded-2xl border-slate-300" required>
                            @error('amount')
                                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="payment_date" class="mb-1 block text-sm font-medium text-slate-700">Datum</label>
                            <input type="date" name="payment_date" id="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}" class="w-full rounded-2xl border-slate-300" required>
                            @error('payment_date')
                                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="note" class="mb-1 block text-sm font-medium text-slate-700">Notiz</label>
                            <input type="text" name="note" id="note" value="{{ old('note') }}" class="w-full rounded-2xl border-slate-300">
                            @error('note')
                                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </section>

            <aside class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Hinweis zur Vorauswahl</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        {{ $incomeAccountHint ?? 'Bitte das passende Ertragskonto waehlen.' }}
                    </p>
                    @if($suggestedIncomeAccount)
                        <div class="mt-4 rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-indigo-700">Empfohlen</div>
                            <div class="mt-2 font-semibold text-indigo-950">
                                {{ $suggestedIncomeAccount->number ? $suggestedIncomeAccount->number . ' - ' : '' }}{{ $suggestedIncomeAccount->name }}
                            </div>
                            <div class="mt-1 text-sm text-indigo-800">
                                Steuerbereich: {{ $suggestedIncomeAccount->taxAreaLabel }}
                            </div>
                        </div>
                    @endif
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <button class="w-full rounded-full bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700">
                        Zahlung speichern
                    </button>
                </div>
            </aside>
        </div>
    </form>
</div>

@endsection
