@extends('layouts.app')

@section('title', 'Projekte')

@section('content')
@php
    $statusColors = [
        'active'   => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'on_hold'  => 'bg-amber-50 text-amber-700 ring-amber-200',
        'done'     => 'bg-slate-100 text-slate-700 ring-slate-300',
        'default'  => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    ];
    $statusLabels = [
        'active' => 'Laeuft',
        'on_hold' => 'Pausiert',
        'done' => 'Abgeschlossen',
    ];
    $firstProject = method_exists($projects, 'first') ? $projects->first() : ($projects[0] ?? null);
    $projectCollection = method_exists($projects, 'getCollection') ? $projects->getCollection() : collect($projects);
    $activeProjectsCount = $projectCollection->where('status', 'active')->count();
    $onHoldProjectsCount = $projectCollection->where('status', 'on_hold')->count();
    $doneProjectsCount = $projectCollection->where('status', 'done')->count();
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">

    <section class="rounded-3xl bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Projekte</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Vorhaben, die im Verein wirklich vorankommen sollen</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    Renovierung, Veranstaltung, Jubiläum oder Anschaffung. Hier bleibt sichtbar, was läuft, was stockt und was schon fertig ist.
                </p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-slate-200">
                    <div class="font-semibold text-white">{{ $activeProjectsCount }} laufend</div>
                    <div class="mt-0.5 text-xs text-slate-300">{{ $onHoldProjectsCount }} pausiert, {{ $doneProjectsCount }} abgeschlossen</div>
                </div>
                <a href="{{ route('projects.create') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-full bg-white px-5 py-3 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-slate-100">
                    <x-heroicon-o-plus class="h-5 w-5" />
                    Neues Projekt
                </a>
            </div>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-3xl border border-emerald-200 bg-emerald-50/60 p-5 shadow-sm">
            <div class="text-sm font-medium text-emerald-700">Laufend</div>
            <div class="mt-3 text-3xl font-semibold tracking-tight text-emerald-900">{{ $activeProjectsCount }}</div>
        </div>
        <div class="rounded-3xl border border-amber-200 bg-amber-50/60 p-5 shadow-sm">
            <div class="text-sm font-medium text-amber-700">Pausiert</div>
            <div class="mt-3 text-3xl font-semibold tracking-tight text-amber-900">{{ $onHoldProjectsCount }}</div>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Abgeschlossen</div>
            <div class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ $doneProjectsCount }}</div>
        </div>
    </section>

    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
            <form method="GET" class="w-full sm:max-w-md">
                <label class="sr-only" for="q">Suche</label>
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-2.5 h-5 w-5 text-slate-400" />
                    <input
                        id="q"
                        type="search"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="In Projekten suchen…"
                        class="w-full rounded-2xl border border-slate-300 py-2 pl-10 pr-3 text-sm placeholder:text-slate-400 focus:border-slate-900 focus:ring-slate-900"
                        />
                </div>
            </form>

            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                    Gesamt: {{ method_exists($projects, 'total') ? $projects->total() : $projects->count() }}
                </span>
                @if ($firstProject)
                    <a href="{{ route('projects.gantt', $firstProject) }}"
                       class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                        <x-heroicon-o-presentation-chart-bar class="h-4 w-4" />
                        Erstes Gantt öffnen
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if(session('status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if($projects->isEmpty())
        <div class="rounded-3xl border-2 border-dashed border-slate-200 bg-white p-10 text-center">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50">
                <x-heroicon-o-rectangle-stack class="h-6 w-6 text-indigo-500" />
            </div>
            <h3 class="text-base font-semibold text-slate-900">Noch keine Projekte vorhanden</h3>
            <p class="mt-1 text-sm text-slate-500">Lege dein erstes Projekt an, damit Renovierung, Veranstaltung oder Jubiläum nicht nur in Köpfen, sondern sauber im Ablauf landen.</p>
            <div class="mt-4">
                <a href="{{ route('projects.create') }}"
                   class="inline-flex items-center gap-2 rounded-full bg-slate-950 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                    <x-heroicon-o-plus class="h-5 w-5" />
                    Neues Projekt
                </a>
            </div>
        </div>
    @else
        <div class="grid gap-4 sm:hidden">
            @foreach($projects as $project)
                @php
                    $status = $project->status ?? 'default';
                    $badge  = $statusColors[$status] ?? $statusColors['default'];
                    $statusLabel = $statusLabels[$status] ?? ($project->status ?? '—');
                    $start  = optional($project->starts_at)->format('d.m.Y');
                    $end    = optional($project->ends_at)->format('d.m.Y');
                @endphp
                <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate text-base font-semibold text-slate-900">{{ $project->name }}</h3>
                            <p class="mt-1 line-clamp-2 text-sm text-slate-600">
                                {{ \Illuminate\Support\Str::limit($project->description, 160) ?: '—' }}
                            </p>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 ring-1 {{ $badge }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-60"></span>
                                    {{ $statusLabel }}
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-0.5 ring-1 ring-slate-200">
                                    <x-heroicon-o-calendar class="h-4 w-4 text-slate-400" />
                                    {{ $start ?: '—' }} – {{ $end ?: '—' }}
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-0.5 ring-1 ring-slate-200">
                                    <x-heroicon-o-user class="h-4 w-4 text-slate-400" />
                                    {{ optional($project->owner)->name ?? '—' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <a href="{{ route('projects.show', $project) }}"
                           class="inline-flex items-center rounded-full bg-slate-950 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800">
                            Anzeigen
                        </a>
                        <a href="{{ route('projects.edit', $project) }}"
                           class="inline-flex items-center rounded-full border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Bearbeiten
                        </a>
                        <a href="{{ route('projects.gantt', $project) }}"
                           class="inline-flex items-center rounded-full border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Gantt
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="hidden overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm sm:block">
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead class="bg-slate-50 text-left text-sm font-semibold text-slate-700">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Zeitraum</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Verantwortlich</th>
                            <th class="px-4 py-3 text-right">Aktion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @foreach($projects as $project)
                            @php
                                $status = $project->status ?? 'default';
                                $badge  = $statusColors[$status] ?? $statusColors['default'];
                                $statusLabel = $statusLabels[$status] ?? ($project->status ?? '—');
                            @endphp
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium text-slate-900">{{ $project->name }}</div>
                                    <div class="mt-0.5 text-xs text-slate-500">
                                        {{ \Illuminate\Support\Str::limit($project->description, 120) ?: '—' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top text-slate-700">
                                    {{ optional($project->starts_at)->format('d.m.Y') ?: '—' }}
                                    –
                                    {{ optional($project->ends_at)->format('d.m.Y') ?: '—' }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 {{ $badge }}">
                                        <span class="h-1.5 w-1.5 rounded-full bg-current opacity-60"></span>
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 align-top text-slate-700">
                                    {{ optional($project->owner)->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('projects.show', $project) }}"
                                           class="text-xs font-semibold text-indigo-700 hover:text-indigo-800">
                                            Anzeigen
                                        </a>
                                        <a href="{{ route('projects.edit', $project) }}"
                                           class="text-xs font-medium text-slate-600 hover:text-slate-900">
                                            Bearbeiten
                                        </a>
                                        <a href="{{ route('projects.gantt', $project) }}"
                                           class="text-xs font-medium text-slate-600 hover:text-slate-900">
                                            Gantt
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if(method_exists($projects, 'links'))
                <div class="border-t border-slate-200 bg-slate-50 px-3 py-2">
                    <div class="flex items-center justify-between text-xs text-slate-600">
                        <div>
                            @if(method_exists($projects, 'firstItem'))
                                Zeige {{ $projects->firstItem() }}–{{ $projects->lastItem() }} von {{ $projects->total() }}
                            @else
                                Gesamt: {{ $projects->count() }}
                            @endif
                        </div>
                        <div class="text-sm">
                            {{ $projects->links() }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
