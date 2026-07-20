@extends('layouts.app')

@section('title', 'EÜR')

@section('content')
@php
    $startLabel = \Carbon\Carbon::parse($start)->format('d.m.Y');
    $endLabel = \Carbon\Carbon::parse($end)->format('d.m.Y');
    $periodLabel = $startLabel . ' bis ' . $endLabel;
    $missingReceiptCount = $transactions->filter(fn ($transaction) => !$transaction->hasAnyReceipt())->count();
@endphp

<div class="mx-auto max-w-7xl space-y-8">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900">EÜR</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-500">
                Interne Steuerbereichsauswertung für euren Verein. Die Ansicht gruppiert Buchungen nach ideellem Bereich, Zweckbetrieb, Vermögensverwaltung und wirtschaftlichem Geschäftsbetrieb.
            </p>
        </div>
    </div>

    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900 shadow-sm">
        <div class="font-semibold">Wichtiger Hinweis</div>
        <div class="mt-1">
            Diese EÜR-Seite ist eine gut lesbare interne Auswertung nach Steuerbereichen. Sie ersetzt noch nicht die amtlich vorgeschriebene Anlage EÜR nach dem veröffentlichten BMF-Datensatz.
        </div>
    </div>

    <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="text-sm font-medium text-slate-500">Zeitraum</div>
                <div class="mt-1 text-lg font-semibold text-slate-900">{{ $periodLabel }}</div>
                <div class="mt-1 text-xs text-slate-500">Grundlage für die nachfolgende Auswertung</div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div>
                    <label for="start" class="mb-1 block text-sm font-medium text-slate-600">Von</label>
                    <input id="start" type="date" name="start" value="{{ $start }}"
                           class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label for="end" class="mb-1 block text-sm font-medium text-slate-600">Bis</label>
                    <input id="end" type="date" name="end" value="{{ $end }}"
                           class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <button class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-slate-800">
                    Aktualisieren
                </button>
            </div>
        </div>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Betriebseinnahmen</div>
            <div class="mt-2 text-2xl font-semibold text-emerald-600">{{ number_format($totalIncome, 2, ',', '.') }} €</div>
            <div class="mt-1 text-xs text-slate-500">{{ $transactions->count() }} Buchungen im Zeitraum</div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Betriebsausgaben</div>
            <div class="mt-2 text-2xl font-semibold text-rose-600">{{ number_format($totalExpense, 2, ',', '.') }} €</div>
            <div class="mt-1 text-xs text-slate-500">Nach aktuell gepflegten Steuerbereichen</div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Überschuss / Fehlbetrag</div>
            <div class="mt-2 text-2xl font-semibold {{ $saldo >= 0 ? 'text-slate-900' : 'text-amber-700' }}">
                {{ number_format($saldo, 2, ',', '.') }} €
            </div>
            <div class="mt-1 text-xs text-slate-500">Einnahmen minus Ausgaben</div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Aktive Bereiche</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $activeAreaCount }}</div>
            <div class="mt-1 text-xs text-slate-500">von {{ $result->count() }} gepflegten Steuerbereichen</div>
        </div>
    </div>

    @if($missingReceiptCount > 0)
        <div class="rounded-2xl border border-violet-200 bg-violet-50 px-5 py-4 text-sm text-violet-900 shadow-sm">
            <div class="font-semibold">Belegprüfung empfohlen</div>
            <div class="mt-1">
                {{ $missingReceiptCount }} Buchung{{ $missingReceiptCount === 1 ? '' : 'en' }} in diesem Zeitraum haben keinen externen Beleg.
                Clubano-Rechnungen sind dabei bereits ausgenommen.
            </div>
        </div>
    @endif

    <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">Steuerbereich</th>
                    <th class="px-4 py-3 text-right font-semibold">Einnahmen</th>
                    <th class="px-4 py-3 text-right font-semibold">Ausgaben</th>
                    <th class="px-4 py-3 text-right font-semibold">Saldo</th>
                    <th class="px-4 py-3 text-right font-semibold">Buchungen</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                @foreach($result as $area => $row)
                    @php
                        $saldoColor = $row['saldo'] >= 0 ? 'text-slate-900' : 'text-amber-700';
                    @endphp
                    <tr class="hover:bg-slate-50/80">
                        <td class="px-4 py-4">
                            <div class="font-medium text-slate-900">{{ $row['label'] }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ str_replace('_', '-', $area) }}</div>
                        </td>
                        <td class="px-4 py-4 text-right font-medium text-emerald-700">{{ number_format($row['income'], 2, ',', '.') }} €</td>
                        <td class="px-4 py-4 text-right font-medium text-rose-700">{{ number_format($row['expense'], 2, ',', '.') }} €</td>
                        <td class="px-4 py-4 text-right font-semibold {{ $saldoColor }}">{{ number_format($row['saldo'], 2, ',', '.') }} €</td>
                        <td class="px-4 py-4 text-right text-slate-600">{{ $row['count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-slate-50 text-sm font-semibold text-slate-900">
                <tr>
                    <td class="px-4 py-4">Gesamt</td>
                    <td class="px-4 py-4 text-right text-emerald-700">{{ number_format($totalIncome, 2, ',', '.') }} €</td>
                    <td class="px-4 py-4 text-right text-rose-700">{{ number_format($totalExpense, 2, ',', '.') }} €</td>
                    <td class="px-4 py-4 text-right">{{ number_format($saldo, 2, ',', '.') }} €</td>
                    <td class="px-4 py-4 text-right">{{ $transactions->count() }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="space-y-4 md:hidden">
        @foreach($result as $row)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-base font-semibold text-slate-900">{{ $row['label'] }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ $row['count'] }} Buchungen</div>

                <div class="mt-4 space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Einnahmen</span>
                        <span class="font-medium text-emerald-700">{{ number_format($row['income'], 2, ',', '.') }} €</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Ausgaben</span>
                        <span class="font-medium text-rose-700">{{ number_format($row['expense'], 2, ',', '.') }} €</span>
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-100 pt-2 font-semibold">
                        <span>Saldo</span>
                        <span class="{{ $row['saldo'] >= 0 ? 'text-slate-900' : 'text-amber-700' }}">{{ number_format($row['saldo'], 2, ',', '.') }} €</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
