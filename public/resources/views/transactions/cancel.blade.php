@extends('layouts.app')

@section('content')
<div class="max-w-xl mx-auto mt-10 bg-white p-6 rounded shadow-sm border border-gray-200">
    <h1 class="text-2xl font-semibold mb-6 text-gray-800">Buchung stornieren</h1>

    <div class="mb-6 text-gray-700">
        <p><strong>Buchungsdatum:</strong> {{ \Carbon\Carbon::parse($transaction->date)->format('d.m.Y') }}</p>
        <p><strong>Beschreibung:</strong> {{ $transaction->description }}</p>
        <p><strong>Betrag:</strong> {{ number_format($transaction->amount, 2, ',', '.') }} €</p>
    </div>

    <form method="POST" action="{{ route('transactions.cancel.store', $transaction) }}" class="space-y-6">
        @csrf

        <div>
            <label for="reason" class="block text-sm font-medium text-gray-700">Grund der Stornierung</label>
            <textarea id="reason" name="reason" rows="4" required
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"></textarea>
        </div>

        <div class="flex justify-between items-center">
            <a href="{{ route('transactions.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 hover:bg-gray-300">
                Abbrechen
            </a>

            <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                Buchung stornieren
            </button>
        </div>
    </form>
</div>
@endsection
