@extends('layouts.app')

@section('title', 'Vereinskalender')

@section('content')
@php
    $dayNames = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
    $baseQuery = request()->except(['view', 'month', 'day', 'year']);
    $activeDateField = match ($calendarView) {
        'day' => 'day',
        'year' => 'year',
        default => 'month',
    };
    $previousParams = match ($calendarView) {
        'day' => ['view' => 'day', 'day' => $calendarDay->copy()->subDay()->format('Y-m-d')],
        'year' => ['view' => 'year', 'year' => $calendarYear->copy()->subYear()->format('Y')],
        default => ['view' => 'month', 'month' => $calendarMonth->copy()->subMonth()->format('Y-m')],
    };
    $nextParams = match ($calendarView) {
        'day' => ['view' => 'day', 'day' => $calendarDay->copy()->addDay()->format('Y-m-d')],
        'year' => ['view' => 'year', 'year' => $calendarYear->copy()->addYear()->format('Y')],
        default => ['view' => 'month', 'month' => $calendarMonth->copy()->addMonth()->format('Y-m')],
    };
    $todayParams = match ($calendarView) {
        'day' => ['view' => 'day', 'day' => now()->format('Y-m-d')],
        'year' => ['view' => 'year', 'year' => now()->format('Y')],
        default => ['view' => 'month', 'month' => now()->format('Y-m')],
    };
    $headline = match ($calendarView) {
        'day' => $calendarDay->translatedFormat('l, d. F Y'),
        'year' => $calendarYear->translatedFormat('Y'),
        default => $calendarMonth->translatedFormat('F Y'),
    };
@endphp

<div class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="rounded-3xl bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">Vereinskalender</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $headline }}</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    Planen, abstimmen und Konflikte früh sehen, ohne dass der Kalender schwer wirkt.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
                <div class="inline-flex items-center rounded-full border border-white/15 bg-white/5 p-1">
                    <a href="{{ route('events.index', array_merge($baseQuery, $previousParams)) }}"
                       class="inline-flex items-center rounded-full px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-white/10">
                        Zurück
                    </a>
                    <a href="{{ route('events.index', array_merge($baseQuery, $todayParams)) }}"
                       class="inline-flex items-center rounded-full px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-white/10">
                        Heute
                    </a>
                    <a href="{{ route('events.index', array_merge($baseQuery, $nextParams)) }}"
                       class="inline-flex items-center rounded-full px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-white/10">
                        Weiter
                    </a>
                </div>
                <a href="{{ route('events.create') }}"
                   class="inline-flex items-center justify-center rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">
                    Termin eintragen
                </a>
            </div>
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <form method="GET" action="{{ route('events.index') }}" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-xl">
                <div class="text-sm font-semibold text-slate-900">Ansicht und Filter</div>
                <div class="mt-1 text-sm text-slate-500">Nur die Werkzeuge, die für diese Ansicht gerade wirklich helfen.</div>
            </div>

            <div class="grid grid-cols-3 rounded-2xl border border-slate-200 bg-slate-50 p-1 sm:w-[320px]">
                @foreach(['month' => 'Monat', 'day' => 'Tag', 'year' => 'Jahr'] as $viewKey => $viewLabel)
                    <label class="cursor-pointer">
                        <input type="radio" name="view" value="{{ $viewKey }}" class="sr-only peer" @checked($calendarView === $viewKey)>
                        <span class="flex justify-center rounded-xl px-3 py-2 text-sm font-medium text-slate-600 peer-checked:bg-white peer-checked:text-slate-950 peer-checked:shadow-sm">
                            {{ $viewLabel }}
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    {{ $activeDateField === 'month' ? 'Monat' : ($activeDateField === 'day' ? 'Tag' : 'Jahr') }}
                </label>
                @if($activeDateField === 'month')
                    <input type="month" name="month" value="{{ $filters['month'] }}" class="w-full rounded-2xl border-slate-300">
                @elseif($activeDateField === 'day')
                    <input type="date" name="day" value="{{ $filters['day'] }}" class="w-full rounded-2xl border-slate-300">
                @else
                    <input type="number" name="year" min="2000" max="2100" value="{{ $filters['year'] }}" class="w-full rounded-2xl border-slate-300">
                @endif
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Kategorie</label>
                <select name="category_id" class="w-full rounded-2xl border-slate-300">
                    <option value="">Alle Kategorien</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $filters['category_id'] === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Verantwortlich</label>
                <select name="responsible_user_id" class="w-full rounded-2xl border-slate-300">
                    <option value="">Alle</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected((string) $filters['responsible_user_id'] === (string) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>

            <label class="flex items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 lg:self-end">
                <input type="checkbox" name="conflicts_only" value="1" @checked($filters['conflicts_only']) class="rounded border-slate-300">
                Nur Konflikte
            </label>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button type="submit" class="rounded-full bg-slate-950 px-4 py-2 text-sm font-semibold text-white">
                Filtern
            </button>
            <a href="{{ route('events.index', ['view' => $calendarView]) }}" class="text-sm font-medium text-slate-500 hover:text-slate-700">
                Filter zurücksetzen
            </a>
        </div>
    </form>

    <div class="grid gap-4 sm:grid-cols-[1fr_1fr_0.9fr]">
        <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Termine</div>
            <div class="mt-1 text-2xl font-semibold text-slate-950">{{ $events->count() }}</div>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-rose-600">Konflikte</div>
            <div class="mt-1 text-2xl font-semibold text-rose-900">{{ $events->where('conflict_count', '>', 0)->count() }}</div>
        </div>
        <div class="rounded-2xl bg-emerald-50/70 px-4 py-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Mit Verantwortlichen</div>
            <div class="mt-1 text-2xl font-semibold text-emerald-900">{{ $events->filter(fn ($event) => filled($event->responsible_name))->count() }}</div>
        </div>
    </div>

    @if($calendarView === 'month')
        <section class="grid gap-6 xl:grid-cols-[1.45fr_0.95fr]">
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50">
                    @foreach($dayNames as $dayName)
                        <div class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                            {{ $dayName }}
                        </div>
                    @endforeach
                </div>

                <div class="grid grid-cols-7">
                    @foreach($calendarDays as $day)
                        <div class="min-h-[152px] border-b border-r border-slate-200 p-3 {{ $day['isCurrentMonth'] ? 'bg-white' : 'bg-slate-50/70' }}">
                            <div class="flex items-center justify-between">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-semibold {{ $day['isToday'] ? 'bg-slate-950 text-white' : 'text-slate-700' }}">
                                    {{ $day['date']->day }}
                                </div>
                                @if($day['events']->isNotEmpty())
                                    <span class="text-xs font-medium text-slate-400">{{ $day['events']->count() }}</span>
                                @endif
                            </div>

                            <div class="mt-3 space-y-1.5">
                                @forelse($day['events']->take(4) as $event)
                                    <a href="{{ route('events.show', $event) }}"
                                       class="block rounded-xl px-2.5 py-2 text-xs transition hover:bg-slate-50 {{ ($event->conflict_count ?? 0) > 0 ? 'bg-rose-50 text-rose-900 ring-1 ring-rose-200' : 'bg-slate-50/80 text-slate-700' }}">
                                        <div class="flex items-start justify-between gap-2">
                                            <span class="line-clamp-2 font-semibold leading-snug">{{ $event->title }}</span>
                                            @if(($event->conflict_count ?? 0) > 0)
                                                <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-700">{{ $event->conflict_count }}</span>
                                            @endif
                                        </div>
                                        <div class="mt-1 text-[11px] opacity-80">{{ $event->start->format('H:i') }}</div>
                                        @if($event->responsible_name)
                                            <div class="mt-0.5 truncate text-[11px] opacity-70">{{ $event->responsible_name }}</div>
                                        @endif
                                    </a>
                                @empty
                                    <div class="rounded-2xl border border-dashed border-slate-200 px-2.5 py-3 text-center text-xs text-slate-400">
                                        frei
                                    </div>
                                @endforelse

                                @if($day['events']->count() > 4)
                                    <div class="text-xs font-medium text-slate-500">+{{ $day['events']->count() - 4 }} weitere</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Termine im Monat</h2>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($events as $event)
                        <a href="{{ route('events.show', $event) }}" class="block px-5 py-3.5 transition hover:bg-slate-50">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="text-sm font-semibold text-slate-950">{{ $event->title }}</div>
                                        @if($event->category)
                                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold text-slate-800" style="background-color: {{ $event->category->color }}22;">
                                                {{ $event->category->name }}
                                            </span>
                                        @endif
                                        @if(($event->conflict_count ?? 0) > 0)
                                            <span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">
                                                Konflikt
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mt-1.5 text-sm text-slate-600">
                                        {{ $event->start->format('d.m.Y H:i') }} - {{ $event->end->format('d.m.Y H:i') }}
                                    </div>
                                    <div class="mt-1 text-sm text-slate-500">
                                        {{ $event->location ?: 'Ort folgt' }}
                                        @if($event->responsible_name)
                                            · Verantwortlich: {{ $event->responsible_name }}
                                        @endif
                                    </div>
                                </div>
                                <div class="shrink-0 text-sm text-slate-400">
                                    {{ $event->start->translatedFormat('D') }}
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="px-5 py-10 text-center text-slate-500">
                            Für diesen Monat gibt es mit den aktuellen Filtern keine Termine.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>
    @elseif($calendarView === 'day')
        <section class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="text-sm font-semibold text-slate-500">Tagesfokus</div>
                <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $calendarDay->translatedFormat('l, d. F Y') }}</div>
                <div class="mt-6 space-y-3">
                    <div class="rounded-2xl bg-slate-50 px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Termine heute</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-950">{{ $dayEvents->count() }}</div>
                    </div>
                    <div class="rounded-2xl bg-rose-50 px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-wide text-rose-600">Konflikte heute</div>
                        <div class="mt-1 text-2xl font-semibold text-rose-900">{{ $dayEvents->where('conflict_count', '>', 0)->count() }}</div>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        @if($dayEvents->isEmpty())
                            Heute ist entspannt. Kein Termin blockiert deine Aufmerksamkeit.
                        @else
                            Erster Termin um {{ $dayEvents->sortBy('start')->first()->start->format('H:i') }},
                            letzter Termin bis {{ $dayEvents->sortByDesc('end')->first()->end->format('H:i') }}.
                        @endif
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Tagesplan</h2>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($dayEvents as $event)
                        <a href="{{ route('events.show', $event) }}" class="block px-5 py-4 transition hover:bg-slate-50">
                            <div class="flex items-start gap-4">
                                <div class="w-24 shrink-0 text-sm font-semibold text-slate-900">
                                    {{ $event->start->format('H:i') }}<br>
                                    <span class="text-xs font-medium text-slate-400">bis {{ $event->end->format('H:i') }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="text-base font-semibold text-slate-950">{{ $event->title }}</div>
                                        @if($event->category)
                                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold text-slate-800" style="background-color: {{ $event->category->color }}22;">
                                                {{ $event->category->name }}
                                            </span>
                                        @endif
                                        @if(($event->conflict_count ?? 0) > 0)
                                            <span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">Konflikt</span>
                                        @endif
                                    </div>
                                    <div class="mt-2 text-sm text-slate-600">{{ $event->location ?: 'Ort folgt' }}</div>
                                    @if($event->responsible_name)
                                        <div class="mt-1 text-sm text-slate-500">Verantwortlich: {{ $event->responsible_name }}</div>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="px-5 py-10 text-center text-slate-500">
                            Für diesen Tag gibt es mit den aktuellen Filtern keine Termine.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>
    @else
        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-900">Jahresübersicht</h2>
            </div>
            <div class="grid gap-px bg-slate-200 lg:grid-cols-2 xl:grid-cols-3">
                @foreach($calendarYearMonths as $month)
                    <div class="bg-white p-5">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-base font-semibold text-slate-950">{{ $month['label'] }}</div>
                            <a href="{{ route('events.index', array_merge($baseQuery, ['view' => 'month', 'month' => $month['month']])) }}"
                               class="text-sm font-medium text-slate-500 hover:text-slate-800">
                                Öffnen
                            </a>
                        </div>
                        <div class="mt-4 space-y-2">
                            @forelse($month['events']->take(5) as $event)
                                <a href="{{ route('events.show', $event) }}" class="block rounded-2xl bg-slate-50/80 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                    <div class="flex items-start justify-between gap-2">
                                        <span class="font-semibold leading-snug text-slate-900">{{ $event->title }}</span>
                                        <span class="shrink-0 text-xs text-slate-400">{{ $event->start->format('d.m.') }}</span>
                                    </div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $event->start->format('H:i') }} - {{ $event->end->format('H:i') }}</div>
                                </a>
                            @empty
                                <div class="rounded-2xl border border-dashed border-slate-200 px-3 py-4 text-center text-sm text-slate-400">
                                    Keine Termine
                                </div>
                            @endforelse

                            @if($month['events']->count() > 5)
                                <div class="text-xs font-medium text-slate-500">+{{ $month['events']->count() - 5 }} weitere</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
