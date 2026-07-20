@extends('layouts.app')

@section('title', $project->name . ' – Projekt')

@section('content')
@php
    // Clubano-Status → Farbklassen (Tailwind)
    // Du kannst hier beliebig erweitern / anpassen.
    $statusChip = [
        'planned'    => 'bg-sky-50 text-sky-700 ring-sky-200',
        'active'     => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'on_hold'    => 'bg-amber-50 text-amber-700 ring-amber-200',
        'blocked'    => 'bg-rose-50 text-rose-700 ring-rose-200',
        'cancelled'  => 'bg-slate-100 text-slate-700 ring-slate-300',
        'canceled'   => 'bg-slate-100 text-slate-700 ring-slate-300', // Schreibvariante
        'done'       => 'bg-violet-50 text-violet-700 ring-violet-200',
        'default'    => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
        'open'       => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    ];

    // Normalisiert Statuswerte auf Keys wie 'on_hold'
    $toKey = function($value) {
        $s = strtolower((string) $value);
        $s = str_replace([' ', '-'], '_', $s);
        $s = preg_replace('/[^a-z0-9_]/', '', $s);
        return $s ?: 'default';
    };

    $projectStatusKey = $toKey($project->status ?? 'default');
    $projectBadge     = $statusChip[$projectStatusKey] ?? $statusChip['default'];

    $start    = optional($project->starts_at)->format('d.m.Y');
    $end      = optional($project->ends_at)->format('d.m.Y');

    // Fallbacks, falls Controller nichts mitliefert
    $documents = isset($documents) ? $documents : collect();
    $tasks     = isset($tasks) ? $tasks : collect();
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 space-y-6">

    <!-- Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <nav class="text-sm text-slate-500">
                <a href="{{ route('projects.index') }}" class="hover:underline">Projekte</a>
                <span class="mx-1">›</span>
                <span class="text-slate-600">Detail</span>
            </nav>
            <div class="mt-1">
                <h1 class="truncate text-2xl font-bold text-slate-900">{{ $project->name }}</h1>
            </div>

            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs sm:text-sm">
                <span class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-2.5 py-1 ring-1 ring-slate-200">
                    <x-heroicon-o-hashtag class="h-4 w-4 text-slate-400" />
                    ID: <span class="font-mono">{{ $project->id }}</span>
                </span>

                <!-- Project-Status farbig -->
                <span class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 ring-1 {{ $projectBadge }}">
                    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-60"></span>
                    Status: {{ $project->status ?? '—' }}
                </span>

                <span class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-2.5 py-1 ring-1 ring-slate-200">
                    <x-heroicon-o-calendar class="h-4 w-4 text-slate-400" />
                    Zeitraum: {{ $start ?: '—' }} – {{ $end ?: '—' }}
                </span>

                <span class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-2.5 py-1 ring-1 ring-slate-200">
                    <x-heroicon-o-user class="h-4 w-4 text-slate-400" />
                    Owner: {{ optional($project->owner)->name ?? '—' }}
                </span>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('projects.gantt', $project) }}"
               class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-white text-sm font-medium shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2">
                <x-heroicon-o-presentation-chart-bar class="h-5 w-5" />
                Gantt öffnen
            </a>
            <a href="{{ route('tasks.create', ['project' => $project->id]) }}"
               class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2">
                <x-heroicon-o-plus-circle class="h-5 w-5 text-slate-500" />
                Neue Aufgabe
            </a>
            <a href="{{ route('projects.documents.create', $project) }}"
               class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2">
                <x-heroicon-o-arrow-up-tray class="h-5 w-5 text-slate-500" />
                Dokument hochladen
            </a>
            <a href="{{ route('projects.edit', $project) }}"
               class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-white text-sm font-medium shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2">
                <x-heroicon-o-pencil-square class="h-5 w-5" />
                Bearbeiten
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            {{ session('success') }}
        </div>
    @endif

    <!-- Info + Dokumente -->
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <!-- Beschreibung & Meta -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="p-4 sm:p-5">
                <h2 class="text-lg font-semibold text-slate-900">Beschreibung</h2>
                <p class="mt-2 text-slate-700">
                    {{ $project->description ?: 'Keine Beschreibung hinterlegt.' }}
                </p>

                <div class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <div class="text-slate-500">Start</div>
                        <div class="text-slate-800">{{ $start ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="text-slate-500">Ende</div>
                        <div class="text-slate-800">{{ $end ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="text-slate-500">Status</div>
                        <div>
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 {{ $projectBadge }}">
                                <span class="h-1.5 w-1.5 rounded-full bg-current opacity-60"></span>
                                {{ $project->status ?? '—' }}
                            </span>
                        </div>
                    </div>
                    <div>
                        <div class="text-slate-500">Verantwortlich</div>
                        <div class="text-slate-800">{{ optional($project->owner)->name ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dokumente -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="p-4 sm:p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900">Dokumente</h2>
                    <a href="{{ route('projects.documents.create', $project) }}"
                       class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                        <x-heroicon-o-arrow-up-tray class="h-4 w-4 text-slate-500" />
                        Hochladen
                    </a>
                </div>

                @if($documents->count())
                    <!-- Mobile Liste -->
                    <div class="mt-3 grid gap-3 sm:hidden">
                        @foreach($documents as $d)
                            <div class="rounded-lg border border-slate-200 p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <a class="block truncate font-medium text-slate-900 hover:underline"
                                           href="{{ route('projects.documents.download', [$project, $d]) }}">
                                           {{ $d->original_name }}
                                        </a>
                                        <div class="mt-1 text-xs text-slate-500">
                                            {{ number_format($d->size/1024, 1, ',', '.') }} KB •
                                            {{ $d->created_at->format('d.m.Y H:i') }} •
                                            {{ optional($d->uploader)->name ?? '—' }}
                                        </div>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1.5">
                                        <a href="{{ route('projects.documents.download', [$project, $d]) }}"
                                           class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">
                                           Download
                                        </a>
                                        <form method="post" action="{{ route('projects.documents.destroy', [$project, $d]) }}"
                                              onsubmit="return confirm('Wirklich löschen?');">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center rounded-md bg-red-600 px-2 py-1 text-xs font-medium text-white hover:bg-red-700">
                                                Löschen
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Desktop Tabelle -->
                    <div class="mt-3 hidden sm:block overflow-auto">
                        <table class="min-w-full divide-y">
                            <thead class="bg-slate-50 text-sm text-slate-600">
                                <tr>
                                    <th class="px-3 py-2 text-left">Datei</th>
                                    <th class="px-3 py-2 text-left">Größe</th>
                                    <th class="px-3 py-2 text-left">Hochgeladen</th>
                                    <th class="px-3 py-2 text-left">Von</th>
                                    <th class="px-3 py-2 text-right">Aktion</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y bg-white text-sm">
                                @foreach($documents as $d)
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-3 py-2">
                                            <a class="hover:underline font-medium text-slate-900"
                                               href="{{ route('projects.documents.download', [$project, $d]) }}">
                                               {{ $d->original_name }}
                                            </a>
                                        </td>
                                        <td class="px-3 py-2 text-slate-700">{{ number_format($d->size/1024, 1, ',', '.') }} KB</td>
                                        <td class="px-3 py-2 text-slate-700">{{ $d->created_at->format('d.m.Y H:i') }}</td>
                                        <td class="px-3 py-2 text-slate-700">{{ optional($d->uploader)->name ?? '—' }}</td>
                                        <td class="px-3 py-2 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="{{ route('projects.documents.download', [$project, $d]) }}"
                                                   class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                                   Download
                                                </a>
                                                <form method="post" action="{{ route('projects.documents.destroy', [$project, $d]) }}"
                                                      onsubmit="return confirm('Wirklich löschen?');" class="inline">
                                                    @csrf @method('DELETE')
                                                    <button type="submit"
                                                            class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700">
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
                @else
                    <div class="mt-3 rounded-lg border-2 border-dashed border-slate-200 p-6 text-center">
                        <p class="text-sm text-slate-600">Noch keine Dokumente vorhanden.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Aufgaben -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="p-4 sm:p-5">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">Aufgaben</h2>
                <a href="{{ route('tasks.create', ['project' => $project->id]) }}"
                   class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">
                    <x-heroicon-o-plus class="h-4 w-4" />
                    Neue Aufgabe
                </a>
            </div>

            @if($tasks->count())
                <!-- Mobile Karten -->
                <div class="mt-3 grid gap-3 sm:hidden">
                    @foreach($tasks as $t)
                        @php
                            $tKey   = $toKey($t->status ?? 'default');
                            $tBadge = $statusChip[$tKey] ?? $statusChip['default'];
                        @endphp
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ route('tasks.edit', $t) }}"
                                       class="block truncate font-medium text-slate-900 hover:underline">
                                        {{ $t->title }}
                                    </a>
                                    <div class="mt-1 text-xs text-slate-500">
                                        {{ optional($t->plan_start)->format('d.m.Y') ?: '—' }} – {{ optional($t->plan_end)->format('d.m.Y') ?: '—' }}
                                        • {{ $t->percent_done }}%
                                        • {{ optional($t->assignee)->name ?? '—' }}
                                    </div>
                                    <span class="mt-2 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] ring-1 {{ $tBadge }}">
                                        <span class="h-1.5 w-1.5 rounded-full bg-current opacity-60"></span>
                                        {{ $t->status ?? '—' }}
                                    </span>
                                </div>
                                <a class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-50"
                                   href="{{ route('tasks.edit', $t) }}">
                                   Bearbeiten
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Desktop Tabelle -->
                <div class="mt-3 hidden sm:block overflow-auto">
                    <table class="min-w-full divide-y">
                        <thead class="bg-slate-50 text-sm text-slate-600">
                            <tr>
                                <th class="px-3 py-2 text-left">Titel</th>
                                <th class="px-3 py-2 text-left">Status</th>
                                <th class="px-3 py-2 text-left">Geplant</th>
                                <th class="px-3 py-2 text-left">% Done</th>
                                <th class="px-3 py-2 text-left">Zuständig</th>
                                <th class="px-3 py-2 text-right">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y bg-white text-sm">
                            @foreach($tasks as $t)
                                @php
                                    $tKey   = $toKey($t->status ?? 'default');
                                    $tBadge = $statusChip[$tKey] ?? $statusChip['default'];
                                @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-2">
                                        <a href="{{ route('tasks.edit', $t) }}" class="font-medium text-slate-900 hover:underline">
                                            {{ $t->title }}
                                        </a>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 {{ $tBadge }}">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current opacity-60"></span>
                                            {{ $t->status ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-slate-700">
                                        {{ optional($t->plan_start)->format('d.m.Y') ?: '—' }} – {{ optional($t->plan_end)->format('d.m.Y') ?: '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-700">{{ $t->percent_done }}%</td>
                                    <td class="px-3 py-2 text-slate-700">{{ optional($t->assignee)->name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <a class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                           href="{{ route('tasks.edit', $t) }}">
                                           Bearbeiten
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="mt-3 rounded-lg border-2 border-dashed border-slate-200 p-6 text-center">
                    <p class="text-sm text-slate-600">Noch keine Aufgaben vorhanden.</p>
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
