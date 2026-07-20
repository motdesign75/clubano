@extends('layouts.app')

@section('title', 'Mitgliedschaften verwalten')

@section('content')
    @php
        $labels = [
            'monatlich' => 'monatlich',
            'vierteljährlich' => 'vierteljährlich',
            'halbjährlich' => 'halbjährlich',
            'jährlich' => 'jährlich',
        ];

        $annualFactors = [
            'monatlich' => 12,
            'vierteljährlich' => 4,
            'halbjährlich' => 2,
            'jährlich' => 1,
        ];

        $totalMembers = $memberships->sum('members_count');
        $annualVolume = $memberships->sum(fn ($membership) => ($membership->amount ?? 0) * ($annualFactors[$membership->interval] ?? 1) * ($membership->members_count ?? 0));
        $activeModels = $memberships->where('members_count', '>', 0)->count();
    @endphp

    <div class="mx-auto max-w-7xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-[2rem] bg-slate-950 px-6 py-7 text-white shadow-sm sm:px-8">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <div class="text-xs font-semibold uppercase tracking-[0.24em] text-white/50">Beitragszentrale</div>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Mitgliedschaften</h1>
                    <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                        Beitragsmodelle bestimmen, was bei Mitgliedern als Snapshot landet und welche Rechnungen später sauber entstehen.
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    @if($memberships->isNotEmpty())
                        <form method="POST" action="{{ route('invoices.generateMemberships') }}">
                            @csrf
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-full bg-white px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100 sm:w-auto">
                                Fällige Beiträge abrechnen
                            </button>
                        </form>
                    @endif

                    <a href="{{ route('memberships.create') }}" class="inline-flex w-full items-center justify-center rounded-full border border-white/20 bg-white/10 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/15 sm:w-auto">
                        Neues Beitragsmodell
                    </a>
                </div>
            </div>
        </section>

        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if($memberships->isEmpty())
            <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-sm">
                <div class="mx-auto max-w-xl">
                    <div class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-400">Startpunkt</div>
                    <h2 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Erst das Beitragsmodell, dann die Mitglieder</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        Lege zuerst die Modelle an, die der Verein wirklich nutzt: Erwachsene, Familie, Jugend oder Fördermitglied.
                    </p>
                </div>
                <a href="{{ route('memberships.create') }}" class="mt-6 inline-flex items-center justify-center rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                    Erstes Beitragsmodell anlegen
                </a>
            </div>
        @else
            <section class="grid gap-4 md:grid-cols-3">
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <div class="text-sm font-medium text-slate-500">Beitragsmodelle</div>
                    <div class="mt-3 text-4xl font-semibold tracking-tight text-slate-950">{{ $memberships->count() }}</div>
                    <div class="mt-2 text-sm text-slate-500">{{ $activeModels }} davon mit Mitgliedern</div>
                </div>
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <div class="text-sm font-medium text-slate-500">Zugeordnete Mitglieder</div>
                    <div class="mt-3 text-4xl font-semibold tracking-tight text-slate-950">{{ $totalMembers }}</div>
                    <div class="mt-2 text-sm text-slate-500">über alle Modelle hinweg</div>
                </div>
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <div class="text-sm font-medium text-slate-500">Jahresvolumen</div>
                    <div class="mt-3 text-4xl font-semibold tracking-tight text-slate-950">{{ number_format($annualVolume, 2, ',', '.') }} €</div>
                    <div class="mt-2 text-sm text-slate-500">hochgerechnet aus Betrag und Intervall</div>
                </div>
            </section>

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/70 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Modelle und Abrechnung</h2>
                        <p class="mt-1 text-sm text-slate-500">Ein Klick erzeugt nur fehlende Beitragsrechnungen für fällige Perioden.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-white text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">
                            <tr>
                                <th class="px-5 py-3">Bezeichnung</th>
                                <th class="px-5 py-3">Beitrag</th>
                                <th class="px-5 py-3">Abrechnung</th>
                                <th class="px-5 py-3">Mitglieder</th>
                                <th class="px-5 py-3 text-right">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($memberships as $membership)
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-5 py-4">
                                        <div class="font-semibold text-slate-950">{{ $membership->name }}</div>
                                        <div class="mt-1 text-xs text-slate-500">
                                            Snapshot-Basis für neu zugeordnete Mitglieder
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="font-semibold tabular-nums text-slate-950">{{ number_format($membership->amount ?? 0, 2, ',', '.') }} €</div>
                                        <div class="mt-1 text-xs text-slate-500">
                                            {{ number_format(($membership->amount ?? 0) * ($annualFactors[$membership->interval] ?? 1), 2, ',', '.') }} € pro Jahr
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-slate-700">
                                        <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                            {{ $labels[$membership->interval] ?? ucfirst((string) $membership->interval) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-slate-700">
                                        <div class="font-medium text-slate-950">{{ $membership->members_count }}</div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap items-center justify-end gap-3">
                                            <form method="POST" action="{{ route('invoices.generateMemberships') }}">
                                                @csrf
                                                <input type="hidden" name="membership_id" value="{{ $membership->id }}">
                                                <button type="submit" class="rounded-full bg-blue-600 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                                                    Abrechnen
                                                </button>
                                            </form>
                                            <a href="{{ route('memberships.edit', $membership) }}" class="text-sm font-medium text-slate-700 hover:text-slate-950">
                                                Bearbeiten
                                            </a>
                                            <form action="{{ route('memberships.destroy', $membership) }}" method="POST" onsubmit="return confirm('Diese Mitgliedschaft wirklich löschen?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-700">
                                                    Löschen
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
@endsection
