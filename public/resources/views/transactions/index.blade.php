@extends('layouts.app')

@section('title', 'Buchungen')

@section('content')
    <div class="space-y-6">
        <h1 class="text-2xl font-bold text-gray-800">🧾 Buchungsübersicht</h1>

        <div class="flex justify-between items-center">
            <p class="text-sm text-gray-600">
                Alle Buchungen im Überblick
            </p>
            <a href="{{ route('transactions.create') }}"
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700 transition">
                ➕ Neue Buchung
            </a>
        </div>

        <div class="overflow-auto bg-white rounded shadow ring-1 ring-gray-200">
            <table class="min-w-full text-sm text-left">
                <thead class="bg-gray-100 text-xs font-semibold text-gray-600 uppercase">
                    <tr>
                        <th class="px-4 py-3">Datum</th>
                        <th class="px-4 py-3">Beleg-Nr.</th>
                        <th class="px-4 py-3">Beschreibung</th>
                        <th class="px-4 py-3">Von-Konto</th>
                        <th class="px-4 py-3">Nach-Konto</th>
                        <th class="px-4 py-3 text-right">Betrag</th>
                        <th class="px-4 py-3">Beleg</th>
                        <th class="px-4 py-3">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                        <tr class="{{ $loop->odd ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50 transition">
                            <td class="px-4 py-3">{{ \Carbon\Carbon::parse($transaction->date)->format('d.m.Y') }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $transaction->receipt_number ?? '–' }}</td>
                            <td class="px-4 py-3">{{ $transaction->description }}</td>
                            <td class="px-4 py-3">{{ $transaction->account_from->name ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $transaction->account_to->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ number_format($transaction->amount, 2, ',', '.') }} €</td>
                            <td class="px-4 py-3 text-center">
                                @if($transaction->receipt_file)
                                    <a href="{{ route('receipts.show', $transaction->receipt_file) }}"
                                       target="_blank" title="Beleg ansehen">
                                        📎
                                    </a>
                                @else
                                    <span class="text-gray-400">–</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 space-x-2">
                                <a href="{{ route('transactions.cancel', $transaction) }}"
                                   class="text-red-600 hover:underline"
                                   onclick="return confirm('Buchung wirklich stornieren? Du wirst eine Begründung eingeben müssen.');">
                                   Stornieren
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-3 text-center text-gray-500">
                                Keine Buchungen vorhanden.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
