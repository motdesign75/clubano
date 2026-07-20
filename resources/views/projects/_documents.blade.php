@extends('layouts.app')

@section('title', $project->name . ' – Dokumente')

@section('content')
@php
    /** @var \Illuminate\Support\Collection|\Illuminate\Pagination\AbstractPaginator $documents */
    // Fallbacks, damit die Seite auch funktioniert, wenn kein $documents übergeben wurde
    $documents = isset($documents)
        ? $documents
        : (method_exists($project, 'documents') ? $project->documents : collect());
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 space-y-6">
    <!-- Header / Breadcrumbs -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <nav class="text-sm text-slate-500">
                <a href="{{ route('projects.index') }}" class="hover:underline">Projekte</a>
                <span class="mx-1">›</span>
                <a href="{{ route('projects.show', $project) }}" class="hover:underline">{{ $project->name }}</a>
                <span class="mx-1">›</span>
                <span class="text-slate-600">Dokumente</span>
            </nav>
            <h1 class="mt-1 truncate text-2xl font-bold text-slate-900">
                Dokumente – <span class="text-slate-700">{{ $project->name }}</span>
            </h1>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('projects.documents.create', $project) }}"
               class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-white text-sm font-medium shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2">
                <x-heroicon-o-arrow-up-tray class="h-5 w-5" />
                Hochladen
            </a>
            <a href="{{ route('projects.show', $project) }}"
               class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2">
                <x-heroicon-o-arrow-uturn-left class="h-5 w-5 text-slate-500" />
                Zur Projektübersicht
            </a>
        </div>
    </div>

    @if(session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <!-- Card -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-lg font-semibold text-slate-900">Dokumente</h2>

            <div class="flex items-center gap-2">
                <span class="hidden sm:inline text-xs text-slate-500">
                    @if(method_exists($documents, 'total'))
                        Insgesamt {{ $documents->total() }} Datei{{ $documents->total() == 1 ? '' : 'en' }}
                    @else
                        Insgesamt {{ $documents->count() }} Datei{{ $documents->count() == 1 ? '' : 'en' }}
                    @endif
                </span>
                <a href="{{ route('projects.documents.create', $project) }}"
                   class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    <x-heroicon-o-plus class="h-5 w-5" />
                    Hochladen
                </a>
            </div>
        </div>

        @if($documents->count())
            <!-- Mobile: Kartenliste -->
            <div class="grid gap-3 p-4 sm:hidden">
                @foreach($documents as $d)
                    <div class="rounded-lg border border-slate-200 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('projects.documents.download', [$project, $d]) }}"
                                   class="block truncate font-medium text-slate-900 hover:underline">
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
                                    @csrf
                                    @method('DELETE')
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

            <!-- Desktop/Tablet: Tabelle -->
            <div class="hidden sm:block overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead class="bg-slate-50 text-left text-sm font-semibold text-slate-700">
                        <tr>
                            <th class="px-4 py-2">Datei</th>
                            <th class="px-4 py-2">Größe</th>
                            <th class="px-4 py-2">Hochgeladen</th>
                            <th class="px-4 py-2">Von</th>
                            <th class="px-4 py-2 text-right">Aktion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm bg-white">
                        @foreach($documents as $d)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2">
                                    <a href="{{ route('projects.documents.download', [$project, $d]) }}"
                                       class="font-medium text-slate-900 hover:underline">
                                        {{ $d->original_name }}
                                    </a>
                                </td>
                                <td class="px-4 py-2 text-slate-700">
                                    {{ number_format($d->size/1024, 1, ',', '.') }} KB
                                </td>
                                <td class="px-4 py-2 text-slate-700">
                                    {{ $d->created_at->format('d.m.Y H:i') }}
                                </td>
                                <td class="px-4 py-2 text-slate-700">
                                    {{ optional($d->uploader)->name ?? '—' }}
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('projects.documents.download', [$project, $d]) }}"
                                           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                            Download
                                        </a>
                                        <form method="post" action="{{ route('projects.documents.destroy', [$project, $d]) }}"
                                              onsubmit="return confirm('Wirklich löschen?');" class="inline">
                                            @csrf
                                            @method('DELETE')
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

                @if(method_exists($documents, 'links'))
                    <div class="border-t border-slate-200 bg-slate-50 px-3 py-2 text-right">
                        {{ $documents->links() }}
                    </div>
                @endif
            </div>
        @else
            <!-- Empty State -->
            <div class="px-4 pb-4">
                <div class="rounded-lg border-2 border-dashed border-slate-200 p-6 text-center">
                    <p class="text-sm text-slate-600">Noch keine Dokumente vorhanden.</p>
                    <div class="mt-3">
                        <a href="{{ route('projects.documents.create', $project) }}"
                           class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2">
                            <x-heroicon-o-arrow-up-tray class="h-5 w-5" />
                            Hochladen
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
