@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-3xl bg-slate-950 px-6 py-6 text-white sm:px-8">
        <div class="max-w-3xl">
            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Vorlageneditor</div>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Vorlage bearbeiten</h1>
            <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                Schärfe Inhalt, Platzhalter und Betreff, damit die Vorlage im Versand zuverlässig wirkt.
            </p>
        </div>
    </section>

    <form id="templateForm"
          method="POST"
          action="{{ route('templates.update', $template->id) }}">

        @csrf
        @method('PUT')

        @include('templates.form')
    </form>
</div>
@endsection
