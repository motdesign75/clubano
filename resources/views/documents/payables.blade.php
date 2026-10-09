@extends('layouts.app')

@section('title', 'Eingangsrechnungen')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-sm">
        <div class="bg-[linear-gradient(135deg,#020617_0%,#174151_58%,#1f2937_100%)] px-6 py-7 sm:px-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">Zahlungsübersicht</div>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Eingangsrechnungen im Blick</h1>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300 sm:text-base">
                        Rechnung hochladen, Zahlungsdaten prüfen, fällige Zahlungen planen und bezahlte Belege nachvollziehen.
                    </p>
                </div>
                <a href="{{ route('payables.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-white px-4 text-sm font-semibold text-slate-950 shadow-sm hover:bg-slate-100">
                    Eingangsrechnung hochladen
                </a>
            </div>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <a href="{{ route('payables.index', ['status' => \App\Models\Document::PAYABLE_REVIEW]) }}" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
            <div class="text-sm font-medium text-amber-800">Zu prüfen</div>
            <div class="mt-2 text-2xl font-semibold text-amber-950">{{ $stats['review'] ?? 0 }}</div>
        </a>
        <a href="{{ route('payables.index', ['status' => \App\Models\Document::PAYABLE_OPEN]) }}" class="rounded-2xl border border-blue-200 bg-blue-50 p-4 shadow-sm">
            <div class="text-sm font-medium text-blue-800">Offen</div>
            <div class="mt-2 text-2xl font-semibold text-blue-950">{{ $stats['open'] ?? 0 }}</div>
        </a>
        <a href="{{ route('payables.index', ['status' => 'overdue']) }}" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 shadow-sm">
            <div class="text-sm font-medium text-rose-800">Überfällig</div>
            <div class="mt-2 text-2xl font-semibold text-rose-950">{{ $stats['overdue'] ?? 0 }}</div>
        </a>
        <a href="{{ route('payables.index', ['status' => \App\Models\Document::PAYABLE_PAID]) }}" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
            <div class="text-sm font-medium text-emerald-800">Bezahlt</div>
            <div class="mt-2 text-2xl font-semibold text-emerald-950">{{ $stats['paid'] ?? 0 }}</div>
        </a>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Offener Betrag</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format((float) ($stats['open_total'] ?? 0), 2, ',', '.') }} €</div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="GET" action="{{ route('payables.index') }}" class="grid gap-3 lg:grid-cols-[minmax(0,1fr),220px,auto] lg:items-end">
            <div>
                <label for="search" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Suche</label>
                <input id="search" name="search" type="search" value="{{ $search }}"
                       class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300"
                       placeholder="Lieferant, Rechnungsnummer, Verwendungszweck">
            </div>
            <div>
                <label for="status" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Status</label>
                <select id="status" name="status" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                    <option value="">Alle</option>
                    <option value="overdue" @selected($status === 'overdue')>Überfällig</option>
                    @foreach(\App\Models\Document::payableStatuses() as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button class="inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                    Filtern
                </button>
                @if(filled($search) || filled($status))
                    <a href="{{ route('payables.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Zurück</a>
                @endif
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-950">Zahlungen planen und nachvollziehen</h2>
            <p class="mt-1 text-sm text-slate-500">Sortiert nach Fälligkeit. Bezahlt wird erst, wenn eine Buchung abgeschlossen oder der Status bewusst gesetzt wurde.</p>
        </div>

        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Lieferant / Beleg</th>
                        <th class="px-4 py-3 text-left font-semibold">Fällig</th>
                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                        <th class="px-4 py-3 text-left font-semibold">Zahlungsdaten</th>
                        <th class="px-4 py-3 text-right font-semibold">Offen</th>
                        <th class="px-4 py-3 text-right font-semibold">Aktion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($payables as $payable)
                        <tr class="{{ $payable->isPayableOverdue() ? 'bg-rose-50/50' : 'hover:bg-slate-50/80' }}">
                            <td class="px-4 py-4">
                                <a href="{{ route('documents.show', $payable) }}" class="font-semibold text-slate-950 hover:text-indigo-700">
                                    {{ $payable->recognized_vendor ?: $payable->title }}
                                </a>
                                <div class="mt-1 text-xs text-slate-500">
                                    {{ $payable->recognized_invoice_number ?: $payable->original_name }}
                                </div>
                            </td>
                            <td class="px-4 py-4 text-slate-700">
                                {{ $payable->payable_due_date?->format('d.m.Y') ?? 'offen' }}
                                @if($payable->payable_due_source === 'calculated')
                                    <div class="mt-1 text-xs text-slate-500">berechnet</div>
                                @elseif($payable->payable_due_source === 'explicit')
                                    <div class="mt-1 text-xs text-slate-500">aus Rechnung</div>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $payable->isPayableOverdue() ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-700' }}">
                                    {{ $payable->payable_status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-slate-600">
                                <div>{{ $payable->payable_iban ?: 'IBAN offen' }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $payable->payable_reference ?: 'Verwendungszweck offen' }}</div>
                            </td>
                            <td class="px-4 py-4 text-right font-mono font-semibold text-slate-950">
                                {{ number_format($payable->payableRemainingAmount(), 2, ',', '.') }} €
                            </td>
                            <td class="px-4 py-4 text-right">
                                @if($payable->derivedPayableStatus() !== \App\Models\Document::PAYABLE_PAID && $payable->derivedPayableStatus() !== \App\Models\Document::PAYABLE_CANCELLED)
                                    <a href="{{ route('documents.receipt.prepare-transaction', $payable) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-slate-950 px-3 text-xs font-semibold text-white hover:bg-slate-800">
                                        Buchung vorbereiten
                                    </a>
                                @else
                                    <a href="{{ route('documents.show', $payable) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        Anzeigen
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500">Keine Eingangsrechnungen gefunden.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="space-y-3 p-4 md:hidden">
            @forelse($payables as $payable)
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <a href="{{ route('documents.show', $payable) }}" class="font-semibold text-slate-950">{{ $payable->recognized_vendor ?: $payable->title }}</a>
                            <div class="mt-1 text-xs text-slate-500">Fällig {{ $payable->payable_due_date?->format('d.m.Y') ?? 'offen' }}</div>
                        </div>
                        <div class="text-right font-mono font-semibold text-slate-950">{{ number_format($payable->payableRemainingAmount(), 2, ',', '.') }} €</div>
                    </div>
                    <div class="mt-3 text-sm text-slate-600">{{ $payable->payable_reference ?: 'Verwendungszweck offen' }}</div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
                    Keine Eingangsrechnungen gefunden.
                </div>
            @endforelse
        </div>

        @if($payables->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $payables->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
