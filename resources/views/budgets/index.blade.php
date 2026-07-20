@extends('layouts.app')

@section('title', 'Haushaltsplan')
@section('help-key', 'budgets.index')

@section('content')
<div class="mx-auto max-w-7xl space-y-8">
    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-6 bg-slate-950 px-6 py-7 text-white md:px-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl space-y-3">
                <p class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-300">Finanzen</p>
                <div class="space-y-2">
                    <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Haushaltsplan mit echtem Gegencheck</h1>
                    <p class="max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                        Plane Einnahmen und Ausgaben pro Jahr und vergleiche sie direkt mit dem, was tatsaechlich verbucht wurde.
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center lg:justify-end">
                <div class="rounded-2xl border border-white/10 bg-white/5 px-5 py-4">
                    <div class="text-2xl font-semibold">{{ $plans->count() }}</div>
                    <div class="text-sm text-slate-300">Plaene angelegt</div>
                </div>
                <a href="{{ route('budgets.create', ['year' => $nextYear]) }}"
                   class="inline-flex items-center justify-center rounded-full bg-white px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">
                    {{ $hasCurrentPlan ? 'Weiteren Plan anlegen' : 'Haushaltsplan ' . $nextYear . ' anlegen' }}
                </a>
            </div>
        </div>
    </section>

    <section class="grid gap-4 md:grid-cols-3">
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Naechster Schritt</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $nextYear }}</div>
            <p class="mt-2 text-sm leading-6 text-slate-600">Fuer viele Vereine ist der kommende Haushaltsplan der wichtigste. Darum steht er hier vorne.</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Arbeitsweise</div>
            <div class="mt-2 text-lg font-semibold text-slate-950">Plan, Ist, Abweichung</div>
            <p class="mt-2 text-sm leading-6 text-slate-600">Keine zweite Buchhaltung. Clubano vergleicht den Plan direkt mit euren abgeschlossenen Buchungen.</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Fuer Vorstand & Kasse</div>
            <div class="mt-2 text-lg font-semibold text-slate-950">Sofort lesbar</div>
            <p class="mt-2 text-sm leading-6 text-slate-600">Einnahmen, Ausgaben und Ergebnis sind absichtlich klar getrennt und ohne Fachjargon sichtbar.</p>
        </div>
    </section>

    <section class="space-y-4">
        @forelse ($plans as $plan)
            @php($summary = $plan->budget_summary)
            <article class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-slate-600">{{ $plan->year }}</span>
                            <span class="rounded-full px-3 py-1 text-xs font-medium {{ $plan->isReleased() ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                {{ $plan->isReleased() ? 'Freigegeben' : 'Entwurf' }}
                            </span>
                        </div>
                        <div>
                            <h2 class="text-2xl font-semibold tracking-tight text-slate-950">{{ $plan->title }}</h2>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                                {{ $plan->notes ?: 'Ein ruhiger Jahresblick auf das, was hereinkommen soll, was hinausgeht und wie weit der Verein davon aktuell entfernt ist.' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('budgets.show', $plan) }}" class="inline-flex items-center justify-center rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Ansehen</a>
                        <a href="{{ route('budgets.pdf', $plan) }}" class="inline-flex items-center justify-center rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">PDF</a>
                        <a href="{{ route('budgets.edit', $plan) }}" class="inline-flex items-center justify-center rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Bearbeiten</a>
                        <form method="POST" action="{{ route('budgets.duplicate', $plan) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center rounded-full bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
                                Nach {{ $plan->year + 1 }} uebernehmen
                            </button>
                        </form>
                    </div>
                </div>

                <div class="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Plan Einnahmen</div>
                        <div class="mt-2 text-xl font-semibold text-slate-950">{{ number_format($summary['planned_income'], 2, ',', '.') }} €</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Plan Ausgaben</div>
                        <div class="mt-2 text-xl font-semibold text-slate-950">{{ number_format($summary['planned_expense'], 2, ',', '.') }} €</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Plan Ergebnis</div>
                        <div class="mt-2 text-xl font-semibold text-slate-950">{{ number_format($summary['planned_result'], 2, ',', '.') }} €</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Ist Einnahmen</div>
                        <div class="mt-2 text-xl font-semibold text-emerald-700">{{ number_format($summary['actual_income'], 2, ',', '.') }} €</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Ist Ausgaben</div>
                        <div class="mt-2 text-xl font-semibold text-rose-700">{{ number_format($summary['actual_expense'], 2, ',', '.') }} €</div>
                    </div>
                    <div class="rounded-2xl border px-4 py-4 {{ $summary['variance_result'] >= 0 ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50' }}">
                        <div class="text-xs font-semibold uppercase tracking-[0.24em] {{ $summary['variance_result'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Abweichung Ergebnis</div>
                        <div class="mt-2 text-xl font-semibold {{ $summary['variance_result'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ number_format($summary['variance_result'], 2, ',', '.') }} €</div>
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-[28px] border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center shadow-sm">
                <h2 class="text-xl font-semibold text-slate-950">Noch kein Haushaltsplan angelegt</h2>
                <p class="mx-auto mt-3 max-w-2xl text-sm leading-6 text-slate-600">
                    Starte am besten mit dem kommenden Jahr. Danach kannst du den Plan spaeter immer wieder fortschreiben und fuer das naechste Jahr uebernehmen.
                </p>
                <div class="mt-6">
                    <a href="{{ route('budgets.create', ['year' => $nextYear]) }}"
                       class="inline-flex items-center justify-center rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Haushaltsplan {{ $nextYear }} anlegen
                    </a>
                </div>
            </div>
        @endforelse
    </section>
</div>
@endsection
