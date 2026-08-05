@extends('layouts.app')

@section('title', $document->title)

@section('content')
@php
    $canManageDocuments = auth()->user()?->canManageDocuments() ?? false;
    $canDeleteDocuments = auth()->user()?->isAdmin() ?? false;
    $contextLinks = [
        ['label' => 'Mitglied', 'value' => $document->member?->full_name, 'route' => $document->member ? route('members.show', $document->member) : null],
        ['label' => 'Projekt', 'value' => $document->project?->name, 'route' => $document->project ? route('projects.show', $document->project) : null],
        ['label' => 'Termin', 'value' => $document->event?->title, 'route' => $document->event ? route('events.show', $document->event) : null],
        ['label' => 'Protokoll', 'value' => $document->protocol?->title, 'route' => $document->protocol ? route('protocols.show', $document->protocol) : null],
        ['label' => 'Rechnung', 'value' => $document->invoice?->invoice_number, 'route' => $document->invoice ? route('invoices.show', $document->invoice) : null],
    ];
@endphp

<div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-2xl bg-slate-950 px-6 py-6 text-white sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">{{ $document->category_label }}</div>
                <h1 class="mt-3 truncate text-3xl font-semibold tracking-tight sm:text-4xl">{{ $document->title }}</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300">
                    {{ $document->original_name }} · {{ $document->human_size }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('documents.download', $document) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-white px-4 text-sm font-semibold text-slate-950 hover:bg-slate-100">
                    <x-heroicon-o-arrow-down-tray class="h-5 w-5" />
                    Download
                </a>
                @if($canManageDocuments)
                    <a href="{{ route('documents.edit', $document) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-white/20 px-4 text-sm font-semibold text-white hover:bg-white/10">
                        Bearbeiten
                    </a>
                @endif
            </div>
        </div>
    </section>

    @if (session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <section class="grid gap-5 lg:grid-cols-[minmax(0,1fr),340px]">
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-lg font-semibold text-slate-950">Einordnung</h2>
            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Status</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-950">{{ $document->status_label }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Kategorie</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-950">{{ $document->category_label }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Dokumentdatum</dt>
                    <dd class="mt-1 text-sm text-slate-700">{{ $document->document_date?->format('d.m.Y') ?? 'Nicht gesetzt' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Ablaufdatum</dt>
                    <dd class="mt-1 text-sm text-slate-700">{{ $document->expires_at?->format('d.m.Y') ?? 'Keine Frist' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Hochgeladen von</dt>
                    <dd class="mt-1 text-sm text-slate-700">{{ $document->uploader?->name ?? 'Unbekannt' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Letzte Änderung</dt>
                    <dd class="mt-1 text-sm text-slate-700">{{ $document->updated_at->format('d.m.Y H:i') }} Uhr</dd>
                </div>
            </dl>

            @if($document->description)
                <div class="mt-6 border-t border-slate-100 pt-5">
                    <h2 class="text-lg font-semibold text-slate-950">Notiz</h2>
                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $document->description }}</p>
                </div>
            @endif

            @if(! empty($document->tags))
                <div class="mt-6 flex flex-wrap gap-2 border-t border-slate-100 pt-5">
                    @foreach($document->tags as $tag)
                        <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $tag }}</span>
                    @endforeach
                </div>
            @endif
        </article>

        <aside class="space-y-5">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">Verknüpfungen</h2>
                <div class="mt-4 divide-y divide-slate-100">
                    @foreach($contextLinks as $link)
                        <div class="py-3 first:pt-0 last:pb-0">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $link['label'] }}</div>
                            @if($link['value'])
                                <a href="{{ $link['route'] }}" class="mt-1 block text-sm font-semibold text-indigo-700 hover:text-indigo-800">{{ $link['value'] }}</a>
                            @else
                                <div class="mt-1 text-sm text-slate-500">Nicht verknüpft</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            @if($canManageDocuments)
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Aktionen</h2>
                    <div class="mt-4 space-y-3">
                        <form method="POST" action="{{ route('documents.archive', $document) }}" onsubmit="return confirm('Dokument wirklich archivieren?');">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Archivieren
                            </button>
                        </form>

                        @if($canDeleteDocuments)
                            <form method="POST" action="{{ route('documents.destroy', $document) }}" onsubmit="return confirm('Dokument endgültig löschen?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-rose-200 px-4 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                                    Endgültig löschen
                                </button>
                            </form>
                        @endif
                    </div>
                </section>
            @endif
        </aside>
    </section>
</div>
@endsection
