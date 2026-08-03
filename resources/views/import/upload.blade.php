@extends('layouts.app')

@section('title', $config['title'])

@section('content')
<div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-3xl">
                <a href="{{ route('import.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-900">Import-Cockpit</a>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ $config['title'] }}</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">{{ $config['description'] }}</p>
            </div>
            <a href="{{ $config['type'] === 'contacts' ? route('import.mitglieder') : route('import.kontakte') }}"
               class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                {{ $config['type'] === 'contacts' ? 'Mitgliederimport' : 'Kontaktimport' }}
            </a>
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <div class="font-semibold">Bitte prüfe den Upload.</div>
            <ul class="mt-2 list-inside list-disc">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <form action="{{ route($config['upload_route']) }}" method="POST" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div class="grid gap-4 lg:grid-cols-[1fr_1fr]">
                <div>
                    <label for="source_profile" class="text-sm font-semibold text-slate-900">Aus welchem System kommen die Daten?</label>
                    <select
                        name="source_profile"
                        id="source_profile"
                        class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:ring-slate-300"
                    >
                        @foreach($config['sources'] as $sourceKey => $sourceLabel)
                            <option value="{{ $sourceKey }}">{{ $sourceLabel }}</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Die Auswahl hilft Clubano, Spalten besser einzuordnen und den Importbericht verständlicher zu machen.
                    </p>
                </div>

                <div>
                    <label for="import_goal" class="text-sm font-semibold text-slate-900">Was soll dieser Import erreichen?</label>
                    <input
                        type="text"
                        name="import_goal"
                        id="import_goal"
                        maxlength="120"
                        placeholder="z. B. Erstimport vor Vereinsstart"
                        class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:ring-slate-300"
                    >
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Optional, aber hilfreich für spätere Nachvollziehbarkeit.
                    </p>
                </div>
            </div>

            <div>
                <label for="csv_file" class="text-sm font-semibold text-slate-900">CSV-Datei auswählen</label>
                <input
                    type="file"
                    name="csv_file"
                    id="csv_file"
                    accept=".csv,.txt,.xlsx,.xls"
                    required
                    class="mt-2 block w-full rounded-lg border border-slate-300 p-2 text-sm file:mr-4 file:rounded-md file:border-0 file:bg-slate-950 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-800"
                >
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Clubano akzeptiert CSV sowie Excel-Dateien im Format .xlsx und .xls. Die erste Zeile muss die Spaltenüberschriften enthalten.
                </p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <h2 class="text-sm font-semibold text-slate-950">So läuft der Import</h2>
                <div class="mt-3 grid gap-3 text-sm text-slate-600 sm:grid-cols-4">
                    <div class="rounded-lg bg-white p-3 ring-1 ring-slate-200">
                        <div class="font-semibold text-slate-900">1. Quelle</div>
                        <div class="mt-1">System und Ziel festlegen.</div>
                    </div>
                    <div class="rounded-lg bg-white p-3 ring-1 ring-slate-200">
                        <div class="font-semibold text-slate-900">2. Datei</div>
                        <div class="mt-1">CSV hochladen.</div>
                    </div>
                    <div class="rounded-lg bg-white p-3 ring-1 ring-slate-200">
                        <div class="font-semibold text-slate-900">3. Prüfung</div>
                        <div class="mt-1">Spalten und Warnungen sehen.</div>
                    </div>
                    <div class="rounded-lg bg-white p-3 ring-1 ring-slate-200">
                        <div class="font-semibold text-slate-900">4. Bericht</div>
                        <div class="mt-1">Ergebnis nachvollziehen.</div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                    <x-heroicon-o-eye class="h-5 w-5" />
                    Vorschau anzeigen
                </button>
            </div>
        </form>
    </section>

    @if($recentImportRuns->isNotEmpty())
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-950">Letzte {{ $config['label'] }}-Importe</h2>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach($recentImportRuns as $run)
                    <div class="px-5 py-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-semibold text-slate-950">{{ $run->imported_count }} importiert</span>
                                    @if($run->summary['source_profile_label'] ?? null)
                                        <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">{{ $run->summary['source_profile_label'] }}</span>
                                    @endif
                                    @if(($run->skipped_count ?? 0) > 0)
                                        <span class="rounded-md bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">{{ $run->skipped_count }} übersprungen</span>
                                    @endif
                                    @if($run->status === 'undone')
                                        <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">Rückgängig gemacht</span>
                                    @else
                                        <span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">Importiert</span>
                                    @endif
                                </div>
                                <div class="mt-1 text-sm text-slate-500">{{ $run->created_at->format('d.m.Y H:i') }}</div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('import.report', $run) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                    Bericht
                                </a>
                                @if($run->isUndoable())
                                    <form method="POST" action="{{ route('import.mitglieder.undo', $run) }}" onsubmit="return confirm('Diesen Import wirklich rückgängig machen?')">
                                        @csrf
                                        <button type="submit" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                                            Rückgängig machen
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
