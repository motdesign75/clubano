@extends('layouts.app')

@section('title', 'Terminaushang')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-2xl bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">Terminaushang</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Welche Termine sollen sichtbar sein?</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    Filtere den Zeitraum, wähle die passenden Veranstaltungen aus und erstelle daraus eine klare Übersicht für den Aushang.
                </p>
            </div>
            <a href="{{ route('events.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-white/20 px-4 text-sm font-semibold text-white hover:bg-white/10">
                Zurück zum Kalender
            </a>
        </div>
    </section>

    @if ($errors->any())
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            Bitte wähle mindestens eine Veranstaltung aus.
        </div>
    @endif

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="GET" action="{{ route('events.poster') }}" class="grid gap-4 lg:grid-cols-5 lg:items-end">
            <div>
                <label for="date_from" class="text-sm font-semibold text-slate-900">Von</label>
                <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] }}" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
            </div>

            <div>
                <label for="date_to" class="text-sm font-semibold text-slate-900">Bis</label>
                <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] }}" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
            </div>

            <div>
                <label for="category_id" class="text-sm font-semibold text-slate-900">Kategorie</label>
                <select id="category_id" name="category_id" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                    <option value="">Alle Kategorien</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $filters['category_id'] === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="visibility" class="text-sm font-semibold text-slate-900">Sichtbarkeit</label>
                <select id="visibility" name="visibility" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                    <option value="all" @selected($filters['visibility'] === 'all')>Alle</option>
                    <option value="public" @selected($filters['visibility'] === 'public')>Öffentlich</option>
                    <option value="internal" @selected($filters['visibility'] === 'internal')>Intern</option>
                </select>
            </div>

            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                Termine anzeigen
            </button>
        </form>
    </section>

    <form method="POST" action="{{ route('events.poster.print') }}" target="_blank" class="space-y-5">
        @csrf

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid gap-4 lg:grid-cols-2">
                <div>
                    <label for="headline" class="text-sm font-semibold text-slate-900">Überschrift auf dem Aushang</label>
                    <input id="headline" name="headline" type="text" value="{{ old('headline', 'Aktuelle Termine') }}" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                </div>
                <div>
                    <label for="note" class="text-sm font-semibold text-slate-900">Optionaler Hinweis</label>
                    <input id="note" name="note" type="text" value="{{ old('note') }}" placeholder="z. B. Änderungen vorbehalten" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Veranstaltungen auswählen</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $events->count() }} Termine im gefilterten Zeitraum.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <x-heroicon-o-printer class="h-5 w-5" />
                        Druckansicht
                    </button>
                    <button type="submit"
                            formaction="{{ route('events.poster.pdf') }}"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                        <x-heroicon-o-document-arrow-down class="h-5 w-5" />
                        PDF öffnen
                    </button>
                </div>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($events as $event)
                    <label class="grid cursor-pointer gap-3 px-5 py-4 transition hover:bg-slate-50 sm:grid-cols-[auto,120px,minmax(0,1fr),150px] sm:items-center">
                        <input type="checkbox" name="event_ids[]" value="{{ $event->id }}" class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-400 sm:mt-0">
                        <div class="text-sm font-semibold text-slate-950">
                            {{ $event->start->format('d.m.Y') }}
                            <span class="block font-normal text-slate-500">{{ $event->start->format('H:i') }} Uhr</span>
                        </div>
                        <div class="min-w-0">
                            <div class="truncate text-base font-semibold text-slate-950">{{ $event->title }}</div>
                            <div class="mt-1 truncate text-sm text-slate-500">{{ $event->location ?: 'Ort folgt' }}</div>
                        </div>
                        <div class="flex flex-wrap gap-2 sm:justify-end">
                            @if($event->category)
                                <span class="rounded-md px-2 py-1 text-xs font-semibold text-slate-700" style="background-color: {{ $event->category->color }}22;">{{ $event->category->name }}</span>
                            @endif
                            <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">{{ $event->is_public ? 'Öffentlich' : 'Intern' }}</span>
                        </div>
                    </label>
                @empty
                    <div class="px-5 py-12 text-center text-sm text-slate-500">Für diese Auswahl wurden keine Termine gefunden.</div>
                @endforelse
            </div>
        </section>
    </form>
</div>
@endsection
