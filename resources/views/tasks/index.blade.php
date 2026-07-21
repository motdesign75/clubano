@extends('layouts.app')

@section('title', 'Aufgaben')

@section('content')
@php
    $todayDate = now()->startOfDay();
    $focusTasks = $overdue->concat($dueToday)->unique('id')->values();
    $nextFocusTask = $focusTasks->first() ?? $myTasks->first() ?? $upcoming->first() ?? $followUpsReady->first();

    $filterHeading = match ($activeFilter) {
        'mine' => 'Meine Aufgaben',
        'open' => 'Offene Aufgaben',
        'done' => 'Erledigte Aufgaben',
        default => 'Alle Aufgaben',
    };

    $filterDescription = match ($activeFilter) {
        'mine' => 'Alles, was aktuell direkt bei dir liegt.',
        'open' => 'Alle noch nicht erledigten Aufgaben im Verein.',
        'done' => 'Was bereits abgeschlossen wurde.',
        default => 'Der gesamte Aufgabenbestand auf einen Blick.',
    };

    $statusLabel = function (?string $status) {
        return match ($status) {
            'in_progress' => 'In Arbeit',
            'blocked' => 'Blockiert',
            'done' => 'Erledigt',
            default => 'Offen',
        };
    };

    $statusTone = function (?string $status) {
        return match ($status) {
            'in_progress' => 'bg-sky-100 text-sky-800',
            'blocked' => 'bg-rose-100 text-rose-800',
            'done' => 'bg-emerald-100 text-emerald-800',
            default => 'bg-slate-100 text-slate-700',
        };
    };

    $dueTone = function ($date) use ($todayDate) {
        if (! $date) {
            return 'text-slate-500';
        }

        if ($date->lt($todayDate)) {
            return 'text-rose-700';
        }

        if ($date->isSameDay($todayDate)) {
            return 'text-amber-700';
        }

        return 'text-slate-600';
    };

    $dueLabel = function ($date) use ($todayDate) {
        if (! $date) {
            return 'Ohne Fälligkeit';
        }

        if ($date->isSameDay($todayDate)) {
            return 'Heute fällig';
        }

        if ($date->lt($todayDate)) {
            return 'Überfällig seit ' . $date->format('d.m.Y');
        }

        return 'Fällig am ' . $date->format('d.m.Y');
    };

    $taskMeta = function ($task) use ($dueLabel) {
        return collect([
            $task->project?->name ?? 'Ohne Projekt',
            $task->assignee?->name,
            $dueLabel($task->plan_end),
        ])->filter()->implode(' · ');
    };
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-3xl bg-slate-950 px-6 py-6 text-white sm:px-8">
        <div class="grid gap-6 lg:grid-cols-3 lg:items-end">
            <div class="lg:col-span-2">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Aufgaben</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Was ist als Nächstes dran?</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                    Ein ruhiger Blick auf Priorität, Zuständigkeit und Fälligkeit. Erst das Wichtige, dann der Rest.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row lg:flex-col">
                <a href="{{ route('tasks.create') }}"
                   class="inline-flex items-center justify-center rounded-full bg-white px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">
                    Neue Aufgabe
                </a>
                @if($nextFocusTask)
                    <a href="{{ route('tasks.edit', $nextFocusTask) }}"
                       class="inline-flex items-center justify-center rounded-full bg-white/10 px-5 py-3 text-sm font-semibold text-white ring-1 ring-white/15 transition hover:bg-white/15">
                        Nächste Aufgabe öffnen
                    </a>
                @endif
            </div>
        </div>
    </section>

    <section class="grid overflow-hidden rounded-2xl border border-slate-200 bg-white sm:grid-cols-4">
        <div class="border-b border-slate-100 px-5 py-4 sm:border-b-0 sm:border-r">
            <div class="text-sm font-medium text-slate-500">Offen</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $stats['open'] }}</div>
        </div>
        <div class="border-b border-slate-100 px-5 py-4 sm:border-b-0 sm:border-r">
            <div class="text-sm font-medium text-slate-500">Meine</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $stats['mine'] }}</div>
        </div>
        <div class="border-b border-slate-100 px-5 py-4 sm:border-b-0 sm:border-r">
            <div class="text-sm font-medium text-slate-500">Dringend</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $overdue->count() + $dueToday->count() }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ $stats['overdue'] }} überfällig</div>
        </div>
        <div class="px-5 py-4">
            <div class="text-sm font-medium text-slate-500">Demnächst</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $upcoming->count() }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ $stats['follow_up_ready'] }} Wiedervorlagen bereit</div>
        </div>
    </section>

    @if($nextFocusTask)
        <section class="rounded-2xl border border-slate-200 bg-white px-5 py-4 sm:px-6">
            <div class="grid gap-4 lg:grid-cols-12 lg:items-center">
                <div class="lg:col-span-7">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Nächster sinnvoller Schritt</div>
                    <h2 class="mt-2 text-xl font-semibold text-slate-950">{{ $nextFocusTask->title }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $taskMeta($nextFocusTask) }}</p>
                </div>
                <div class="lg:col-span-2">
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusTone($nextFocusTask->status) }}">
                        {{ $statusLabel($nextFocusTask->status) }}
                    </span>
                </div>
                <div class="flex flex-wrap gap-2 lg:col-span-3 lg:justify-end">
                    @if(! $nextFocusTask->isDone())
                        <form method="POST" action="{{ route('tasks.quick-action', $nextFocusTask) }}" class="inline-flex">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="action" value="start">
                            <button type="submit" class="inline-flex items-center justify-center rounded-full bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                                Das mache ich jetzt
                            </button>
                        </form>

                        <form method="POST" action="{{ route('tasks.quick-action', $nextFocusTask) }}" class="inline-flex">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="action" value="done">
                            <button type="submit" class="inline-flex items-center justify-center rounded-full border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                Erledigt
                            </button>
                        </form>

                        <form method="POST" action="{{ route('tasks.quick-action', $nextFocusTask) }}" class="inline-flex">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="action" value="tomorrow">
                            <button type="submit" class="inline-flex items-center justify-center rounded-full border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                Morgen
                            </button>
                        </form>
                    @endif

                    <a href="{{ route('tasks.edit', $nextFocusTask) }}"
                       class="inline-flex items-center justify-center rounded-full border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Details
                    </a>
                </div>
            </div>
        </section>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white px-5 py-4 sm:px-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Ansicht wählen</h2>
                <p class="mt-1 text-sm text-slate-500">Wechsel den Blick, ohne dich durch Nebenlisten zu arbeiten.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach($filterOptions as $filter)
                    <a href="{{ route('tasks.index', ['filter' => $filter['key'] === 'all' ? null : $filter['key']]) }}"
                       class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition {{ $activeFilter === $filter['key'] ? 'bg-slate-950 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50' }}">
                        <span>{{ $filter['label'] }}</span>
                        <span class="rounded-full px-2 py-0.5 text-xs {{ $activeFilter === $filter['key'] ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-500' }}">{{ $filter['count'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <section class="rounded-2xl border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $filterHeading }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $filterDescription }}</p>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($filteredTasks as $task)
                        <article class="px-5 py-4 transition hover:bg-slate-50/70 sm:px-6">
                            <div class="grid gap-4 lg:grid-cols-12 lg:items-center">
                                <div class="min-w-0 lg:col-span-6">
                                    <a href="{{ route('tasks.edit', $task) }}" class="block truncate text-base font-semibold text-slate-950 transition hover:text-indigo-700">
                                        {{ $task->title }}
                                    </a>
                                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-sm text-slate-500">
                                        <span>{{ $task->project?->name ?? 'Ohne Projekt' }}</span>
                                        <span>{{ $task->assignee?->name ?? 'Nicht zugewiesen' }}</span>
                                    </div>
                                </div>

                                <div class="lg:col-span-2">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusTone($task->status) }}">
                                        {{ $statusLabel($task->status) }}
                                    </span>
                                </div>

                                <div class="text-sm font-medium {{ $dueTone($task->plan_end) }} lg:col-span-2">
                                    {{ $dueLabel($task->plan_end) }}
                                </div>

                                <div class="flex lg:col-span-2 lg:justify-end">
                                    <a href="{{ route('tasks.edit', $task) }}"
                                       class="inline-flex items-center justify-center rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                        Öffnen
                                    </a>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="px-5 py-10 text-center text-sm text-slate-500 sm:px-6">
                            In diesem Blick gibt es gerade keine Aufgaben.
                        </div>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Heute</div>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Aufmerksamkeit</h2>

                <div class="mt-4 divide-y divide-slate-100">
                    @forelse($focusTasks->take(5) as $task)
                        <a href="{{ route('tasks.edit', $task) }}" class="block py-3 first:pt-0 last:pb-0">
                            <div class="text-sm font-semibold text-slate-950">{{ $task->title }}</div>
                            <div class="mt-1 text-xs {{ $dueTone($task->plan_end) }}">{{ $dueLabel($task->plan_end) }}</div>
                        </a>
                    @empty
                        <div class="py-4 text-sm text-slate-500">Nichts überfällig oder heute fällig.</div>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Demnächst</div>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Nächste 7 Tage</h2>

                <div class="mt-4 divide-y divide-slate-100">
                    @forelse($upcoming->take(5) as $task)
                        <a href="{{ route('tasks.edit', $task) }}" class="block py-3 first:pt-0 last:pb-0">
                            <div class="text-sm font-semibold text-slate-950">{{ $task->title }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $taskMeta($task) }}</div>
                        </a>
                    @empty
                        <div class="py-4 text-sm text-slate-500">Für die nächsten sieben Tage ist nichts eingeplant.</div>
                    @endforelse
                </div>
            </section>

            <details class="group rounded-2xl border border-slate-200 bg-white p-5">
                <summary class="flex cursor-pointer list-none items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Archivierter Blick</div>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950">Wiedervorlage & erledigt</h2>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 group-open:bg-slate-950 group-open:text-white">
                        Anzeigen
                    </span>
                </summary>

                <div class="mt-5 space-y-5">
                    <div>
                        <div class="text-sm font-semibold text-slate-900">Wiedervorlage</div>
                        <div class="mt-3 divide-y divide-slate-100">
                            @forelse($followUps->take(5) as $task)
                                <a href="{{ route('tasks.edit', $task) }}" class="block py-3 first:pt-0 last:pb-0">
                                    <div class="text-sm font-semibold text-slate-950">{{ $task->title }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ optional($task->follow_up_at)?->format('d.m.Y') ?: 'Kein Datum' }}</div>
                                </a>
                            @empty
                                <div class="py-4 text-sm text-slate-500">Noch keine Wiedervorlagen gesetzt.</div>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <div class="text-sm font-semibold text-slate-900">Zuletzt erledigt</div>
                        <div class="mt-3 divide-y divide-slate-100">
                            @forelse($recentlyCompleted->take(5) as $task)
                                <a href="{{ route('tasks.edit', $task) }}" class="block py-3 first:pt-0 last:pb-0">
                                    <div class="text-sm font-semibold text-slate-950">{{ $task->title }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ optional($task->completed_at)->format('d.m.Y H:i') ?: '—' }}</div>
                                </a>
                            @empty
                                <div class="py-4 text-sm text-slate-500">Noch keine erledigten Aufgaben im Blick.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </details>
        </aside>
    </section>
</div>
@endsection
