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
    $canManageEvents = auth()->user()?->isStaff() ?? false;
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

            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center xl:justify-end">
                <div class="grid min-h-11 grid-cols-1 gap-2 sm:grid-cols-3">
                    <a href="{{ route('events.index', array_merge($baseQuery, $previousParams)) }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <x-heroicon-o-chevron-left class="h-4 w-4" />
                        <span>{{ $previousLabel }}</span>
                    </a>
                    <a href="{{ route('events.index', array_merge($baseQuery, $todayParams)) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-slate-950 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        {{ $todayLabel }}
                    </a>
                    <a href="{{ route('events.index', array_merge($baseQuery, $nextParams)) }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <span>{{ $nextLabel }}</span>
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
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <a href="{{ route('events.poster') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            <x-heroicon-o-printer class="h-5 w-5" />
                            Aushang erstellen
                        </a>
                        <a href="{{ route('events.attendance.report') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            <x-heroicon-o-chart-bar class="h-5 w-5" />
                            Anwesenheit auswerten
                        </a>
                        <a href="{{ route('events.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                            <x-heroicon-o-plus class="h-5 w-5" />
                            Termin oder Serie planen
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if($canManageEvents)
        <section class="grid gap-3 lg:grid-cols-5">
            <a href="{{ route('events.create') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-950 text-white">
                        <x-heroicon-o-calendar-days class="h-5 w-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Einzeltermin</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-500">Für Sommerfest, Sitzung oder einmalige Veranstaltung.</p>
                    </div>
                </div>
            </a>

            <a href="{{ route('events.create') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                        <x-heroicon-o-arrow-path class="h-5 w-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Serientermin</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-500">Für Training, Stammtisch oder regelmäßige Vorstandsrunden.</p>
                    </div>
                </div>
            </a>

            <a href="{{ route('event-categories.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                        <x-heroicon-o-swatch class="h-5 w-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Kategorien</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-500">Farben und Bereiche vorbereiten, damit der Kalender lesbar bleibt.</p>
                    </div>
                </div>
            </a>

            <a href="{{ route('events.poster') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                        <x-heroicon-o-printer class="h-5 w-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Aushang</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-500">Termine auswählen und als ruhige Übersicht für das Vereinsheim drucken.</p>
                    </div>
                </div>
            </a>

            <a href="{{ route('events.attendance.report') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                        <x-heroicon-o-chart-bar class="h-5 w-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Anwesenheit</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-500">Pflichtstunden, Teilnehmer und offene Stunden auswerten.</p>
                    </div>
                </div>
            </a>
        </section>
    @endif

    <details class="group rounded-xl border border-slate-200 bg-white p-4 shadow-sm" {{ $hasActiveFilters ? 'open' : '' }}>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-slate-950">Kalenderwerkzeuge</h2>
                <p class="mt-1 text-sm text-slate-500">Termine suchen, Ansicht feinsteuern oder Konflikte isolieren.</p>
            </div>
            <span class="rounded-md bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-700 group-open:bg-slate-950 group-open:text-white">
                {{ $hasActiveFilters ? 'Aktiv' : 'Filter' }}
            </span>
        </summary>

        <form method="GET" action="{{ route('events.index') }}" class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end">
            <input type="hidden" name="view" value="{{ $calendarView }}">

            <div>
                <label for="event_search" class="text-sm font-semibold text-slate-900">Suche</label>
                <div class="relative mt-2">
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                    <input type="search" name="search" id="event_search" value="{{ $filters['search'] }}"
                           class="w-full rounded-lg border-slate-300 pl-10 text-sm focus:border-slate-500 focus:ring-slate-300"
                           placeholder="Titel, Ort, Beschreibung, Person">
                </div>
            </div>

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

    <section class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Termine im Ausschnitt</div>
            <div class="mt-1 text-2xl font-semibold text-slate-950">{{ $events->count() }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Konflikte</div>
            <div class="mt-1 text-2xl font-semibold {{ $conflictCount > 0 ? 'text-rose-700' : 'text-slate-950' }}">{{ $conflictCount }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Mit Zuständigkeit</div>
            <div class="mt-1 text-2xl font-semibold text-slate-950">{{ $responsibleCount }}</div>
        </div>
    </section>

    @if($calendarView === 'month' && $availableDayCount > 0)
        <section class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-emerald-950">Freie Termine sofort erkennen</h2>
                    <p class="mt-1 text-sm leading-6 text-emerald-800">
                        Grün bedeutet frei, rot bedeutet belegt. So siehst du sofort, wo noch Platz ist.
                    </p>
                </div>
                @if($canManageEvents)
                    <a href="{{ route('events.create', ['date' => $availableDays->first()->format('Y-m-d')]) }}"
                       class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800">
                        <x-heroicon-o-plus class="h-5 w-5" />
                        Ersten freien Tag planen
                    </a>
                @endif
            </div>
        </section>
    @endif

    @if($calendarView === 'month')
        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr),360px]">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50">
                    @foreach($dayNames as $dayName)
                        <div class="px-2 py-3 text-center text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $dayName }}</div>
                    @endforeach
                </div>

                <div class="grid grid-cols-7">
                    @foreach($calendarDays as $day)
                        <div class="min-h-[118px] border-b border-r border-slate-100 p-2 {{ $day['isAvailable'] ? 'bg-emerald-50/70' : ($day['events']->isNotEmpty() && $day['isCurrentMonth'] ? 'bg-rose-50/60' : ($day['isCurrentMonth'] ? 'bg-white' : 'bg-slate-50/60')) }} sm:min-h-[140px] sm:p-3">
                            <div class="flex items-center justify-between">
                                <div class="flex h-7 w-7 items-center justify-center rounded-md text-sm font-semibold {{ $day['isToday'] ? 'bg-slate-950 text-white' : ($day['isCurrentMonth'] ? 'text-slate-800' : 'text-slate-400') }}">
                                    {{ $day['date']->day }}
                                </div>
                                @if($day['events']->isNotEmpty())
                                    <span class="rounded-md bg-rose-100 px-1.5 py-0.5 text-[11px] font-semibold text-rose-700">belegt</span>
                                @elseif($day['isAvailable'])
                                    <span class="rounded-md bg-emerald-100 px-1.5 py-0.5 text-[11px] font-semibold text-emerald-700">frei</span>
                                @endif
                            </div>

                            <div class="mt-2 space-y-1.5">
                                @foreach($day['events']->take(3) as $event)
                                    <a href="{{ route('events.show', $event) }}" class="block rounded-md border px-2 py-1.5 text-xs leading-snug transition hover:bg-white {{ ($event->conflict_count ?? 0) > 0 ? 'border-rose-300 bg-rose-100 text-rose-950' : 'border-rose-200 bg-white/80 text-rose-900' }}">
                                        <span class="block truncate font-semibold">{{ $event->start->format('H:i') }} {{ $event->title }}</span>
                                        @if($event->responsible_name)
                                            <span class="block truncate text-[11px] opacity-70">{{ $event->responsible_name }}</span>
                                        @endif
                                    </a>
                                @endforeach

                                @if($day['events']->count() > 3)
                                    <div class="text-[11px] font-semibold text-slate-500">+{{ $day['events']->count() - 3 }} weitere</div>
                                @endif

                                @if($day['isAvailable'])
                                    @if($canManageEvents)
                                        <a href="{{ route('events.create', ['date' => $day['date']->format('Y-m-d')]) }}"
                                           class="block rounded-md border border-emerald-200 bg-white/80 px-2 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-white">
                                            Termin planen
                                        </a>
                                    @else
                                        <div class="rounded-md border border-emerald-200 bg-white/70 px-2 py-1.5 text-xs font-semibold text-emerald-800">
                                            Noch frei
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <aside class="space-y-5">
                <section class="rounded-xl border border-emerald-200 bg-white shadow-sm">
                    <div class="border-b border-emerald-100 px-5 py-4">
                        <h2 class="text-lg font-semibold text-slate-950">Freie Tage im {{ $monthContext }}</h2>
                        <p class="mt-1 text-sm text-slate-500">Schnell sehen, wo in diesem Monat noch Luft ist.</p>
                    </div>

                    <div class="divide-y divide-emerald-50">
                        @forelse($availableDays->take(8) as $availableDay)
                            <div class="px-5 py-3.5">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold text-slate-950">{{ $availableDay->translatedFormat('l') }}</div>
                                        <div class="mt-0.5 text-sm text-slate-500">{{ $availableDay->format('d.m.Y') }}</div>
                                    </div>
                                    @if($canManageEvents)
                                        <a href="{{ route('events.create', ['date' => $availableDay->format('Y-m-d')]) }}"
                                           class="inline-flex min-h-9 shrink-0 items-center justify-center rounded-lg border border-emerald-200 px-3 text-sm font-semibold text-emerald-800 hover:bg-emerald-50">
                                            Planen
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="px-5 py-10 text-center text-sm text-slate-500">In diesem Monat ist jeder kommende Tag belegt.</div>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="text-lg font-semibold text-slate-950">Termine im {{ $monthContext }}</h2>
                        <p class="mt-1 text-sm text-slate-500">Alle geplanten Termine in diesem Monat.</p>
                    </div>

                    <div class="divide-y divide-slate-100">
                        @forelse($events->take(12) as $event)
                            <a href="{{ route('events.show', $event) }}" class="block px-5 py-3.5 hover:bg-slate-50">
                                <div class="flex items-start gap-3">
                                    <div class="w-14 shrink-0 rounded-lg bg-slate-50 px-2 py-2 text-center">
                                        <div class="text-xs font-semibold uppercase text-slate-500">{{ $event->start->translatedFormat('D') }}</div>
                                        <div class="text-lg font-semibold text-slate-950">{{ $event->start->format('d') }}</div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-semibold text-slate-950">{{ $event->title }}</div>
                                        <div class="mt-1 text-sm text-slate-500">{{ $event->start->format('H:i') }} - {{ $event->end->format('H:i') }} Uhr</div>
                                        <div class="mt-1 truncate text-xs text-slate-500">{{ $event->location ?: 'Ort folgt' }}</div>
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="px-5 py-10 text-center text-sm text-slate-500">Keine Termine gefunden.</div>
                        @endforelse
                    </div>
                </section>
            </aside>
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
