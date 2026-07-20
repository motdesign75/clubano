@extends('layouts.sidebar')

@section('title', 'Einnahmen & Ausgaben')

@section('content')
<div class="max-w-6xl mx-auto space-y-10">
    <h1 class="text-3xl font-bold text-gray-800">📊 Einnahmen & Ausgaben</h1>

    {{-- Übersichtskarten --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div class="bg-gradient-to-br from-green-100 to-green-200 text-green-900 rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div class="text-3xl">💰</div>
                <div class="text-right">
                    <p class="text-sm font-medium">Gesamte Einnahmen</p>
                    <p class="text-2xl font-bold">{{ number_format($summary['total_income'], 2, ',', '.') }} €</p>
                </div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-red-100 to-red-200 text-red-900 rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div class="text-3xl">💸</div>
                <div class="text-right">
                    <p class="text-sm font-medium">Gesamte Ausgaben</p>
                    <p class="text-2xl font-bold">{{ number_format($summary['total_expense'], 2, ',', '.') }} €</p>
                </div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-blue-100 to-blue-200 text-blue-900 rounded-xl shadow-lg p-6">
            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium">Saldo gesamt</span>
                    <span class="text-2xl font-bold">{{ number_format($summary['saldo'], 2, ',', '.') }} €</span>
                </div>
                <div class="text-sm text-gray-800">
                    📅 Aktueller Monat: <strong>{{ number_format($summary['current']['saldo'], 2, ',', '.') }} €</strong><br>
                    📆 Vormonat: <strong>{{ number_format($summary['previous']['saldo'], 2, ',', '.') }} €</strong><br>
                    🔁 Veränderung:
                    @php
                        $diff = $summary['current']['saldo'] - $summary['previous']['saldo'];
                        $symbol = $diff > 0 ? '⬆️' : ($diff < 0 ? '⬇️' : '➡️');
                        $color = $diff > 0 ? 'text-green-700' : ($diff < 0 ? 'text-red-700' : 'text-gray-700');
                    @endphp
                    <span class="{{ $color }}">
                        {{ $symbol }} {{ number_format($diff, 2, ',', '.') }} €
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Zeitraum --}}
    <p class="text-sm text-gray-500 mt-2">
        Zeitraum: {{ \Carbon\Carbon::parse($start)->format('d.m.Y') }} – {{ \Carbon\Carbon::parse($end)->format('d.m.Y') }}
    </p>

    {{-- Diagramm --}}
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">📊 Monatsvergleich als Diagramm</h2>
        <canvas id="finanzChart" height="120"></canvas>
    </div>

    {{-- Buchungstabelle --}}
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">📋 Einzelne Buchungen</h2>
        <table class="w-full text-sm border border-gray-200 rounded">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="px-4 py-2 text-left">Datum</th>
                    <th class="px-4 py-2 text-left">Beschreibung</th>
                    <th class="px-4 py-2 text-left">Von</th>
                    <th class="px-4 py-2 text-left">Nach</th>
                    <th class="px-4 py-2 text-right">Betrag</th>
                </tr>
            </thead>
            <tbody>
                @forelse($summary['transactions'] as $transaction)
                    <tr class="border-t border-gray-200">
                        <td class="px-4 py-2">
                            {{ \Carbon\Carbon::parse($transaction->date)->format('d.m.Y') }}
                        </td>
                        <td class="px-4 py-2">{{ $transaction->description }}</td>
                        <td class="px-4 py-2">{{ $transaction->account_from->name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $transaction->account_to->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-right">
                            {{ number_format($transaction->amount, 2, ',', '.') }} €
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-gray-500 py-4">Keine Buchungen gefunden.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('finanzChart').getContext('2d');

    const finanzChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: {!! json_encode(array_map(
                fn($k) => \Carbon\Carbon::parse($k . '-01')->locale('de')->translatedFormat('M Y'),
                array_keys($summary['by_month']->toArray())
            )) !!},
            datasets: [
                {
                    label: 'Einnahmen',
                    backgroundColor: 'rgba(34, 197, 94, 0.7)',
                    data: {!! json_encode($summary['by_month']->pluck('income')->values()) !!}
                },
                {
                    label: 'Ausgaben',
                    backgroundColor: 'rgba(239, 68, 68, 0.7)',
                    data: {!! json_encode($summary['by_month']->pluck('expense')->values()) !!}
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: value => value + ' €'
                    }
                }
            }
        }
    });
</script>
@endpush
