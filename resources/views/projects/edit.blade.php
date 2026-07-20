@extends('layouts.app')

@section('title', 'Projekt bearbeiten')

@section('content')
<div class="mx-auto max-w-5xl space-y-6 px-4 py-4 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <div class="text-sm text-slate-500">
                <a href="{{ route('projects.index') }}" class="hover:text-slate-700 hover:underline">Projekte</a>
                <span class="mx-1">›</span>
                <a href="{{ route('projects.show', $project) }}" class="hover:text-slate-700 hover:underline">{{ $project->name }}</a>
                <span class="mx-1">›</span>
                <span>Bearbeiten</span>
            </div>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">Projekt bearbeiten</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                Halte das Projekt klar, aktuell und für alle im Verein sofort verständlich.
            </p>
        </div>

        <a href="{{ route('projects.show', $project) }}"
           class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
            Zurück zum Projekt
        </a>
    </div>

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-900 shadow-sm">
            <div class="font-semibold">Bitte kurz prüfen.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('projects.update', $project) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-5">
                <h2 class="text-lg font-semibold text-slate-900">Worum geht es?</h2>
                <p class="mt-1 text-sm text-slate-500">Der Kern des Projekts sollte schnell lesbar sein.</p>
            </div>

            <div class="space-y-6 px-6 py-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700">Projektname</label>
                    <input type="text" name="name" id="name"
                           value="{{ old('name', $project->name) }}"
                           required maxlength="255"
                           class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700">Beschreibung</label>
                    <textarea name="description" id="description" rows="6"
                              class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $project->description) }}</textarea>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.7fr)]">
            <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-5">
                    <h2 class="text-lg font-semibold text-slate-900">Wie läuft es?</h2>
                    <p class="mt-1 text-sm text-slate-500">Status und Zeitraum gehören sichtbar zusammen.</p>
                </div>

                <div class="grid gap-6 px-6 py-6 sm:grid-cols-2">
                    <div>
                        <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
                        <select name="status" id="status"
                                class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="active" @selected(old('status', $project->status) === 'active')>Aktiv</option>
                            <option value="on_hold" @selected(old('status', $project->status) === 'on_hold')>Pausiert</option>
                            <option value="done" @selected(old('status', $project->status) === 'done')>Abgeschlossen</option>
                        </select>
                    </div>

                    <div>
                        <label for="owner_id" class="block text-sm font-medium text-slate-700">Verantwortlich</label>
                        <select name="owner_id" id="owner_id"
                                class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Unverändert lassen</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected((string) old('owner_id', $project->owner_id) === (string) $user->id)>
                                    {{ $user->name }}@if($user->email) · {{ $user->email }}@endif
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-slate-500">Zuständigkeiten gehen an interne Benutzer deines Vereins.</p>
                    </div>

                    <div>
                        <label for="starts_at" class="block text-sm font-medium text-slate-700">Start</label>
                        <input type="date" name="starts_at" id="starts_at"
                               value="{{ old('starts_at', optional($project->starts_at)->toDateString()) }}"
                               class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>

                    <div>
                        <label for="ends_at" class="block text-sm font-medium text-slate-700">Ende</label>
                        <input type="date" name="ends_at" id="ends_at"
                               value="{{ old('ends_at', optional($project->ends_at)->toDateString()) }}"
                               class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-rose-200 bg-rose-50 shadow-sm">
                <div class="border-b border-rose-100 px-6 py-5">
                    <h2 class="text-lg font-semibold text-rose-900">Gefährlicher Bereich</h2>
                    <p class="mt-1 text-sm text-rose-700">Löschen lässt sich nicht rückgängig machen.</p>
                </div>

                <div class="space-y-4 px-6 py-6">
                    <p class="text-sm leading-6 text-rose-800">
                        Nutze das nur, wenn das Projekt wirklich weg soll und keine Aufgaben oder Unterlagen mehr gebraucht werden.
                    </p>

                    <form action="{{ route('projects.destroy', $project) }}" method="POST"
                          onsubmit="return confirm('Dieses Projekt wirklich dauerhaft löschen?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex w-full items-center justify-center rounded-2xl bg-rose-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-rose-700">
                            Projekt löschen
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('projects.show', $project) }}"
               class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                Abbrechen
            </a>
            <button type="submit"
                    class="inline-flex items-center justify-center rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                Änderungen speichern
            </button>
        </div>
    </form>
</div>
@endsection
