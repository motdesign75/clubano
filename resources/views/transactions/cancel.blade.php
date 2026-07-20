@extends('layouts.app')

@section('content')
<div class="mx-auto mt-6 max-w-2xl px-4 sm:mt-10 sm:px-6">
    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <h1 class="mb-3 text-2xl font-semibold tracking-tight text-slate-900">Buchung stornieren</h1>
    <p class="mb-6 text-sm leading-6 text-slate-500">Eine Stornierung erzeugt eine saubere Gegenbuchung. So bleibt die Geschichte richtig und nachvollziehbar.</p>

    <div class="mb-6 grid gap-3 rounded-2xl bg-slate-50 p-4 text-sm text-slate-700 sm:grid-cols-3">
        <p><strong>Buchungsdatum:</strong><br>{{ \Carbon\Carbon::parse($transaction->date)->format('d.m.Y') }}</p>
        <p><strong>Beschreibung:</strong><br>{{ $transaction->description }}</p>
        <p><strong>Betrag:</strong><br>{{ number_format($transaction->amount, 2, ',', '.') }} €</p>
    </div>

    <form method="POST" action="{{ route('transactions.cancel.store', $transaction) }}" class="space-y-6">
        @csrf

        <div>
            <label for="reason" class="block text-sm font-medium text-slate-700">Grund der Stornierung</label>
            <textarea id="reason" name="reason" rows="4" required
                      class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"></textarea>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('transactions.index') }}"
               class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto">
                Abbrechen
            </a>

            <button type="submit"
                    class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 sm:w-auto">
                Buchung stornieren
            </button>
        </div>
    </form>
    </div>
</div>
@endsection
