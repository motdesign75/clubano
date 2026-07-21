@extends('layouts.app')

@section('title', 'Dokumente')

@section('content')
@php
    $canManageDocuments = auth()->user()?->isStaff() ?? false;
    $hasActiveFilters = filled($search) || filled($category) || filled($status) || filled($due);
    $documentsCollection = $documents->getCollection();
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-sm">
        <div class="grid gap-6 bg-[linear-gradient(135deg,#020617_0%,#0f3a3a_58%,#1f2937_100%)] px-6 py-7 sm:px-8 lg:grid-cols-[minmax(0,1fr),360px] lg:items-end">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">Dokumentenzentrale</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Alles Wichtige an einem Ort.</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                    Verträge, Satzungen, Bescheide, Vorlagen und Nachweise mit Kontext, Fristen und schneller Suche.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row lg:justify-end">
                @if($canManageDocuments)
                    <a href="{{ route('documents.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-white px-4 text-sm font-semibold text-slate-950 hover:bg-slate-100">
                        <x-heroicon-o-document-plus class="h-5 w-5" />
                        Dokument ablegen
                    </a>
                @endif
                <a href="{{ route('documents.index', ['due' => 'soon']) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-white/20 px-4 text-sm font-semibold text-white hover:bg-white/10">
                    <x-heroicon-o-bell-alert class="h-5 w-5" />
                    Fristen
                </a>
            </div>
        </div>
    </section>

    @if (session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <section class="grid overflow-hidden rounded-xl border border-slate-200 bg-white sm:grid-cols-4">
        <div class="border-b border-slate-100 px-5 py-4 sm:border-b-0 sm:border-r">
            <div class="text-sm font-medium text-slate-500">Aktive Dokumente</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $documentTotalCount }}</div>
        </div>
        <div class="border-b border-slate-100 px-5 py-4 sm:border-b-0 sm:border-r">
            <div class="text-sm font-medium text-slate-500">Prüfen</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $attentionCount }}</div>
        </div>
        <div class="border-b border-slate-100 px-5 py-4 sm:border-b-0 sm:border-r">
            <div class="text-sm font-medium text-slate-500">Läuft bald ab</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $expiringCount }}</div>
        </div>
        <div class="px-5 py-4">
            <div class="text-sm font-medium text-slate-500">Archiviert</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $archivedCount }}</div>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white px-5 py-4 sm:px-6">
        <form method="GET" action="{{ route('documents.index') }}" class="grid gap-3 lg:grid-cols-5 lg:items-end">
            <div class="lg:col-span-2">
                <label for="search" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Suche</label>
                <input id="search" name="search" type="search" value="{{ $search }}"
                       class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300"
                       placeholder="Titel, Beschreibung oder Dateiname">
            </div>

            <div>
                <label for="category" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Kategorie</label>
                <select id="category" name="category" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                    <option value="">Alle</option>
                    @foreach($categories as $value => $label)
                        <option value="{{ $value }}" @selected($category === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="status" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Status</label>
                <select id="status" name="status" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                    <option value="">Aktive Ablage</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row lg:flex-col">
                <input type="hidden" name="due" value="{{ $due }}">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                    Filtern
                </button>
                @if($hasActiveFilters)
                    <a href="{{ route('documents.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Zurücksetzen
                    </a>
                @endif
            </div>
        </form>
    </section>

    @if($documentsCollection->isEmpty())
        <section class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <h2 class="text-xl font-semibold text-slate-950">{{ $hasActiveFilters ? 'Keine passenden Dokumente gefunden' : 'Noch keine Dokumente abgelegt' }}</h2>
            <p class="mt-3 text-sm leading-6 text-slate-500">
                {{ $hasActiveFilters ? 'Passe Suche oder Filter an.' : 'Lege das erste wichtige Vereinsdokument mit Kategorie, Frist und Kontext ab.' }}
            </p>
            @if($canManageDocuments && ! $hasActiveFilters)
                <a href="{{ route('documents.create') }}" class="mt-6 inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                    Erstes Dokument ablegen
                </a>
            @endif
        </section>
    @else
        <section class="rounded-xl border border-slate-200 bg-white">
            <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h2 class="text-xl font-semibold text-slate-950">Ablage</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $documents->firstItem() }}-{{ $documents->lastItem() }} von {{ $documents->total() }} Dokumenten.</p>
                </div>
                <div class="text-sm text-slate-500">Sortiert nach letzter Änderung</div>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach($documentsCollection as $document)
                    <article class="px-5 py-4 transition hover:bg-slate-50/80 sm:px-6">
                        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr),180px,170px] lg:items-center">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('documents.show', $document) }}" class="truncate text-base font-semibold text-slate-950 hover:text-indigo-700">
                                        {{ $document->title }}
                                    </a>
                                    <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">{{ $document->category_label }}</span>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-sm text-slate-500">
                                    <span>{{ $document->original_name }}</span>
                                    <span>{{ $document->human_size }}</span>
                                    @if($document->linked_context)
                                        <span>{{ $document->linked_context }}</span>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <span class="inline-flex rounded-md px-2.5 py-1 text-xs font-semibold {{ $document->status === 'review' ? 'bg-amber-50 text-amber-700' : ($document->status === 'expired' ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700') }}">
                                    {{ $document->status_label }}
                                </span>
                                <div class="mt-2 text-xs text-slate-500">
                                    @if($document->expires_at)
                                        Ablauf: {{ $document->expires_at->format('d.m.Y') }}
                                    @else
                                        Keine Frist
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2 lg:justify-end">
                                <a href="{{ route('documents.download', $document) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                    Download
                                </a>
                                <a href="{{ route('documents.show', $document) }}" class="inline-flex items-center justify-center rounded-lg bg-slate-950 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                                    Öffnen
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if($documents->hasPages())
                <div class="border-t border-slate-100 px-5 py-4 sm:px-6">
                    {{ $documents->links() }}
                </div>
            @endif
        </section>
    @endif
</div>
@endsection
