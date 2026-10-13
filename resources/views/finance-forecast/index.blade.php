@extends('layouts.app')

@section('title', 'Finanzvorschau')

@section('content')
@php
    $money = fn ($amount) => number_format((float) $amount, 2, ',', '.') . ' €';
    $periodLabel = $start->format('d.m.Y') . ' bis ' . $end->format('d.m.Y');
@endphp

<div class="mx-auto max-w-7xl space-y-8">
    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-6 bg-slate-950 px-6 py-7 text-white md:px-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl space-y-3">
                <p class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-300">Planung</p>
                <div class="space-y-2">
                    <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Finanzvorschau</h1>
                    <p class="max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                        Sieh auf einen Blick, welche Beitragszahlungen, offenen Rechnungen und geplanten Ausgaben in den naechsten Monaten auf den Verein zukommen.
                    </p>
                </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 px-5 py-4">
                <div class="text-2xl font-semibold">{{ $money($stats['expected_saldo']) }}</div>
                <div class="text-sm text-slate-300">Voraussichtlicher Saldo</div>
                <div class="mt-1 text-xs text-slate-400">{{ $periodLabel }}</div>
            </div>
        </div>
    </section>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.26em] text-emerald-700">Erwartete Einnahmen</div>
            <div class="mt-3 text-2xl font-semibold text-emerald-900">{{ $money($stats['expected_income']) }}</div>
            <p class="mt-2 text-sm leading-6 text-emerald-800">Beitraege aus Mitgliederdaten plus offene Ausgangsrechnungen.</p>
        </div>

        <div class="rounded-3xl border border-rose-200 bg-rose-50 p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.26em] text-rose-700">Erwartete Ausgaben</div>
            <div class="mt-3 text-2xl font-semibold text-rose-900">{{ $money($stats['expected_expenses']) }}</div>
            <p class="mt-2 text-sm leading-6 text-rose-800">Offene Eingangsrechnungen plus anteilige Ausgaben aus dem Haushaltsplan.</p>
        </div>

        <div class="rounded-3xl border {{ $stats['overdue_membership_count'] > 0 ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-white' }} p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.26em] {{ $stats['overdue_membership_count'] > 0 ? 'text-amber-700' : 'text-slate-500' }}">Ueberfaellige Beitraege</div>
            <div class="mt-3 text-2xl font-semibold {{ $stats['overdue_membership_count'] > 0 ? 'text-amber-900' : 'text-slate-950' }}">{{ $money($stats['overdue_membership_amount']) }}</div>
            <p class="mt-2 text-sm leading-6 {{ $stats['overdue_membership_count'] > 0 ? 'text-amber-800' : 'text-slate-600' }}">{{ $stats['overdue_membership_count'] }} offene Beitragsrechnung{{ $stats['overdue_membership_count'] === 1 ? '' : 'en' }} sind faellig.</p>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.26em] text-slate-500">Beitragsbasis</div>
            <div class="mt-3 text-2xl font-semibold text-slate-950">{{ $stats['members_with_contribution'] }}</div>
            <p class="mt-2 text-sm leading-6 text-slate-600">Aktive Zahler mit Beitrag und Zahlungsrhythmus in der Vorschau.</p>
        </div>
    </section>

    <section class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-3 border-b border-slate-200 pb-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Naechste 12 Monate</p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950">Monatsvorschau</h2>
            </div>
            <div class="text-sm text-slate-500">Prognosen werden nicht gebucht. Sie dienen der Planung.</div>
        </div>

        <div class="mt-5 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                        <th class="py-3 pr-4">Monat</th>
                        <th class="px-4 py-3 text-right">Beitraege</th>
                        <th class="px-4 py-3 text-right">Offene Rechnungen</th>
                        <th class="px-4 py-3 text-right">Plan-Ausgaben</th>
                        <th class="px-4 py-3 text-right">Offene Eingangsrechnungen</th>
                        <th class="py-3 pl-4 text-right">Saldo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($months as $month)
                        <tr class="align-top">
                            <td class="py-4 pr-4 font-semibold text-slate-950">{{ $month['label'] }}</td>
                            <td class="px-4 py-4 text-right text-emerald-700">{{ $money($month['membership_forecast']) }}</td>
                            <td class="px-4 py-4 text-right text-emerald-700">{{ $money($month['open_invoice_income']) }}</td>
                            <td class="px-4 py-4 text-right text-rose-700">{{ $money($month['planned_expenses']) }}</td>
                            <td class="px-4 py-4 text-right text-rose-700">{{ $money($month['open_payables']) }}</td>
                            <td class="py-4 pl-4 text-right font-semibold {{ $month['saldo'] >= 0 ? 'text-slate-950' : 'text-rose-700' }}">{{ $money($month['saldo']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-2">
        <div class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Mitglieder</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-950">Beitraege nach Zahlungsweise</h2>
                </div>
                <a href="{{ route('membership-billing.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Abrechnung</a>
            </div>

            <div class="mt-5 space-y-3">
                @forelse($membershipForecastRows->take(8) as $row)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="font-semibold text-slate-950">{{ $row['member']->full_name }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $row['interval'] }} · naechste Faelligkeiten: {{ $row['occurrences']->take(3)->map->format('d.m.Y')->implode(', ') }}</div>
                            </div>
                            <div class="font-mono font-semibold text-emerald-700">{{ $money($row['total']) }}</div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-sm text-slate-500">Noch keine Beitragsdaten fuer eine Vorschau vorhanden.</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Warnhinweise</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-950">Was Aufmerksamkeit braucht</h2>
                </div>
                <a href="{{ route('invoices.index', ['status' => 'overdue']) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Rechnungen</a>
            </div>

            <div class="mt-5 space-y-3">
                @forelse($overdueMembershipInvoices->take(6) as $invoice)
                    <a href="{{ route('invoices.show', $invoice) }}" class="block rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 hover:bg-amber-100/60">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="font-semibold text-amber-950">{{ $invoice->invoice_number }} · {{ $invoice->member?->full_name ?? $invoice->recipient_name }}</div>
                                <div class="mt-1 text-xs text-amber-800">Faellig {{ optional($invoice->due_date)->format('d.m.Y') }}</div>
                            </div>
                            <div class="font-mono font-semibold text-amber-900">{{ $money($invoice->getRemainingAmount()) }}</div>
                        </div>
                    </a>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-sm text-slate-500">Keine ueberfaelligen Beitragsrechnungen.</div>
                @endforelse

                @if($dueMemberRows->isNotEmpty())
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="font-semibold text-slate-950">{{ $dueMemberRows->count() }} Mitglied{{ $dueMemberRows->count() === 1 ? '' : 'er' }} sind laut Zahlungsplan faellig</div>
                        <p class="mt-1 text-sm leading-6 text-slate-600">Diese Beitraege sind noch keine offenen Rechnungen, sollten aber in der Mitgliederabrechnung geprueft werden.</p>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-2">
        <div class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Ausgaben</p>
            <h2 class="mt-2 text-xl font-semibold text-slate-950">Groesste geplante Ausgaben</h2>
            <div class="mt-5 space-y-3">
                @forelse($plannedExpenses->take(8) as $row)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="font-semibold text-slate-950">{{ $row['item']->account?->number }} · {{ $row['item']->account?->name ?? 'Ohne Konto' }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $row['plan']->title }} · {{ $row['item']->budgetCategory?->name ?? 'Ohne Bereich' }}</div>
                            </div>
                            <div class="font-mono font-semibold text-rose-700">{{ $money($row['annual_amount']) }}</div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-sm text-slate-500">Noch kein Haushaltsplan fuer den Vorschauzeitraum vorhanden.</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Grundlage</p>
            <h2 class="mt-2 text-xl font-semibold text-slate-950">So rechnet Clubano aktuell</h2>
            <div class="mt-5 space-y-3 text-sm leading-6 text-slate-600">
                <p>Beitraege kommen aus aktiven Mitgliedern ohne Familienzahler, deren Beitrag, Zahlungsweise und naechstes Rechnungsdatum gepflegt sind.</p>
                <p>Offene Ausgangsrechnungen und Eingangsrechnungen werden mit ihrem Restbetrag im Faelligkeitsmonat beruecksichtigt.</p>
                <p>Ausgaben aus dem Haushaltsplan werden anteilig auf Monate verteilt, solange noch kein konkreter Zahlungstermin hinterlegt ist.</p>
            </div>
        </div>
    </section>
</div>
@endsection
