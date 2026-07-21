@extends('layouts.app')

@section('title', 'Protokolle')

@section('content')
@php
    $canManageProtocols = auth()->user()?->canManageProtocols() ?? false;
    $protocolsCollection = $protocols->getCollection();
    $filteredProtocolCount = $protocols->total();
    $latestProtocol = $protocolsCollection->first();
    $openProtocolsCount = max(0, ($protocolTotalCount ?? 0) - ($sentProtocolsCount ?? 0));
    $hasActiveFilters = filled($search) || filled($type) || filled($status);
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-3xl bg-slate-950 px-6 py-6 text-white sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Protokolle</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Was wurde besprochen?</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    Halte Sitzungen, Absprachen und Beschlüsse so fest, dass dein Verein später alles wiederfindet.
                </p>
            </div>

            @if($canManageProtocols)
                <a href="{{ route('protocols.create') }}"
                   class="inline-flex items-center justify-center rounded-full bg-white px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">
                    Neues Protokoll
                </a>
            @endif
        </div>
    </section>

    @if (session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <section class="grid overflow-hidden rounded-2xl border border-slate-200 bg-white sm:grid-cols-3">
        <div class="border-b border-slate-100 px-5 py-4 sm:border-b-0 sm:border-r">
            <div class="text-sm font-medium text-slate-500">Protokolle gesamt</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $protocolTotalCount }}</div>
        </div>
        <div class="border-b border-slate-100 px-5 py-4 sm:border-b-0 sm:border-r">
            <div class="text-sm font-medium text-slate-500">Schon versendet</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $sentProtocolsCount }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ $openProtocolsCount }} noch offen</div>
        </div>
        <div class="px-5 py-4">
            <div class="text-sm font-medium text-slate-500">Aktueller Ausschnitt</div>
            <div class="mt-2 text-xl font-semibold text-slate-950">
                {{ $filteredProtocolCount }} Treffer
            </div>
            <div class="mt-1 text-xs text-slate-500">
                Neuester Treffer: {{ $latestProtocol?->created_at?->format('d.m.Y') ?? '—' }}
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white px-5 py-4 sm:px-6">
        <form method="GET" action="{{ route('protocols.index') }}" class="grid gap-3 lg:grid-cols-4 lg:items-end">
            <div class="lg:col-span-2">
                <label for="search" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Suche</label>
                <input id="search" name="search" type="search" value="{{ $search }}"
                       class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300"
                       placeholder="Titel, Ort, Inhalt oder Beschluss">
            </div>

            <div>
                <label for="type" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Typ</label>
                <select id="type" name="type" class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300">
                    <option value="">Alle Typen</option>
                    @foreach($typeOptions as $typeOption)
                        <option value="{{ $typeOption }}" @selected($type === $typeOption)>{{ $typeOption }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="status" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Versand</label>
                <select id="status" name="status" class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300">
                    <option value="">Alle</option>
                    <option value="open" @selected($status === 'open')>Noch offen</option>
                    <option value="sent" @selected($status === 'sent')>Versendet</option>
                </select>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row lg:flex-col">
                <button type="submit" class="inline-flex items-center justify-center rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Filtern
                </button>
                @if($hasActiveFilters)
                    <a href="{{ route('protocols.index') }}" class="inline-flex items-center justify-center rounded-full border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Zurücksetzen
                    </a>
                @endif
            </div>
        </form>
    </section>

    @if($protocolsCollection->isEmpty())
        <section class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <div class="mx-auto max-w-md">
                <h2 class="text-xl font-semibold text-slate-900">{{ $hasActiveFilters ? 'Keine passenden Protokolle gefunden' : 'Noch kein Protokoll vorhanden' }}</h2>
                <p class="mt-3 text-sm leading-6 text-slate-500">
                    {{ $hasActiveFilters
                        ? 'Passe Suche oder Filter an, um den Ausschnitt wieder zu erweitern.'
                        : 'Lege das erste Protokoll an, damit Beschlüsse, Teilnehmer und Ergebnisse nicht verloren gehen.' }}
                </p>
                @if($canManageProtocols && ! $hasActiveFilters)
                    <a href="{{ route('protocols.create') }}"
                       class="mt-6 inline-flex items-center justify-center rounded-full bg-indigo-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700">
                        Erstes Protokoll erstellen
                    </a>
                @endif
            </div>
        </section>
    @else
        <section class="rounded-2xl border border-slate-200 bg-white">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Protokollliste</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $protocols->firstItem() }}-{{ $protocols->lastItem() }} von {{ $filteredProtocolCount }} Protokollen.
                    </p>
                </div>
                <div class="text-sm text-slate-500">Sortiert nach letzter Änderung</div>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach($protocolsCollection as $protocol)
                    <article class="px-5 py-4 transition hover:bg-slate-50/70 sm:px-6">
                        <div class="grid gap-4 lg:grid-cols-12 lg:items-center">
                            <div class="min-w-0 flex items-start gap-4 lg:col-span-6">
                                <div class="hidden w-20 shrink-0 text-sm text-slate-500 sm:block">
                                    <div class="font-semibold text-slate-900">{{ $protocol->created_at->format('d.m.Y') }}</div>
                                    <div class="mt-0.5 text-xs">{{ $protocol->created_at->format('H:i') }} Uhr</div>
                                </div>
                                <div class="min-w-0">
                                    <a href="{{ route('protocols.show', $protocol) }}" class="block truncate text-base font-semibold text-slate-950 transition hover:text-indigo-700">
                                        {{ $protocol->title }}
                                    </a>
                                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-sm text-slate-500">
                                        <span>{{ $protocol->type }}</span>
                                        <span>{{ $protocol->user->name ?? 'Unbekannt' }}</span>
                                        @if($protocol->location)
                                            <span>{{ $protocol->location }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="lg:col-span-2">
                                <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold {{ (int) ($protocol->dispatch_count ?? 0) > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                    {{ (int) ($protocol->dispatch_count ?? 0) > 0 ? 'Versendet' : 'Noch offen' }}
                                </span>
                                <div class="mt-2 text-xs text-slate-500">
                                    @if((int) ($protocol->dispatch_count ?? 0) > 0)
                                        {{ $protocol->dispatch_count }} Empfänger
                                        @if($protocol->last_dispatched_at)
                                            · {{ $protocol->last_dispatched_at->format('d.m.Y') }}
                                        @endif
                                    @else
                                        Noch nicht verteilt
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2 lg:col-span-4 lg:justify-end">
                                <a href="{{ route('protocols.show', $protocol) }}"
                                   class="inline-flex items-center justify-center rounded-full bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
                                    Ansehen
                                </a>
                                @if($canManageProtocols)
                                <a href="{{ route('protocols.edit', $protocol) }}"
                                   class="inline-flex items-center justify-center rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Bearbeiten
                                </a>
                                <a href="{{ route('protocols.mail.form', $protocol) }}"
                                   class="inline-flex items-center justify-center rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Versenden
                                </a>
                                <form method="POST" action="{{ route('protocols.archive', $protocol) }}" onsubmit="return confirm('Protokoll wirklich archivieren?');" class="inline-flex">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                        Archivieren
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('protocols.destroy', $protocol) }}" onsubmit="return confirm('Protokoll wirklich löschen?');" class="inline-flex">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                        Löschen
                                    </button>
                                </form>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if($protocols->hasPages())
                <div class="border-t border-slate-100 px-5 py-4 sm:px-6">
                    {{ $protocols->links() }}
                </div>
            @endif
        </section>
    @endif
</div>
@endsection
