@extends('layouts.app')

@section('title', ($receiptMode ?? false) ? 'Beleg fotografieren' : 'Dokument ablegen')

@section('content')
<div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-2xl bg-slate-950 px-6 py-6 text-white sm:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">{{ ($receiptMode ?? false) ? 'Beleg-Eingang' : 'Dokumentenzentrale' }}</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight">{{ ($receiptMode ?? false) ? 'Beleg fotografieren' : 'Dokument ablegen' }}</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">
                    {{ ($receiptMode ?? false) ? 'Fotografiere den Beleg mit dem Handy oder lade eine Datei hoch. Clubano legt ihn als neuen, noch nicht gebuchten Beleg ab.' : 'Lade die Datei hoch und gib ihr genug Kontext, damit sie später sofort wiedergefunden wird.' }}
                </p>
            </div>
            <a href="{{ route('documents.index') }}" class="inline-flex items-center justify-center rounded-lg border border-white/20 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/10">
                Zur Ablage
            </a>
        </div>
    </section>

    @include('documents.partials.form', [
        'action' => route('documents.store'),
        'method' => 'POST',
        'document' => null,
        'receiptMode' => $receiptMode ?? false,
    ])
</div>
@endsection
