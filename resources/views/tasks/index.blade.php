@extends('layouts.app')

@section('title', 'Aufgaben')

@section('content')
@php
    $todayDate = now()->startOfDay();
    $focusTasks = $overdue->concat($dueToday)->unique('id')->values();
    $focusCount = $focusTasks->count();
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
            'in_progress' => 'border-sky-200 bg-sky-50 text-sky-700',
            'blocked' => 'border-rose-200 bg-rose-50 text-rose-700',
            'done' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            default => 'border-slate-200 bg-slate-50 text-slate-700',
        };
    };

    $dueLabel = function ($date) use ($todayDate) {
        if (! $date) {
            return 'Ohne Faelligkeit';
        }

        if ($date->isSameDay($todayDate)) {
            return 'Heute faellig';
        }

        if ($date->lt($todayDate)) {
            return 'Ueberfaellig seit ' . $date->format('d.m.Y');
        }

        return 'Faellig am ' . $date->format('d.m.Y');
    };

    $taskMeta = function ($task) use ($dueLabel) {
        $parts = [($task->project?->name ?? 'Ohne Projekt')];

        if ($task->assignee) {
            $parts[] = $task->assignee->name;
        }

        $parts[] = $dueLabel($task->plan_end);

        return implode(' · ', array_filter($parts));
    };
@endphp

<div class="mx-auto max-w-7xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
        <div class="grid gap-8 lg:grid-cols-[1.15fr,0.85fr] lg:items-start">
            <div class="space-y-5">
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <span class="rounded-full bg-slate-950 px-3 py-1 font-semibold text-white">Aufgaben</span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-700">{{ $stats['open'] }} offen</span>
                    @if($stats['overdue'] > 0)
                        <span class="rounded-full bg-rose-50 px-3 py-1 font-semibold text-rose-700 ring-1 ring-rose-200">{{ $stats['overdue'] }} ueberfaellig</span>
                    @elseif($stats['due_today'] > 0)
                        <span class="rounded-full bg-indigo-50 px-3 py-1 font-semibold text-indigo-700 ring-1 ring-indigo-200">{{ $stats['due_today'] }} heute faellig</span>
                    @else
                        <span class="rounded-full bg-emerald-50 px-3 py-1 font-semibold text-emerald-700 ring-1 ring-emerald-200">Heute ruhig</span>
                    @endif
                </div>

                <div class="max-w-3xl">
                    <h1 class="text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">
                        Worum musst du dich jetzt wirklich kuemmern?
                    </h1>
                    <p class="mt-3 max-w-xl text-base leading-7 text-slate-600">
                        Erst das Dringende, dann das Naechste, dann der Rest. So sollte sich Aufgabenarbeit anfuehlen.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2.5">
                    <a href="{{ route('tasks.create') }}"
                       class="inline-flex items-center justify-center rounded-full bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                        Neue Aufgabe
                    </a>

                    @if($nextFocusTask)
                        <a href="{{ route('tasks.edit', $nextFocusTask) }}"
                           class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                            Naechste Aufgabe oeffnen
                        </a>
                    @endif
                </div>
            </div>

            <div class="rounded-3xl bg-slate-950 p-6 text-white shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-white/60">Als Naechstes</div>

                @if($nextFocusTask)
                    <h2 class="mt-4 text-2xl font-semibold">{{ $nextFocusTask->title }}</h2>
                    <p class="mt-3 text-sm leading-6 text-white/75">{{ $taskMeta($nextFocusTask) }}</p>

                    @if($nextFocusTask->description)
                        <p class="mt-4 text-sm leading-6 text-white/70">{{ \Illuminate\Support\Str::limit($nextFocusTask->description, 180) }}</p>
                    @endif

                    <a href="{{ route('tasks.edit', $nextFocusTask) }}"
                       class="mt-5 inline-flex rounded-2xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-950 hover:bg-slate-100">
                        Jetzt bearbeiten
                    </a>
                @else
                    <h2 class="mt-4 text-2xl font-semibold">Alles sortiert</h2>
                    <p class="mt-3 text-sm leading-6 text-white/75">
                        Gerade liegt nichts Dringendes auf dem Tisch. Ein guter Moment fuer saubere Planung statt hektischer Nacharbeit.
                    </p>
                @endif
            </div>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-[1.1fr_1fr_1fr]">
        <div class="rounded-3xl border border-rose-200 bg-rose-50/60 p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Dringend</div>
            <div class="mt-3 text-3xl font-semibold tracking-tight text-rose-900">{{ $focusCount }}</div>
            <div class="mt-2 text-sm text-rose-700/80">Ueberfaellig oder heute faellig</div>
        </div>
        <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm font-medium text-slate-500">Meine Aufgaben</div>
            <div class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ $stats['mine'] }}</div>
            <div class="mt-2 text-sm text-slate-500">Direkt bei mir</div>
        </div>
        <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm font-medium text-slate-500">Demnaechst</div>
            <div class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ $upcoming->count() }}</div>
            <div class="mt-2 text-sm text-slate-500">{{ $stats['follow_up_ready'] }} Wiedervorlagen heute bereit</div>
        </div>
    </section>

    <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Schnell filtern</h2>
                <p class="mt-1 text-sm text-slate-500">Wechsel den Blick, ohne die Seite zu verlassen.</p>
            </div>

            <div class="inline-flex flex-wrap gap-2 rounded-2xl bg-slate-100 p-1">
                @foreach($filterOptions as $filter)
                    <a href="{{ route('tasks.index', ['filter' => $filter['key'] === 'all' ? null : $filter['key']]) }}"
                       class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold transition {{ $activeFilter === $filter['key'] ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                        <span>{{ $filter['label'] }}</span>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500 {{ $activeFilter === $filter['key'] ? 'bg-slate-100 text-slate-700' : '' }}">{{ $filter['count'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-[1.2fr,0.8fr]">
        <div class="space-y-6">
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Jetzt wichtig</h2>
                    <p class="mt-1 text-sm text-slate-500">Nur das, was heute wirklich Aufmerksamkeit braucht.</p>
                </div>

                <div class="mt-6 space-y-3">
                    @forelse($focusTasks as $task)
                        <article class="rounded-2xl border border-slate-200 px-4 py-4 transition hover:bg-slate-50">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="text-base font-semibold text-slate-950">{{ $task->title }}</div>
                                    <div class="mt-1 text-sm text-slate-500">{{ $taskMeta($task) }}</div>
                                </div>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusTone($task->status) }}">
                                    {{ $statusLabel($task->status) }}
                                </span>
                            </div>

                            @if($task->description)
                                <p class="mt-3 text-sm leading-6 text-slate-600">{{ \Illuminate\Support\Str::limit($task->description, 180) }}</p>
                            @endif

                            <div class="mt-4">
                                <a href="{{ route('tasks.edit', $task) }}"
                                   class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Aufgabe oeffnen
                                </a>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                            Heute ist nichts ueberfaellig oder akut faellig.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Demnaechst dran</h2>
                        <p class="mt-1 text-sm text-slate-500">Was als Naechstes vorbereitet werden sollte.</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-3 lg:grid-cols-2">
                    @forelse($upcoming as $task)
                        <article class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <div class="font-semibold text-slate-950">{{ $task->title }}</div>
                            <div class="mt-1 text-sm text-slate-500">{{ $taskMeta($task) }}</div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500 lg:col-span-2">
                            Fuer die naechsten sieben Tage ist noch nichts eingeplant.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $filterHeading }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $filterDescription }}</p>
                </div>

                <div class="mt-6 space-y-2.5">
                    @forelse($filteredTasks->take(10) as $task)
                        <article class="rounded-2xl border border-slate-200 px-4 py-3.5 transition hover:bg-slate-50">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-slate-950">{{ $task->title }}</div>
                                    <div class="mt-1 text-sm text-slate-500">{{ $taskMeta($task) }}</div>
                                </div>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusTone($task->status) }}">
                                    {{ $statusLabel($task->status) }}
                                </span>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500">
                            In diesem Filter gibt es gerade nichts zu sehen.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Bei mir</h2>
                    <p class="mt-1 text-sm text-slate-500">Die Aufgaben, die direkt bei dir liegen.</p>
                </div>

                <div class="mt-6 space-y-2.5">
                    @forelse($myTasks->take(6) as $task)
                        <article class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3.5">
                            <div class="text-sm font-semibold text-slate-950">{{ $task->title }}</div>
                            <div class="mt-1 text-sm text-slate-500">{{ $taskMeta($task) }}</div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500">
                            Dir ist gerade nichts fest zugewiesen.
                        </div>
                    @endforelse
                </div>
            </div>

            <details class="group rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <summary class="flex cursor-pointer list-none items-start justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Wiedervorlage und erledigt</h2>
                        <p class="mt-1 text-sm text-slate-500">Sekundaere Listen, wenn du tiefer in den Bestand schauen willst.</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 group-open:bg-slate-950 group-open:text-white">
                        Aufklappen
                    </span>
                </summary>

                <div class="mt-6 space-y-6">
                    <div>
                        <div class="text-sm font-semibold text-slate-900">Wiedervorlage</div>
                        <div class="mt-3 space-y-2.5">
                            @forelse($followUps as $task)
                                <article class="rounded-2xl border {{ optional($task->follow_up_at)?->lte($todayDate) ? 'border-amber-300 bg-amber-50/50' : 'border-slate-200 bg-slate-50/70' }} p-3.5">
                                    <div class="text-sm font-semibold text-slate-950">{{ $task->title }}</div>
                                    <div class="mt-1 text-sm text-slate-500">
                                        {{ $task->project?->name ?? 'Ohne Projekt' }} · {{ optional($task->follow_up_at)?->format('d.m.Y') ?: 'Kein Datum' }}
                                    </div>
                                </article>
                            @empty
                                <div class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500">
                                    Noch keine Wiedervorlagen gesetzt.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <div class="text-sm font-semibold text-slate-900">Zuletzt erledigt</div>
                        <div class="mt-3 space-y-2.5">
                            @forelse($recentlyCompleted->take(6) as $task)
                                <article class="rounded-2xl border border-emerald-100 bg-emerald-50/40 p-3.5">
                                    <div class="text-sm font-semibold text-slate-950">{{ $task->title }}</div>
                                    <div class="mt-1 text-sm text-slate-500">
                                        {{ $task->project?->name ?? 'Ohne Projekt' }} · Erledigt {{ optional($task->completed_at)->format('d.m.Y H:i') ?: '—' }}
                                    </div>
                                </article>
                            @empty
                                <div class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500">
                                    Noch keine erledigten Aufgaben im Blick.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </details>
        </div>
    </section>
</div>
@endsection
