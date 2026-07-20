@extends('layouts.app')

@section('title', 'Körperschaftsteuer')

@section('content')
@php
    $startLabel = \Carbon\Carbon::parse($start)->format('d.m.Y');
    $endLabel = \Carbon\Carbon::parse($end)->format('d.m.Y');
    $periodLabel = $startLabel . ' bis ' . $endLabel;
    $missingReceiptCount = $relevantTransactions->filter(fn ($transaction) => !$transaction->hasAnyReceipt())->count();
@endphp

<div class="mx-auto max-w-7xl space-y-8">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div class="max-w-3xl">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Körperschaftsteuer</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">
                Diese Seite zeigt nur das, was ihr für die Körperschaftsteuer wirklich im Blick haben wollt:
                Vermögensverwaltung und wirtschaftlichen Geschäftsbetrieb. Klar, ruhig und ohne Steuernebel.
            </p>
        </div>
    </div>

    <div class="rounded-2xl border border-sky-200 bg-sky-50 px-5 py-4 text-sm text-sky-900 shadow-sm">
        <div class="font-semibold">Wichtiger Hinweis</div>
        <div class="mt-1 leading-6">
            Diese Auswertung ist eine interne Vorbereitung für euren Verein. Sie ersetzt keine steuerliche Beratung und keine amtliche Erklärung.
        </div>
    </div>

    <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <div class="text-sm font-medium text-slate-500">Zeitraum</div>
                <div class="mt-1 text-lg font-semibold text-slate-900">{{ $periodLabel }}</div>
                <div class="mt-1 text-xs text-slate-500">Auf Basis abgeschlossener Buchungen</div>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label for="start" class="mb-1 block text-sm font-medium text-slate-600">Von</label>
                    <input id="start" type="date" name="start" value="{{ $start }}"
                           class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                </div>

                <div>
                    <label for="end" class="mb-1 block text-sm font-medium text-slate-600">Bis</label>
                    <input id="end" type="date" name="end" value="{{ $end }}"
                           class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                </div>

                <button class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800">
                    Aktualisieren
                </button>
            </div>
        </div>
    </form>

    @if($pendingCount > 0)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900 shadow-sm">
            <div class="font-semibold">Noch nicht abgeschlossen</div>
            <div class="mt-1 leading-6">
                {{ $pendingCount }} Buchung{{ $pendingCount === 1 ? '' : 'en' }} in diesem Zeitraum sind noch offen.
                Sie erscheinen hier erst, wenn sie abgeschlossen sind.
            </div>
        </div>
    @endif

    @if($missingReceiptCount > 0)
        <div class="rounded-2xl border border-violet-200 bg-violet-50 px-5 py-4 text-sm text-violet-900 shadow-sm">
            <div class="font-semibold">Belege noch nicht vollständig</div>
            <div class="mt-1 leading-6">
                {{ $missingReceiptCount }} steuerlich relevante Buchung{{ $missingReceiptCount === 1 ? '' : 'en' }} haben aktuell keinen externen Beleg.
                Clubano-Rechnungen zählen hier bereits als vorhandener Systembeleg.
            </div>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Relevant für die Körperschaftsteuer</div>
            <div class="mt-2 text-2xl font-semibold {{ $relevantSaldo >= 0 ? 'text-slate-900' : 'text-amber-700' }}">
                {{ number_format($relevantSaldo, 2, ',', '.') }} €
            </div>
            <div class="mt-1 text-xs text-slate-500">Saldo aus den wirklich wichtigen Bereichen</div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Einnahmen relevant</div>
            <div class="mt-2 text-2xl font-semibold text-emerald-700">{{ number_format($relevantIncome, 2, ',', '.') }} €</div>
            <div class="mt-1 text-xs text-slate-500">Vermögensverwaltung und wirtschaftlich</div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Ausgaben relevant</div>
            <div class="mt-2 text-2xl font-semibold text-rose-700">{{ number_format($relevantExpense, 2, ',', '.') }} €</div>
            <div class="mt-1 text-xs text-slate-500">Passend zum gewählten Zeitraum</div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Relevante Buchungen</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $relevantTransactions->count() }}</div>
            <div class="mt-1 text-xs text-slate-500">Bereits abgeschlossen und zugeordnet</div>
        </div>
    </div>

    <section class="space-y-4">
        <div>
            <h2 class="text-xl font-semibold tracking-tight text-slate-900">Worauf ihr wirklich schauen solltet</h2>
            <p class="mt-1 text-sm text-slate-500">
                Diese beiden Bereiche sind meist der Kern, wenn ihr die Körperschaftsteuer vorbereitet.
            </p>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            @foreach($relevantAreas as $area => $row)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-lg font-semibold text-slate-900">{{ $row['label'] }}</div>
                            <div class="mt-1 text-sm text-slate-500">{{ $row['count'] }} Buchung{{ $row['count'] === 1 ? '' : 'en' }}</div>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                            {{ $area === 'wirtschaftlich' ? 'Besonders im Blick behalten' : 'Steuerlich relevant' }}
                        </span>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-emerald-50 px-4 py-3">
                            <div class="text-xs font-medium uppercase tracking-[0.18em] text-emerald-700">Einnahmen</div>
                            <div class="mt-2 text-lg font-semibold text-emerald-800">{{ number_format($row['income'], 2, ',', '.') }} €</div>
                        </div>

                        <div class="rounded-xl bg-rose-50 px-4 py-3">
                            <div class="text-xs font-medium uppercase tracking-[0.18em] text-rose-700">Ausgaben</div>
                            <div class="mt-2 text-lg font-semibold text-rose-800">{{ number_format($row['expense'], 2, ',', '.') }} €</div>
                        </div>

                        <div class="rounded-xl bg-slate-100 px-4 py-3">
                            <div class="text-xs font-medium uppercase tracking-[0.18em] text-slate-600">Saldo</div>
                            <div class="mt-2 text-lg font-semibold {{ $row['saldo'] >= 0 ? 'text-slate-900' : 'text-amber-700' }}">
                                {{ number_format($row['saldo'], 2, ',', '.') }} €
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="space-y-4">
        <div>
            <h2 class="text-xl font-semibold tracking-tight text-slate-900">Zur Einordnung im Gesamtbild</h2>
            <p class="mt-1 text-sm text-slate-500">
                Ideeller Bereich und Zweckbetrieb bleiben sichtbar, damit ihr die Abgrenzung sauber nachvollziehen könnt.
            </p>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach(collect($allAreas)->only(['ideell', 'zweckbetrieb']) as $row)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <div class="text-base font-semibold text-slate-900">{{ $row['label'] }}</div>
                            <div class="mt-1 text-sm text-slate-500">{{ $row['count'] }} Buchung{{ $row['count'] === 1 ? '' : 'en' }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs uppercase tracking-[0.18em] text-slate-400">Saldo</div>
                            <div class="mt-1 text-base font-semibold {{ $row['saldo'] >= 0 ? 'text-slate-900' : 'text-amber-700' }}">
                                {{ number_format($row['saldo'], 2, ',', '.') }} €
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="space-y-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold tracking-tight text-slate-900">Welche Buchungen dahinterstecken</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Nur Buchungen aus Vermögensverwaltung und wirtschaftlichem Geschäftsbetrieb.
                </p>
            </div>
        </div>

        <div class="space-y-4 xl:hidden">
            @forelse($relevantTransactions as $transaction)
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm font-medium text-slate-500">{{ optional($transaction->date)->format('d.m.Y') }}</div>
                            <h3 class="mt-1 text-base font-semibold text-slate-900">{{ $transaction->description }}</h3>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-semibold text-slate-900">{{ number_format($transaction->amount, 2, ',', '.') }} €</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $allAreas[$transaction->tax_area]['label'] ?? $transaction->tax_area }}</div>
                        </div>
                    </div>

                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Von</dt>
                            <dd class="text-right font-medium text-slate-900">{{ optional($transaction->account_from)->name ?: '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Nach</dt>
                            <dd class="text-right font-medium text-slate-900">{{ optional($transaction->account_to)->name ?: '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Beleg</dt>
                            <dd class="text-right text-slate-700">
                                @if($transaction->receipt_number)
                                    {{ $transaction->receipt_number }}
                                @elseif($transaction->hasSystemReceipt())
                                    Clubano-Rechnung
                                @else
                                    Fehlt
                                @endif
                            </dd>
                        </div>
                    </dl>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
                    In diesem Zeitraum gibt es noch keine abgeschlossenen Buchungen in den steuerlich relevanten Bereichen.
                </div>
            @endforelse
        </div>

        <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm xl:block">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Datum</th>
                        <th class="px-4 py-3 text-left font-semibold">Beschreibung</th>
                        <th class="px-4 py-3 text-left font-semibold">Bereich</th>
                        <th class="px-4 py-3 text-left font-semibold">Von</th>
                        <th class="px-4 py-3 text-left font-semibold">Nach</th>
                        <th class="px-4 py-3 text-right font-semibold">Betrag</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($relevantTransactions as $transaction)
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-4 text-slate-600">{{ optional($transaction->date)->format('d.m.Y') }}</td>
                            <td class="px-4 py-4">
                                <div class="font-medium text-slate-900">{{ $transaction->description }}</div>
                                <div class="mt-1 text-xs text-slate-500">
                                    @if($transaction->receipt_number)
                                        {{ $transaction->receipt_number }}
                                    @elseif($transaction->hasSystemReceipt())
                                        Clubano-Rechnung vorhanden
                                    @else
                                        Beleg fehlt
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-4 text-slate-700">{{ $allAreas[$transaction->tax_area]['label'] ?? $transaction->tax_area }}</td>
                            <td class="px-4 py-4 text-slate-700">{{ optional($transaction->account_from)->name ?: '—' }}</td>
                            <td class="px-4 py-4 text-slate-700">{{ optional($transaction->account_to)->name ?: '—' }}</td>
                            <td class="px-4 py-4 text-right font-semibold text-slate-900">{{ number_format($transaction->amount, 2, ',', '.') }} €</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500">
                                In diesem Zeitraum gibt es noch keine abgeschlossenen Buchungen in den steuerlich relevanten Bereichen.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
