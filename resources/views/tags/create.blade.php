@extends('layouts.app')

@section('title', 'Markierung anlegen')

@section('content')
<div class="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-2xl bg-slate-950 text-white shadow-sm">
        <div class="bg-[linear-gradient(135deg,#020617_0%,#0f3a3a_58%,#1f2937_100%)] p-6 sm:p-8">
            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-white/55">Verein verwalten</div>
            <h1 class="mt-5 text-3xl font-semibold tracking-tight text-white">Markierung anlegen</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-white/68">
                Markierungen sollten so benannt sein, dass jeder im Verein sofort versteht, wer oder was gemeint ist.
            </p>
        </div>
    </section>

    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <div class="font-semibold">Bitte prüfe deine Eingaben.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('tags.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        @csrf

        <div class="grid gap-6 md:grid-cols-[minmax(0,1fr),220px]">
            <div>
                <label for="name" class="block text-sm font-semibold text-slate-800">Name</label>
                <input type="text"
                       name="name"
                       id="name"
                       value="{{ old('name') }}"
                       class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300"
                       placeholder="z. B. Vorstand, Jugend, Sponsoren"
                       required>
                <p class="mt-2 text-sm leading-6 text-slate-500">Wähle einen kurzen, eindeutigen Namen.</p>
            </div>

            <div>
                <label for="color" class="block text-sm font-semibold text-slate-800">Farbe</label>
                <div class="mt-2 flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                    <input type="color"
                           name="color"
                           id="color"
                           value="{{ old('color', '#2954A3') }}"
                           class="h-11 w-14 cursor-pointer rounded-lg border border-slate-300 bg-white">
                    <span class="text-sm text-slate-500">Für schnelle Wiedererkennung.</span>
                </div>
            </div>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('tags.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                Zurück zu Markierungen
            </a>
            <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white transition hover:bg-slate-800">
                <x-heroicon-o-check class="h-5 w-5" />
                Markierung speichern
            </button>
        </div>
    </form>
</div>
@endsection
