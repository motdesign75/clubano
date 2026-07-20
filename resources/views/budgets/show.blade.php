@extends('layouts.app')

@section('title', $budget->title)
@section('help-key', 'budgets.show')

@section('content')
<div class="mx-auto max-w-7xl space-y-8">
    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-6 bg-slate-950 px-6 py-7 text-white md:px-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl space-y-3">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-slate-200">{{ $budget->year }}</span>
                    <span class="rounded-full px-3 py-1 text-xs font-medium {{ $budget->isReleased() ? 'bg-emerald-400/15 text-emerald-200' : 'bg-amber-400/15 text-amber-200' }}">
                        {{ $budget->isReleased() ? 'Freigegeben' : 'Entwurf' }}
                    </span>
                </div>
                <div class="space-y-2">
                    <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">{{ $budget->title }}</h1>
                    <p class="max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                        {{ $budget->notes ?: 'Der Jahresplan bleibt nur dann hilfreich, wenn Einnahmen, Ausgaben und das Ergebnis schnell erfassbar bleiben.' }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('budgets.pdf', $budget) }}" class="inline-flex items-center justify-center rounded-full border border-white/15 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/5">
                    PDF
                </a>
                <a href="{{ route('budgets.edit', $budget) }}" class="inline-flex items-center justify-center rounded-full bg-white px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">
                    Bearbeiten
                </a>
                <form method="POST" action="{{ route('budgets.duplicate', $budget) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center rounded-full border border-white/15 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/5">
                        Nach {{ $budget->year + 1 }} uebernehmen
                    </button>
                </form>
            </div>
        </div>
    </section>

    <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Plan Einnahmen</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($summary['planned_income'], 2, ',', '.') }} €</div>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Plan Ausgaben</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($summary['planned_expense'], 2, ',', '.') }} €</div>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Plan Ergebnis</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($summary['planned_result'], 2, ',', '.') }} €</div>
        </div>
        <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">Ist Einnahmen</div>
            <div class="mt-2 text-2xl font-semibold text-emerald-700">{{ number_format($summary['actual_income'], 2, ',', '.') }} €</div>
        </div>
        <div class="rounded-3xl border border-rose-200 bg-rose-50 p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-rose-700">Ist Ausgaben</div>
            <div class="mt-2 text-2xl font-semibold text-rose-700">{{ number_format($summary['actual_expense'], 2, ',', '.') }} €</div>
        </div>
        <div class="rounded-3xl border p-5 shadow-sm {{ $summary['variance_result'] >= 0 ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50' }}">
            <div class="text-xs font-semibold uppercase tracking-[0.24em] {{ $summary['variance_result'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Abweichung</div>
            <div class="mt-2 text-2xl font-semibold {{ $summary['variance_result'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ number_format($summary['variance_result'], 2, ',', '.') }} €</div>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-2">
        @foreach (['income' => 'Einnahmen', 'expense' => 'Ausgaben'] as $type => $label)
            <div class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-center justify-between gap-4 border-b border-slate-200 pb-4">
                    <div>
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">{{ $label }}</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            {{ $type === 'income' ? 'Was hereinkommen soll und wie weit es bereits erreicht wurde.' : 'Was hinausgeht und wie stark es den Plan bereits beansprucht.' }}
                        </p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-3 text-right">
                        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Positionen</div>
                        <div class="mt-1 text-lg font-semibold text-slate-950">{{ $items->where('type', $type)->count() }}</div>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($items->where('type', $type) as $item)
                        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0">
                                    <h3 class="text-lg font-semibold text-slate-950">
                                        {{ $item['account']->name }}
                                    </h3>
                                    <div class="mt-1 flex flex-wrap gap-2 text-sm text-slate-500">
                                        {{ $item['account']->number ?: 'Ohne Kontonummer' }}
                                        <span>·</span>
                                        <span>{{ number_format($item['period_amount'], 2, ',', '.') }} € {{ $item['planning_cycle_label'] }}</span>
                                    </div>
                                    @if($item['notes'])
                                        <p class="mt-3 text-sm leading-6 text-slate-600">{{ $item['notes'] }}</p>
                                    @endif
                                </div>

                                <div class="grid gap-3 sm:grid-cols-3 lg:min-w-[25rem]">
                                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                        <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Plan</div>
                                        <div class="mt-2 text-lg font-semibold text-slate-950">{{ number_format($item['planned_amount'], 2, ',', '.') }} €</div>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                        <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Ist</div>
                                        <div class="mt-2 text-lg font-semibold {{ $type === 'income' ? 'text-emerald-700' : 'text-rose-700' }}">{{ number_format($item['actual_amount'], 2, ',', '.') }} €</div>
                                    </div>
                                    <div class="rounded-2xl border px-4 py-3 {{ $item['variance'] >= 0 ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50' }}">
                                        <div class="text-xs font-semibold uppercase tracking-[0.22em] {{ $item['variance'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Abweichung</div>
                                        <div class="mt-2 text-lg font-semibold {{ $item['variance'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ number_format($item['variance'], 2, ',', '.') }} €</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-sm text-slate-500">
                            Noch keine {{ strtolower($label) }} erfasst.
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </section>
</div>
@endsection
