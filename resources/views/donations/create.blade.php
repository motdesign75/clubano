@extends('layouts.app')

@section('title', 'Spende erfassen')

@section('content')
<div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Spenden</div>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Spende erfassen</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-500">Erst die Zuwendung sauber erfassen. Die Bestätigung erstellst du anschließend im Detail.</p>
        </div>
        <a href="{{ route('donations.index') }}" class="inline-flex justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">Zur Übersicht</a>
    </div>

    <form method="POST" action="{{ route('donations.store') }}" class="space-y-6">
        @csrf

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">1. Spender</h2>
            <p class="mt-1 text-sm text-slate-500">Wähle ein Mitglied oder erfasse einen externen Spender.</p>

            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <div>
                    <label for="member_id" class="mb-1 block text-sm font-medium text-slate-600">Mitglied verbinden</label>
                    <select id="member_id" name="member_id" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                        <option value="">Kein Mitglied / externer Spender</option>
                        @foreach($members as $member)
                            <option value="{{ $member->id }}" @selected(old('member_id') == $member->id)>
                                {{ $member->organization ?: $member->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    <input type="checkbox" name="fill_from_member" value="1" class="rounded border-slate-300 text-slate-900 focus:ring-slate-900" @checked(old('fill_from_member', true))>
                    Daten aus dem Mitglied übernehmen
                </label>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <div>
                    <label for="donor_name" class="mb-1 block text-sm font-medium text-slate-600">Name des Spenders</label>
                    <input id="donor_name" name="donor_name" value="{{ old('donor_name') }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    @error('donor_name')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="donor_email" class="mb-1 block text-sm font-medium text-slate-600">E-Mail</label>
                    <input id="donor_email" type="email" name="donor_email" value="{{ old('donor_email') }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    @error('donor_email')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="donor_street" class="mb-1 block text-sm font-medium text-slate-600">Straße</label>
                    <input id="donor_street" name="donor_street" value="{{ old('donor_street') }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                </div>
                <div class="grid grid-cols-[8rem_minmax(0,1fr)] gap-3">
                    <div>
                        <label for="donor_zip" class="mb-1 block text-sm font-medium text-slate-600">PLZ</label>
                        <input id="donor_zip" name="donor_zip" value="{{ old('donor_zip') }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    </div>
                    <div>
                        <label for="donor_city" class="mb-1 block text-sm font-medium text-slate-600">Ort</label>
                        <input id="donor_city" name="donor_city" value="{{ old('donor_city') }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">2. Zuwendung</h2>
            <div class="mt-5 grid gap-4 lg:grid-cols-3">
                <div>
                    <label for="donated_at" class="mb-1 block text-sm font-medium text-slate-600">Datum</label>
                    <input id="donated_at" type="date" name="donated_at" value="{{ old('donated_at', now()->toDateString()) }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    @error('donated_at')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="amount" class="mb-1 block text-sm font-medium text-slate-600">Betrag</label>
                    <input id="amount" type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    @error('amount')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="payment_method" class="mb-1 block text-sm font-medium text-slate-600">Zahlungsart</label>
                    <select id="payment_method" name="payment_method" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                        <option value="">Bitte wählen</option>
                        <option value="ueberweisung" @selected(old('payment_method') === 'ueberweisung')>Überweisung</option>
                        <option value="bar" @selected(old('payment_method') === 'bar')>Bar</option>
                        <option value="lastschrift" @selected(old('payment_method') === 'lastschrift')>Lastschrift</option>
                        <option value="karte" @selected(old('payment_method') === 'karte')>Karte</option>
                        <option value="sonstiges" @selected(old('payment_method') === 'sonstiges')>Sonstiges</option>
                    </select>
                </div>
            </div>
            <div class="mt-4">
                <label for="purpose" class="mb-1 block text-sm font-medium text-slate-600">Zweck</label>
                <input id="purpose" name="purpose" value="{{ old('purpose') }}" placeholder="z. B. Jugendarbeit, Vereinsarbeit, Renovierung" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
            </div>
            <div class="mt-4">
                <label for="notes" class="mb-1 block text-sm font-medium text-slate-600">Interne Notiz</label>
                <textarea id="notes" name="notes" rows="3" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">{{ old('notes') }}</textarea>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">3. Buchhaltung</h2>
            <label class="mt-4 flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                <input type="checkbox" name="create_transaction" value="1" class="mt-0.5 rounded border-slate-300 text-slate-900 focus:ring-slate-900" @checked(old('create_transaction'))>
                <span>
                    <span class="block font-medium text-slate-950">Direkt als abgeschlossene Einnahme buchen</span>
                    <span class="mt-1 block text-slate-500">Clubano erstellt eine Buchung im ideellen Bereich.</span>
                </span>
            </label>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div>
                    <label for="cash_account_id" class="mb-1 block text-sm font-medium text-slate-600">Bank oder Kasse</label>
                    <select id="cash_account_id" name="cash_account_id" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                        <option value="">Bitte wählen</option>
                        @foreach($cashAccounts as $account)
                            <option value="{{ $account->id }}" @selected(old('cash_account_id') == $account->id)>{{ $account->number }} {{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="income_account_id" class="mb-1 block text-sm font-medium text-slate-600">Einnahmekonto</label>
                    <select id="income_account_id" name="income_account_id" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                        <option value="">Bitte wählen</option>
                        @foreach($incomeAccounts as $account)
                            <option value="{{ $account->id }}" @selected(old('income_account_id') == $account->id)>{{ $account->number }} {{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('donations.index') }}" class="inline-flex justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">Abbrechen</a>
            <button type="submit" class="inline-flex justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-slate-800">Spende speichern</button>
        </div>
    </form>
</div>
@endsection
