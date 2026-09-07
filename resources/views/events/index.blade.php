@extends('layouts.app')

@section('title', 'Kalender')

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
    $monthContext = $calendarMonth->translatedFormat('F Y');
    $previousLabel = match ($calendarView) {
        'day' => 'Vorheriger Tag',
        'year' => 'Vorheriges Jahr',
        default => 'Vorheriger Monat',
    };
    $todayLabel = match ($calendarView) {
        'day' => 'Heute',
        'year' => 'Dieses Jahr',
        default => 'Dieser Monat',
    };
    $nextLabel = match ($calendarView) {
        'day' => 'Nächster Tag',
        'year' => 'Nächstes Jahr',
        default => 'Nächster Monat',
    };
    $conflictCount = $events->where('conflict_count', '>', 0)->count();
    $responsibleCount = $events->filter(fn ($event) => filled($event->responsible_name))->count();
    $nextEvent = $events->sortBy('start')->first(fn ($event) => $event->start->greaterThanOrEqualTo(now()));
    $canManageEvents = auth()->user()?->canManageEvents() ?? false;
    $hasActiveFilters = filled($filters['category_id']) || filled($filters['responsible_user_id']) || filled($filters['search']) || $filters['conflicts_only'];
    $availableDayCount = ($availableDays ?? collect())->count();
@endphp

<div class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:p-6">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-slate-500">
                    <span class="font-semibold text-slate-900">Vereinskalender</span>
                    <span aria-hidden="true" class="hidden text-slate-300 sm:inline">/</span>
                    <span>{{ $events->count() }} Termine</span>
                    @if($calendarView === 'month' && $availableDayCount > 0)
                        <span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">{{ $availableDayCount }} freie Tage</span>
                    @endif
                    @if($conflictCount > 0)
                        <span class="rounded-md bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700">{{ $conflictCount }} Konflikte</span>
                    @endif
                </div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">{{ $headline }}</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                    @if($nextEvent)
                        Als Nächstes: {{ $nextEvent->title }} am {{ $nextEvent->start->format('d.m.Y') }} um {{ $nextEvent->start->format('H:i') }} Uhr.
                    @else
                        Für diesen Ausschnitt ist kein kommender Termin sichtbar.
                    @endif
                </p>
            </div>

            <div class="flex flex-col gap-3 xl:items-end">
                <div class="grid min-h-11 grid-cols-3 gap-2">
                    <a href="{{ route('events.index', array_merge($baseQuery, $previousParams)) }}" aria-label="{{ $previousLabel }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <x-heroicon-o-chevron-left class="h-4 w-4" />
                        <span class="hidden sm:inline">{{ $previousLabel }}</span>
                    </a>
                    <a href="{{ route('events.index', array_merge($baseQuery, $todayParams)) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-slate-950 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        {{ $todayLabel }}
                    </a>
                    <a href="{{ route('events.index', array_merge($baseQuery, $nextParams)) }}" aria-label="{{ $nextLabel }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <span class="hidden sm:inline">{{ $nextLabel }}</span>
                        <x-heroicon-o-chevron-right class="h-4 w-4" />
                    </a>
                </div>

                <div class="grid grid-cols-3 rounded-lg border border-slate-200 bg-slate-50 p-1 sm:w-[260px]">
                    @foreach(['month' => 'Monat', 'day' => 'Tag', 'year' => 'Jahr'] as $viewKey => $viewLabel)
                        <a href="{{ route('events.index', array_merge($baseQuery, ['view' => $viewKey], $viewKey === 'day' ? ['day' => $calendarDay->format('Y-m-d')] : ($viewKey === 'year' ? ['year' => $calendarYear->format('Y')] : ['month' => $calendarMonth->format('Y-m')])) ) }}"
                           class="rounded-md px-3 py-2 text-center text-sm font-semibold {{ $calendarView === $viewKey ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">
                            {{ $viewLabel }}
                        </a>
                    @endforeach
                </div>

                @if($canManageEvents)
                    <a href="{{ route('events.create') }}" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800 sm:w-auto">
                        <x-heroicon-o-plus class="h-5 w-5" />
                        Termin oder Serie planen
                    </a>
                @endif
            </div>
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('events.index') }}" class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
            <input type="hidden" name="view" value="{{ $calendarView }}">
            @if($activeDateField === 'month')
                <input type="hidden" name="month" value="{{ $filters['month'] }}">
            @elseif($activeDateField === 'day')
                <input type="hidden" name="day" value="{{ $filters['day'] }}">
            @else
                <input type="hidden" name="year" value="{{ $filters['year'] }}">
            @endif

            <div class="relative">
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                <input type="search" name="search" id="event_search" value="{{ $filters['search'] }}"
                       class="w-full rounded-lg border-slate-300 pl-10 text-sm focus:border-slate-500 focus:ring-slate-300"
                       placeholder="Termin suchen: Titel, Ort, Beschreibung oder Person">
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                    Suchen
                </button>
                @if(filled($filters['search']))
                    <a href="{{ route('events.index', ['view' => $calendarView]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 px-4 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                        Suche zurücksetzen
                    </a>
                @endif
            </div>
        </form>
    </section>

    <details class="group rounded-xl border border-slate-200 bg-white p-4 shadow-sm" {{ $hasActiveFilters ? 'open' : '' }}>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-slate-950">Kalenderwerkzeuge</h2>
                <p class="mt-1 text-sm text-slate-500">Nur öffnen, wenn du genauer eingrenzen oder Listen vorbereiten möchtest.</p>
            </div>
            <span class="rounded-md bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-700 group-open:bg-slate-950 group-open:text-white">
                {{ $hasActiveFilters ? 'Aktiv' : 'Öffnen' }}
            </span>
        </summary>

        @if($canManageEvents)
            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <a href="{{ route('events.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <x-heroicon-o-arrow-path class="h-5 w-5" />
                    Serientermin planen
                </a>
                <a href="{{ route('event-categories.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <x-heroicon-o-swatch class="h-5 w-5" />
                    Kategorien
                </a>
                <a href="{{ route('events.poster') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <x-heroicon-o-printer class="h-5 w-5" />
                    Aushang
                </a>
                <a href="{{ route('events.attendance.report') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <x-heroicon-o-chart-bar class="h-5 w-5" />
                    Anwesenheit
                </a>
            </div>
        @endif

        <form method="GET" action="{{ route('events.index') }}" class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end">
            <input type="hidden" name="view" value="{{ $calendarView }}">
            <input type="hidden" name="search" value="{{ $filters['search'] }}">

            <div>
                <label class="text-sm font-semibold text-slate-900">
                    {{ $activeDateField === 'month' ? 'Monat' : ($activeDateField === 'day' ? 'Tag' : 'Jahr') }}
                </label>
                @if($activeDateField === 'month')
                    <input type="month" name="month" value="{{ $filters['month'] }}" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                @elseif($activeDateField === 'day')
                    <input type="date" name="day" value="{{ $filters['day'] }}" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                @else
                    <input type="number" name="year" min="2000" max="2100" value="{{ $filters['year'] }}" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                @endif
            </div>

            <div>
                <label class="text-sm font-semibold text-slate-900">Kategorie</label>
                <select name="category_id" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                    <option value="">Alle Kategorien</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $filters['category_id'] === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-sm font-semibold text-slate-900">Verantwortlich</label>
                <select name="responsible_user_id" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                    <option value="">Alle</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected((string) $filters['responsible_user_id'] === (string) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-2">
                <label class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 px-4 text-sm text-slate-700">
                    <input type="checkbox" name="conflicts_only" value="1" @checked($filters['conflicts_only']) class="rounded border-slate-300">
                    Nur Konflikte
                </label>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                    Anwenden
                </button>
            </div>

            @if($hasActiveFilters)
                <div class="lg:col-span-5">
                    <a href="{{ route('events.index', ['view' => $calendarView]) }}" class="text-sm font-semibold text-slate-500 hover:text-slate-800">
                        Filter zurücksetzen
                    </a>
                </div>
            @endif
        </form>
    </details>

    @if($calendarView === 'month')
        <section class="grid gap-5 xl:grid-cols-[280px_minmax(0,1fr)]">
            <aside class="hidden min-w-0 space-y-4 xl:sticky xl:top-6 xl:block xl:self-start">
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-slate-950">{{ $monthContext }}</h2>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('events.index', array_merge($baseQuery, $previousParams)) }}" aria-label="{{ $previousLabel }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100">
                                <x-heroicon-o-chevron-left class="h-4 w-4" />
                            </a>
                            <a href="{{ route('events.index', array_merge($baseQuery, $nextParams)) }}" aria-label="{{ $nextLabel }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100">
                                <x-heroicon-o-chevron-right class="h-4 w-4" />
                            </a>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-7 gap-1 text-center">
                        @foreach($dayNames as $dayName)
                            <div class="py-1 text-[11px] font-semibold uppercase text-slate-400">{{ $dayName }}</div>
                        @endforeach

                        @foreach($calendarDays as $day)
                            <a href="{{ route('events.index', array_merge($baseQuery, ['view' => 'day', 'day' => $day['date']->format('Y-m-d')])) }}"
                               class="relative flex h-8 items-center justify-center rounded-full text-xs font-semibold transition {{ $day['isToday'] ? 'bg-blue-600 text-white' : ($day['isCurrentMonth'] ? 'text-slate-800 hover:bg-blue-50' : 'text-slate-300 hover:bg-slate-50') }}">
                                {{ $day['date']->day }}
                                @if($day['events']->isNotEmpty())
                                    <span class="absolute bottom-1 h-1 w-1 rounded-full {{ $day['isToday'] ? 'bg-white' : 'bg-blue-500' }}"></span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Schnellblick</div>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <div class="rounded-xl bg-slate-50 px-3 py-3">
                            <div class="text-2xl font-semibold text-slate-950">{{ $events->count() }}</div>
                            <div class="text-xs font-semibold text-slate-500">Termine</div>
                        </div>
                        <div class="rounded-xl bg-emerald-50 px-3 py-3">
                            <div class="text-2xl font-semibold text-emerald-800">{{ $availableDayCount }}</div>
                            <div class="text-xs font-semibold text-emerald-700">freie Tage</div>
                        </div>
                    </div>

                    @if($conflictCount > 0)
                        <a href="{{ route('events.index', array_merge($baseQuery, ['view' => 'month', 'month' => $calendarMonth->format('Y-m'), 'conflicts_only' => 1])) }}"
                           class="mt-3 flex items-center justify-between rounded-xl border border-rose-200 bg-rose-50 px-3 py-3 text-sm font-semibold text-rose-800">
                            <span>{{ $conflictCount }} Konflikte prüfen</span>
                            <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                        </a>
                    @endif
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-slate-950">Kalender</h3>
                        <span class="text-xs font-semibold text-slate-400">{{ $categories->count() }}</span>
                    </div>

                    <div class="mt-3 space-y-2">
                        <a href="{{ route('events.index', array_merge(request()->except(['category_id']), ['view' => 'month', 'month' => $calendarMonth->format('Y-m')])) }}"
                           class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-semibold {{ filled($filters['category_id']) ? 'text-slate-500 hover:bg-slate-50' : 'bg-slate-100 text-slate-950' }}">
                            <span class="h-3 w-3 rounded-full bg-slate-900"></span>
                            Alle Termine
                        </a>
                        @foreach($categories as $category)
                            <a href="{{ route('events.index', array_merge($baseQuery, ['view' => 'month', 'month' => $calendarMonth->format('Y-m'), 'category_id' => $category->id])) }}"
                               class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-semibold {{ (string) $filters['category_id'] === (string) $category->id ? 'bg-slate-100 text-slate-950' : 'text-slate-600 hover:bg-slate-50' }}">
                                <span class="h-3 w-3 rounded-full" style="background-color: {{ e($category->color ?: '#2563EB') }}"></span>
                                <span class="truncate">{{ $category->name }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-slate-950">Nächste Termine</h3>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $events->count() }}</span>
                    </div>

                    <div class="mt-3 space-y-2">
                        @forelse($events->take(6) as $event)
                            <a href="{{ route('events.show', $event) }}" class="block rounded-xl border border-slate-100 px-3 py-2 hover:bg-slate-50">
                                <div class="flex items-start gap-2">
                                    <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ e($event->category?->color ?: '#2563EB') }}"></span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-semibold text-slate-950">{{ $event->title }}</span>
                                        <span class="block truncate text-xs text-slate-500">{{ $event->start->format('d.m. H:i') }} Uhr · {{ $event->location ?: 'Ort folgt' }}</span>
                                    </span>
                                </div>
                            </a>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-200 px-3 py-4 text-sm text-slate-500">Keine Termine gefunden.</div>
                        @endforelse
                    </div>
                </section>
            </aside>

            <div class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 bg-white px-3 py-3 sm:px-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <h2 class="truncate text-lg font-semibold text-slate-950">{{ $headline }}</h2>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">
                            {{ $events->count() }} Termine
                        </span>
                    </div>
                    @if($canManageEvents)
                        <a href="{{ route('events.create') }}" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700">
                            <x-heroicon-o-plus class="h-5 w-5" />
                            Neuer Termin
                        </a>
                    @endif
                </div>

                <div class="overflow-hidden">
                    <div class="w-full md:min-w-[920px]">
                        <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50">
                            @foreach(['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'] as $dayName)
                                <div class="px-1 py-2 text-center text-xs font-bold text-slate-700 sm:px-3 sm:py-3 sm:text-sm">
                                    <span class="sm:hidden">{{ mb_substr($dayName, 0, 1) }}</span>
                                    <span class="hidden sm:inline">{{ $dayName }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-7">
                            @foreach($calendarDays as $day)
                                <div class="min-h-[104px] min-w-0 border-b border-r border-slate-200 bg-white p-1 {{ ! $day['isCurrentMonth'] ? 'bg-slate-50/70' : '' }} {{ $day['isToday'] ? 'bg-blue-50/60' : '' }} sm:min-h-[132px] sm:p-2 xl:min-h-[170px]">
                                    <div class="flex items-center justify-between gap-1">
                                        <a href="{{ route('events.index', array_merge($baseQuery, ['view' => 'day', 'day' => $day['date']->format('Y-m-d')])) }}"
                                           class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-xs font-bold sm:h-7 sm:min-w-7 sm:text-sm {{ $day['isToday'] ? 'bg-blue-600 text-white' : ($day['isCurrentMonth'] ? 'text-slate-800 hover:bg-slate-100' : 'text-slate-400 hover:bg-slate-100') }}">
                                            <span class="sm:hidden">{{ $day['date']->day === 1 ? $day['date']->translatedFormat('j. M') : $day['date']->day }}</span>
                                            <span class="hidden sm:inline">{{ $day['date']->isSameDay($calendarMonth->copy()->startOfMonth()) || $day['date']->day === 1 ? $day['date']->translatedFormat('j. M') : $day['date']->day }}</span>
                                        </a>
                                        @if($day['isAvailable'] && $canManageEvents)
                                            <a href="{{ route('events.create', ['date' => $day['date']->format('Y-m-d')]) }}"
                                               class="hidden h-7 w-7 items-center justify-center rounded-full text-slate-300 hover:bg-blue-50 hover:text-blue-700 sm:inline-flex"
                                               aria-label="Termin am {{ $day['date']->format('d.m.Y') }} planen">
                                                <x-heroicon-o-plus class="h-4 w-4" />
                                            </a>
                                        @endif
                                    </div>

                                    <div class="mt-1 space-y-1 sm:mt-2">
                                        @foreach($day['events']->take(3) as $event)
                                            <a href="{{ route('events.show', $event) }}"
                                               class="group block min-w-0 rounded-md border border-transparent bg-slate-50 px-1.5 py-1 text-[11px] leading-tight hover:border-slate-200 hover:bg-white sm:px-2 sm:py-1.5 sm:text-xs sm:leading-snug"
                                               style="border-left: 3px solid {{ e($event->category?->color ?: '#2563EB') }}">
                                                <span class="block truncate font-bold text-slate-950">
                                                    <span class="hidden sm:inline">{{ $event->start->format('H:i') }} </span>{{ $event->title }}
                                                </span>
                                                <span class="hidden truncate text-[11px] text-slate-500 sm:block">
                                                    {{ $event->location ?: ($event->responsible_name ?: 'Details öffnen') }}
                                                </span>
                                                @if(($event->conflict_count ?? 0) > 0)
                                                    <span class="mt-1 hidden rounded-full bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold text-rose-700 sm:inline-flex">Konflikt</span>
                                                @endif
                                            </a>
                                        @endforeach

                                        @if($day['events']->count() > 3)
                                            <a href="{{ route('events.index', array_merge($baseQuery, ['view' => 'day', 'day' => $day['date']->format('Y-m-d')])) }}"
                                               class="block rounded-md px-1.5 py-1 text-xs font-bold text-blue-700 hover:bg-blue-50 sm:px-2">
                                                +{{ $day['events']->count() - 3 }}
                                                <span class="hidden sm:inline">weitere</span>
                                            </a>
                                        @elseif($day['isAvailable'] && ! $day['isPast'])
                                            <div class="hidden px-2 py-1 text-xs font-medium text-slate-300 sm:block">frei</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @elseif($calendarView === 'day')
        <section class="grid gap-5 xl:grid-cols-[320px,minmax(0,1fr)]">
            <aside class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm font-semibold text-slate-500">Tagesfokus</div>
                <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $calendarDay->translatedFormat('d. F Y') }}</div>
                <div class="mt-5 space-y-3 text-sm text-slate-600">
                    <div class="rounded-lg bg-slate-50 px-4 py-3">{{ $dayEvents->count() }} Termine</div>
                    <div class="rounded-lg bg-slate-50 px-4 py-3">{{ $dayEvents->where('conflict_count', '>', 0)->count() }} Konflikte</div>
                    @if($dayEvents->isNotEmpty())
                        <div class="rounded-lg bg-slate-50 px-4 py-3">
                            {{ $dayEvents->sortBy('start')->first()->start->format('H:i') }} bis {{ $dayEvents->sortByDesc('end')->first()->end->format('H:i') }} Uhr
                        </div>
                    @endif
                </div>
            </aside>

            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-lg font-semibold text-slate-950">Tagesplan</h2>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($dayEvents as $event)
                        <a href="{{ route('events.show', $event) }}" class="block px-5 py-4 hover:bg-slate-50">
                            <div class="grid gap-3 sm:grid-cols-[110px,minmax(0,1fr),90px] sm:items-center">
                                <div class="text-sm font-semibold text-slate-950">
                                    {{ $event->start->format('H:i') }}
                                    <span class="block font-normal text-slate-500">bis {{ $event->end->format('H:i') }}</span>
                                </div>
                                <div class="min-w-0">
                                    <div class="truncate text-base font-semibold text-slate-950">{{ $event->title }}</div>
                                    <div class="mt-1 truncate text-sm text-slate-500">{{ $event->location ?: 'Ort folgt' }}</div>
                                </div>
                                <div class="text-xs font-semibold {{ ($event->conflict_count ?? 0) > 0 ? 'text-rose-700' : 'text-slate-500' }}">
                                    {{ ($event->conflict_count ?? 0) > 0 ? 'Konflikt' : ($event->is_public ? 'Öffentlich' : 'Intern') }}
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="px-5 py-10 text-center text-sm text-slate-500">Für diesen Tag gibt es keine Termine.</div>
                    @endforelse
                </div>
            </div>
        </section>
    @else
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-950">Jahresübersicht</h2>
            </div>
            <div class="grid gap-px bg-slate-200 lg:grid-cols-2 xl:grid-cols-3">
                @foreach($calendarYearMonths as $month)
                    <div class="bg-white p-5">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-base font-semibold text-slate-950">{{ $month['label'] }}</div>
                            <a href="{{ route('events.index', array_merge($baseQuery, ['view' => 'month', 'month' => $month['month']])) }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-800">
                                Öffnen
                            </a>
                        </div>
                        <div class="mt-4 space-y-2">
                            @forelse($month['events']->take(5) as $event)
                                <a href="{{ route('events.show', $event) }}" class="block rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">
                                    <div class="flex items-start justify-between gap-2">
                                        <span class="font-semibold leading-snug text-slate-900">{{ $event->title }}</span>
                                        <span class="shrink-0 text-xs text-slate-400">{{ $event->start->format('d.m.') }}</span>
                                    </div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $event->start->format('H:i') }} - {{ $event->end->format('H:i') }}</div>
                                </a>
                            @empty
                                <div class="rounded-lg border border-dashed border-slate-200 px-3 py-4 text-center text-sm text-slate-400">
                                    Keine Termine
                                </div>
                            @endforelse

                            @if($month['events']->count() > 5)
                                <div class="text-xs font-semibold text-slate-500">+{{ $month['events']->count() - 5 }} weitere</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
