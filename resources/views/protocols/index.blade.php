@extends('layouts.app')

@section('title', 'Protokolle')

@section('content')
@php
    $canManageProtocols = auth()->user()?->canManageProtocols() ?? false;
    $protocolCount = $protocols->count();
    $latestProtocol = $protocols->first();
    $typesCount = $protocols->pluck('type')->filter()->unique()->count();
    $sentProtocolsCount = $protocols->filter(fn ($protocol) => (int) ($protocol->dispatch_count ?? 0) > 0)->count();
@endphp

<div class="mx-auto max-w-7xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-3xl bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Protokolle</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Was wurde besprochen?</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    Halte Sitzungen, Absprachen und Beschluesse so fest, dass dein Verein spaeter alles wiederfindet.
                </p>
            </div>

            @if($canManageProtocols)
                <a href="{{ route('protocols.create') }}"
                   class="inline-flex items-center justify-center rounded-full bg-white px-5 py-3 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-slate-100">
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

    <section class="grid gap-4 sm:grid-cols-[1.1fr_1fr_1fr]">
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Protokolle gesamt</div>
            <div class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ $protocolCount }}</div>
        </div>
        <div class="rounded-3xl border border-emerald-200 bg-emerald-50/60 p-5 shadow-sm">
            <div class="text-sm font-medium text-emerald-700">Schon versendet</div>
            <div class="mt-3 text-3xl font-semibold tracking-tight text-emerald-900">{{ $sentProtocolsCount }}</div>
            <div class="mt-2 text-xs text-emerald-700/80">Von {{ $protocolCount }} Protokollen</div>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Neuester Stand</div>
            <div class="mt-3 text-xl font-semibold tracking-tight text-slate-950">
                {{ $latestProtocol?->created_at?->format('d.m.Y') ?? '—' }}
            </div>
            <div class="mt-2 text-xs text-slate-500">
                {{ $latestProtocol?->user?->name ?? '—' }} · {{ $typesCount }} Typen im Einsatz
            </div>
        </div>
    </section>

    @if($protocols->isEmpty())
        <section class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-sm">
            <div class="mx-auto max-w-md">
                <h2 class="text-xl font-semibold text-slate-900">Noch kein Protokoll vorhanden</h2>
                <p class="mt-3 text-sm leading-6 text-slate-500">
                    Lege das erste Protokoll an, damit Beschluesse, Teilnehmer und Ergebnisse nicht verloren gehen.
                </p>
                @if($canManageProtocols)
                    <a href="{{ route('protocols.create') }}"
                       class="mt-6 inline-flex items-center justify-center rounded-full bg-indigo-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700">
                        Erstes Protokoll erstellen
                    </a>
                @endif
            </div>
        </section>
    @else
        <section class="space-y-4">
            <div>
                <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Letzte Protokolle</h2>
                <p class="mt-1 text-sm text-slate-500">Neu, versendet oder noch offen. Alles, was fuer den weiteren Verlauf wichtig ist.</p>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                @foreach($protocols as $protocol)
                    <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                                    {{ $protocol->created_at->format('d.m.Y') }} · {{ $protocol->created_at->format('H:i') }} Uhr
                                </div>
                                <h3 class="mt-2 text-xl font-semibold text-slate-950">{{ $protocol->title }}</h3>
                                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700">
                                        {{ $protocol->type }}
                                    </span>
                                    <span class="inline-flex rounded-full bg-indigo-100 px-3 py-1 font-semibold text-indigo-700">
                                        {{ $protocol->user->name ?? 'Unbekannt' }}
                                    </span>
                                    @if($protocol->location)
                                        <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700">
                                            {{ $protocol->location }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ (int) ($protocol->dispatch_count ?? 0) > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ (int) ($protocol->dispatch_count ?? 0) > 0 ? 'Versendet' : 'Noch offen' }}
                            </span>
                        </div>

                        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3">
                            @if((int) ($protocol->dispatch_count ?? 0) > 0)
                                <div class="text-sm font-semibold text-slate-900">
                                    {{ $protocol->dispatch_count }} Empfaenger erreicht
                                </div>
                                <div class="mt-1 text-sm text-slate-500">
                                    Zuletzt an {{ $protocol->last_recipient_name ?: 'unbekannt' }}
                                    @if($protocol->last_dispatched_at)
                                        · {{ $protocol->last_dispatched_at->format('d.m.Y H:i') }}
                                    @endif
                                </div>
                                @if($protocol->last_recipient_reference)
                                    <div class="mt-1 text-xs text-slate-500">{{ $protocol->last_recipient_reference }}</div>
                                @endif
                            @else
                                <div class="text-sm font-semibold text-slate-900">Noch nicht versendet</div>
                                <div class="mt-1 text-sm text-slate-500">Dieses Protokoll wurde bisher noch an niemanden per Mail verteilt.</div>
                            @endif
                        </div>

                        <div class="mt-5 grid gap-3 {{ $canManageProtocols ? 'sm:grid-cols-3' : 'sm:grid-cols-1' }}">
                            <a href="{{ route('protocols.show', $protocol) }}"
                               class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                                Ansehen
                            </a>
                            @if($canManageProtocols)
                                <a href="{{ route('protocols.edit', $protocol) }}"
                                   class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Bearbeiten
                                </a>
                                <a href="{{ route('protocols.mail.form', $protocol) }}"
                                   class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Versenden
                                </a>
                            @endif
                        </div>

                        @if($canManageProtocols)
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <form method="POST" action="{{ route('protocols.archive', $protocol) }}" onsubmit="return confirm('Protokoll wirklich archivieren?');">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                        Archivieren
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('protocols.destroy', $protocol) }}" onsubmit="return confirm('Protokoll wirklich loeschen?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                        Loeschen
                                    </button>
                                </form>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
