@extends('layouts.app')

@section('title', 'Rollen und Rechte')

@section('content')
<div class="mx-auto max-w-6xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
    <div class="space-y-3">
        <span class="inline-flex rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-indigo-700">
            Rollenmodell
        </span>
        <div class="space-y-2">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Klar wie ein Vorstandsposten</h1>
            <p class="max-w-3xl text-sm leading-6 text-slate-600 sm:text-base">
                Clubano arbeitet mit festen, verständlichen Rollen. Keine Checkbox-Matrix, keine versteckten Sonderfälle:
                Kasse sieht Finanzen, Schriftführung arbeitet an Protokollen und Dokumenten, Veranstaltungen plant Termine.
            </p>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        @foreach ($profiles as $profile)
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-xl font-semibold text-slate-900">{{ $profile['label'] }}</h2>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ match($profile['role']) {
                            \App\Models\User::ROLE_ADMIN => 'bg-indigo-100 text-indigo-700',
                            \App\Models\User::ROLE_STAFF => 'bg-amber-100 text-amber-700',
                            \App\Models\User::ROLE_TREASURER => 'bg-emerald-100 text-emerald-700',
                            \App\Models\User::ROLE_SECRETARY => 'bg-sky-100 text-sky-700',
                            \App\Models\User::ROLE_EVENT_MANAGER => 'bg-violet-100 text-violet-700',
                            default => 'bg-slate-100 text-slate-700',
                        } }}">
                            {{ $profile['role'] }}
                        </span>
                    </div>

                    <p class="text-sm leading-6 text-slate-600">
                        {{ $profile['description'] }}
                    </p>
                </div>

                <div class="mt-6 space-y-3">
                    @foreach ($profile['access'] as $access)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="text-sm font-medium text-slate-800">{{ $access['area'] }}</div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $access['level'] === 'voll' ? 'bg-emerald-100 text-emerald-700' : ($access['level'] === 'bearbeiten' ? 'bg-amber-100 text-amber-700' : ($access['level'] === 'lesen' ? 'bg-blue-100 text-blue-700' : 'bg-rose-100 text-rose-700')) }}">
                                    {{ match($access['level']) {
                                        'voll' => 'Vollzugriff',
                                        'bearbeiten' => 'Bearbeiten',
                                        'lesen' => 'Lesen',
                                        default => 'Kein Zugriff',
                                    } }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    <section class="rounded-3xl border border-slate-200 bg-slate-950 p-6 text-white shadow-sm">
        <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr] lg:items-start">
            <div class="space-y-3">
                <h2 class="text-xl font-semibold">Empfohlener Standard für Vereine</h2>
                <p class="max-w-3xl text-sm leading-6 text-slate-300">
                    In den meisten Vereinen reicht dieses Modell vollkommen:
                    ein oder zwei Admins für Leitung und Einstellungen, Kasse für Finanzen, Schriftführung für Protokolle,
                    Veranstaltungen für Termine und Lesen für Vorstand, Beirat oder Kassenprüfung.
                </p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <div class="text-sm font-semibold text-white">Merksatz</div>
                <ul class="mt-3 space-y-2 text-sm text-slate-300">
                    <li>`Admin` verwaltet den Verein.</li>
                    <li>`Kasse` verwaltet Geld, Rechnungen und SEPA.</li>
                    <li>`Schriftführung` verwaltet Protokolle und Dokumente.</li>
                    <li>`Veranstaltungen` verwaltet Termine, Dienstpläne und Anwesenheiten.</li>
                    <li>`Lesen` schaut nur hinein.</li>
                </ul>
            </div>
        </div>
    </section>
</div>
@endsection
