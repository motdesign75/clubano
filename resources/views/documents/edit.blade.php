@extends('layouts.app')

@section('title', 'Dokument bearbeiten')

@section('content')
<div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-2xl bg-slate-950 px-6 py-6 text-white sm:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">Dokumentenzentrale</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight">Dokument bearbeiten</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">
                    Passe Kategorie, Fristen, Beschreibung oder die Datei an.
                </p>
            </div>
            <a href="{{ route('documents.show', $document) }}" class="inline-flex items-center justify-center rounded-lg border border-white/20 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/10">
                Zur Detailseite
            </a>
        </div>
    </section>

    @include('documents.partials.form', [
        'action' => route('documents.update', $document),
        'method' => 'PUT',
        'document' => $document,
    ])
</div>
@endsection
