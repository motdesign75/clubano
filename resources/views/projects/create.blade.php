@extends('layouts.app')

@section('title', 'Neues Projekt')

@section('content')
<div class="mx-auto max-w-5xl space-y-6 px-4 py-4 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <div class="text-sm text-slate-500">
                <a href="{{ route('projects.index') }}" class="hover:text-slate-700 hover:underline">Projekte</a>
                <span class="mx-1">›</span>
                <span>Neu</span>
            </div>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">Neues Projekt</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                Lege ein Projekt so an, dass man sofort versteht, worum es geht, wer verantwortlich ist und bis wann es laufen soll.
            </p>
        </div>

        <a href="{{ route('projects.index') }}"
           class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
            Zur Übersicht
        </a>
    </div>

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-900 shadow-sm">
            <div class="font-semibold">Bitte kurz prüfen.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('projects.store') }}" class="space-y-6">
        @csrf

        <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-5">
                <h2 class="text-lg font-semibold text-slate-900">Worum geht es?</h2>
                <p class="mt-1 text-sm text-slate-500">Name und Beschreibung sollten für jeden im Verein sofort verständlich sein.</p>
            </div>

            <div class="space-y-6 px-6 py-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700">Projektname</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required
                           class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('name') <div class="mt-2 text-sm text-rose-700">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700">Beschreibung</label>
                    <textarea id="description" name="description" rows="6"
                              class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description') }}</textarea>
                    @error('description') <div class="mt-2 text-sm text-rose-700">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.7fr)]">
            <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-5">
                    <h2 class="text-lg font-semibold text-slate-900">Wie läuft es?</h2>
                    <p class="mt-1 text-sm text-slate-500">Zeitraum und Status gehören zusammen und geben sofort Orientierung.</p>
                </div>

                <div class="grid gap-6 px-6 py-6 sm:grid-cols-2">
                    <div>
                        <label for="starts_at" class="block text-sm font-medium text-slate-700">Start</label>
                        <input id="starts_at" name="starts_at" type="date" value="{{ old('starts_at') }}"
                               class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('starts_at') <div class="mt-2 text-sm text-rose-700">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label for="ends_at" class="block text-sm font-medium text-slate-700">Ende</label>
                        <input id="ends_at" name="ends_at" type="date" value="{{ old('ends_at') }}"
                               class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('ends_at') <div class="mt-2 text-sm text-rose-700">{{ $message }}</div> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
                        <select id="status" name="status"
                                class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="active" @selected(old('status', 'active') === 'active')>Aktiv</option>
                            <option value="on_hold" @selected(old('status') === 'on_hold')>Pausiert</option>
                            <option value="done" @selected(old('status') === 'done')>Abgeschlossen</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-5">
                    <h2 class="text-lg font-semibold text-slate-900">Wer hält den Faden?</h2>
                    <p class="mt-1 text-sm text-slate-500">Die Zuständigkeit liegt bei einem internen Benutzer deines Vereins.</p>
                </div>

                <div class="space-y-4 px-6 py-6">
                    <div>
                        <label for="owner_id" class="block text-sm font-medium text-slate-700">Verantwortlich</label>
                        <select id="owner_id" name="owner_id"
                                class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Automatisch mich verwenden</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected((string) old('owner_id') === (string) $user->id)>
                                    {{ $user->name }}@if($user->email) · {{ $user->email }}@endif
                                </option>
                            @endforeach
                        </select>
                        @error('owner_id') <div class="mt-2 text-sm text-rose-700">{{ $message }}</div> @enderror
                    </div>

                    <div class="rounded-2xl bg-slate-50 px-4 py-4 text-sm leading-6 text-slate-600">
                        Projekte werden an Teammitglieder mit Login vergeben. Normale Vereinsmitglieder bleiben der fachliche Bezug, nicht die System-Zuständigkeit.
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('projects.index') }}"
               class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                Abbrechen
            </a>
            <button class="inline-flex items-center justify-center rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700"
                    type="submit">
                Projekt anlegen
            </button>
        </div>
    </form>
</div>
@endsection
