@extends('layouts.app')

@section('title', 'Aktivitätsarten')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-10 sm:px-6 lg:px-8 space-y-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900">Aktivitätsarten</h1>
            <p class="mt-2 text-sm text-slate-600">Definiere, wie sich Training, Spiel, Arbeitseinsatz oder Sitzung in Clubano verhalten sollen.</p>
        </div>
        <a href="{{ route('events.index') }}" class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Zurück zu Veranstaltungen
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">
            <ul class="list-disc pl-5 text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $tenant = auth()->user()->tenant;
        $publicListUrl = route('events.public.index', $tenant->slug);
        $embedListUrl = route('events.public.embed', $tenant->slug);
    @endphp

    <div class="grid gap-6 lg:grid-cols-[0.85fr_1.15fr]">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-slate-900">Neue Aktivitätsart</h2>
            <form method="POST" action="{{ route('event-categories.store') }}" class="mt-5 space-y-4">
                @csrf

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Slug für Filter/URL</label>
                    <input type="text" name="slug" value="{{ old('slug') }}" placeholder="z. B. braukurse" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Farbe</label>
                    <input type="color" name="color" value="{{ old('color', '#2563EB') }}" class="h-12 w-20 rounded-xl border border-slate-300 bg-white p-1">
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Symbol</label>
                        <select name="icon" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                            @foreach(['calendar' => 'Kalender', 'bolt' => 'Training / Aktiv', 'trophy' => 'Spiel / Wettkampf', 'wrench' => 'Arbeitseinsatz', 'users' => 'Gruppe', 'clipboard' => 'Sitzung'] as $iconValue => $iconLabel)
                                <option value="{{ $iconValue }}" @selected(old('icon', 'calendar') === $iconValue)>{{ $iconLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Standard-Zielgruppe</label>
                        <select name="default_target_tag_id" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Keine Vorgabe</option>
                            @foreach($targetTags as $tag)
                                <option value="{{ $tag->id }}" @selected((string) old('default_target_tag_id') === (string) $tag->id)>{{ $tag->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Sichtbarkeit als Vorgabe</label>
                    <select name="default_visibility" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="public" @selected(old('default_visibility', 'public') === 'public')>Öffentlich</option>
                        <option value="internal" @selected(old('default_visibility') === 'internal')>Intern</option>
                    </select>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-sm font-semibold text-slate-900">Was soll Clubano vorbereiten?</div>
                    <div class="mt-3 grid gap-2 text-sm text-slate-700">
                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="response_required_default" value="1" @checked(old('response_required_default')) class="rounded border-slate-300">
                            Rückmeldung erwarten
                        </label>
                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="attendance_enabled_default" value="1" @checked(old('attendance_enabled_default')) class="rounded border-slate-300">
                            Anwesenheit erfassen
                        </label>
                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="counts_toward_required_hours_default" value="1" @checked(old('counts_toward_required_hours_default')) class="rounded border-slate-300">
                            Stunden zählen normalerweise zu Pflichtstunden
                        </label>
                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="reminders_enabled_default" value="1" @checked(old('reminders_enabled_default')) class="rounded border-slate-300">
                            Erinnerungen vorbereiten
                        </label>
                    </div>
                </div>

                <button type="submit" class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                    Aktivitätsart speichern
                </button>
            </form>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-slate-900">Einbettung für eure Website</h2>

            <div class="mt-5 space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Gesamte Veranstaltungsseite</label>
                    <input type="text" readonly value="{{ $publicListUrl }}" class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Embed-URL für alle öffentlichen Veranstaltungen</label>
                    <input type="text" readonly value="{{ $embedListUrl }}" class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Iframe-Code</label>
                    <textarea readonly rows="4" class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm">{{ '<iframe src="' . $embedListUrl . '" width="100%" height="980" style="border:0;max-width:100%;" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>' }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 px-6 py-4">
            <h2 class="text-xl font-semibold text-slate-900">Vorhandene Aktivitätsarten</h2>
        </div>

        <div class="divide-y divide-slate-200">
            @forelse($categories as $category)
                <div class="p-6">
                    <div class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
                        <form method="POST" action="{{ route('event-categories.update', $category) }}" class="grid gap-4">
                            @csrf
                            @method('PUT')

                            <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_90px] md:items-end">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                                    <input type="text" name="name" value="{{ $category->name }}" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Slug</label>
                                    <input type="text" name="slug" value="{{ $category->slug }}" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Farbe</label>
                                    <input type="color" name="color" value="{{ $category->color }}" class="h-11 w-16 rounded-xl border border-slate-300 bg-white p-1">
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Symbol</label>
                                    <select name="icon" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                        @foreach(['calendar' => 'Kalender', 'bolt' => 'Training / Aktiv', 'trophy' => 'Spiel / Wettkampf', 'wrench' => 'Arbeitseinsatz', 'users' => 'Gruppe', 'clipboard' => 'Sitzung'] as $iconValue => $iconLabel)
                                            <option value="{{ $iconValue }}" @selected(($category->icon ?: 'calendar') === $iconValue)>{{ $iconLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Standard-Zielgruppe</label>
                                    <select name="default_target_tag_id" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                        <option value="">Keine Vorgabe</option>
                                        @foreach($targetTags as $tag)
                                            <option value="{{ $tag->id }}" @selected((string) $category->default_target_tag_id === (string) $tag->id)>{{ $tag->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Sichtbarkeit</label>
                                    <select name="default_visibility" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                        <option value="public" @selected($category->default_visibility === 'public')>Öffentlich</option>
                                        <option value="internal" @selected($category->default_visibility === 'internal')>Intern</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 sm:grid-cols-2">
                                <label class="flex items-center gap-3">
                                    <input type="checkbox" name="response_required_default" value="1" @checked($category->response_required_default) class="rounded border-slate-300">
                                    Rückmeldung erwarten
                                </label>
                                <label class="flex items-center gap-3">
                                    <input type="checkbox" name="attendance_enabled_default" value="1" @checked($category->attendance_enabled_default) class="rounded border-slate-300">
                                    Anwesenheit erfassen
                                </label>
                                <label class="flex items-center gap-3">
                                    <input type="checkbox" name="counts_toward_required_hours_default" value="1" @checked($category->counts_toward_required_hours_default) class="rounded border-slate-300">
                                    Zählt zu Pflichtstunden
                                </label>
                                <label class="flex items-center gap-3">
                                    <input type="checkbox" name="reminders_enabled_default" value="1" @checked($category->reminders_enabled_default) class="rounded border-slate-300">
                                    Erinnerungen
                                </label>
                            </div>

                            <div>
                                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                                    Aktualisieren
                                </button>
                            </div>
                        </form>

                        <div class="space-y-3">
                            <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700">
                                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $category->color }}"></span>
                                {{ $category->events_count }} Veranstaltung{{ $category->events_count === 1 ? '' : 'en' }}
                            </div>

                            <div class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-600">
                                <div class="font-semibold text-slate-900">Vorgabe</div>
                                <div class="mt-2 space-y-1">
                                    <div>Zielgruppe: {{ $category->defaultTargetTag?->name ?? 'keine' }}</div>
                                    <div>{{ $category->default_visibility === 'internal' ? 'Intern' : 'Öffentlich' }}</div>
                                    <div>
                                        {{ collect([
                                            $category->response_required_default ? 'Rückmeldung' : null,
                                            $category->attendance_enabled_default ? 'Anwesenheit' : null,
                                            $category->counts_toward_required_hours_default ? 'Pflichtstunden' : null,
                                            $category->reminders_enabled_default ? 'Erinnerung' : null,
                                        ])->filter()->implode(' · ') ?: 'Keine Automatik' }}
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Embed-URL dieser Kategorie</label>
                                <input type="text" readonly value="{{ $embedListUrl . '?category=' . $category->slug }}" class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm">
                            </div>

                            <form method="POST" action="{{ route('event-categories.destroy', $category) }}" onsubmit="return confirm('Aktivitätsart wirklich löschen? Veranstaltungen bleiben erhalten und verlieren nur die Zuordnung.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-medium text-red-600 hover:underline">
                                    Kategorie löschen
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-6 py-10 text-center text-slate-500">
                    Noch keine Event-Kategorien vorhanden.
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
