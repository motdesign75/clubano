@extends('layouts.app')

@section('title', 'Markierungen')

@section('content')
@php
    $totalTags = $tags->count();
    $usedTags = $tags->where('members_count', '>', 0)->count();
    $unusedTags = max(0, $totalTags - $usedTags);
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-sm">
        <div class="grid gap-8 bg-[linear-gradient(135deg,#020617_0%,#0f3a3a_52%,#1f2937_100%)] p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr),360px] lg:p-10">
            <div class="min-w-0">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-white/55">Verein verwalten</div>
                <h1 class="mt-5 text-3xl font-semibold tracking-tight text-white sm:text-4xl">Markierungen</h1>
                <p class="mt-4 max-w-2xl text-sm leading-6 text-white/68">
                    Markierungen helfen dir, Mitglieder schnell zu finden, gezielt einzuladen und Gruppen wie Abteilungen, Teams, Helfer oder Sponsoren sauber zu trennen.
                </p>

                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="{{ route('tags.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-white px-4 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">
                        <x-heroicon-o-plus class="h-5 w-5" />
                        Markierung anlegen
                    </a>
                    <a href="{{ route('members.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-white/18 bg-white/8 px-4 text-sm font-semibold text-white transition hover:bg-white/12">
                        <x-heroicon-o-users class="h-5 w-5" />
                        Mitglieder ansehen
                    </a>
                </div>
            </div>

            <aside class="rounded-xl border border-white/15 bg-white/10 p-5 backdrop-blur">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-white/55">Schnell prüfen</div>
                <div class="mt-4 space-y-3">
                    <div class="grid grid-cols-[40px,minmax(0,1fr),auto] items-center gap-3 rounded-lg border border-white/12 px-3 py-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white text-slate-950">
                            <x-heroicon-o-tag class="h-5 w-5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-white">Markierungen</span>
                            <span class="block truncate text-xs text-white/55">insgesamt angelegt</span>
                        </span>
                        <span class="text-2xl font-semibold tracking-tight text-white">{{ $totalTags }}</span>
                    </div>

                    <div class="grid grid-cols-[40px,minmax(0,1fr),auto] items-center gap-3 rounded-lg border border-white/12 px-3 py-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/10 text-white/70">
                            <x-heroicon-o-user-group class="h-5 w-5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-white">In Nutzung</span>
                            <span class="block truncate text-xs text-white/55">mindestens ein Mitglied</span>
                        </span>
                        <span class="text-2xl font-semibold tracking-tight text-white">{{ $usedTags }}</span>
                    </div>

                    <div class="grid grid-cols-[40px,minmax(0,1fr),auto] items-center gap-3 rounded-lg border border-white/12 px-3 py-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/10 text-white/70">
                            <x-heroicon-o-archive-box class="h-5 w-5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-white">Noch leer</span>
                            <span class="block truncate text-xs text-white/55">können geprüft werden</span>
                        </span>
                        <span class="text-2xl font-semibold tracking-tight text-white">{{ $unusedTags }}</span>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Übersicht</div>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Alle Markierungen</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                    Nutze wenige, klare Markierungen. Gute Beispiele sind Abteilungen, Teams, Zielgruppen oder Verteiler.
                </p>
            </div>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($tags as $tag)
                <div class="grid gap-4 p-5 lg:grid-cols-[minmax(0,1fr),180px,180px] lg:items-center">
                    <div class="flex min-w-0 items-start gap-4">
                        <span class="mt-1 h-5 w-5 shrink-0 rounded-full border border-slate-200" style="background-color: {{ $tag->color ?: '#4f46e5' }}"></span>
                        <div class="min-w-0">
                            <div class="truncate text-lg font-semibold text-slate-950">{{ $tag->name }}</div>
                            <div class="mt-1 text-sm leading-5 text-slate-500">
                                Diese Markierung kann für Suche, Einladungen und Zielgruppen genutzt werden.
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Mitglieder</div>
                        <div class="mt-1 text-lg font-semibold text-slate-950">{{ $tag->members_count }}</div>
                    </div>

                    <div class="flex flex-wrap justify-start gap-2 lg:justify-end">
                        <a href="{{ route('tags.edit', $tag) }}" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-slate-300 px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            <x-heroicon-o-pencil-square class="h-4 w-4" />
                            Bearbeiten
                        </a>
                        <form action="{{ route('tags.destroy', $tag) }}" method="POST" onsubmit="return confirm('Diese Markierung wirklich löschen? Mitglieder werden nicht gelöscht.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-rose-200 px-3 text-sm font-semibold text-rose-700 transition hover:bg-rose-50">
                                <x-heroicon-o-trash class="h-4 w-4" />
                                Löschen
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="p-8">
                    <div class="rounded-2xl border border-dashed border-slate-300 px-5 py-8 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-600">
                            <x-heroicon-o-tag class="h-6 w-6" />
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-slate-950">Noch keine Markierungen</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Lege die erste Markierung an, zum Beispiel Vorstand, Jugend, Helfer, Sponsoren oder Abteilung Fußball.
                        </p>
                        <a href="{{ route('tags.create') }}" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white transition hover:bg-slate-800">
                            Erste Markierung anlegen
                        </a>
                    </div>
                </div>
            @endforelse
        </div>
    </section>
</div>
@endsection
