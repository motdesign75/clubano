@extends('layouts.app')

@section('title', 'Importbericht')

@section('content')
@php
    $summary = $importRun->summary ?? [];
    $readiness = $summary['readiness'] ?? [];
    $skippedRows = $summary['skipped_rows'] ?? [];
    $mappedFields = $summary['mapped_fields'] ?? [];
@endphp

<div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-2xl bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <a href="{{ route('import.index') }}" class="text-sm font-semibold text-slate-300 hover:text-white">Import-Cockpit</a>
                <div class="mt-3 text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">Importbericht</div>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $config['label'] }} übernommen</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    {{ $importRun->filename ?: 'CSV-Datei' }} · {{ $summary['source_profile_label'] ?? 'Excel / freie CSV' }} · {{ $importRun->created_at->format('d.m.Y H:i') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('import.report.export', $importRun) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-white px-4 text-sm font-semibold text-slate-950 hover:bg-slate-100">
                    <x-heroicon-o-arrow-down-tray class="h-5 w-5" />
                    Bericht laden
                </a>
                <a href="{{ route($config['index_route']) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-white/20 px-4 text-sm font-semibold text-white hover:bg-white/10">
                    Weiteren Import starten
                </a>
            </div>
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <section class="grid gap-4 md:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Erkannt</div>
            <div class="mt-2 text-3xl font-semibold text-slate-950">{{ $importRun->row_count }}</div>
            <div class="mt-1 text-sm text-slate-500">Zeilen in der Datei</div>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5">
            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700">Importiert</div>
            <div class="mt-2 text-3xl font-semibold text-emerald-900">{{ $importRun->imported_count }}</div>
            <div class="mt-1 text-sm text-emerald-700">neue Datensätze</div>
        </div>
        <div class="rounded-xl border {{ $importRun->skipped_count > 0 ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-white' }} p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.22em] {{ $importRun->skipped_count > 0 ? 'text-amber-700' : 'text-slate-500' }}">Übersprungen</div>
            <div class="mt-2 text-3xl font-semibold text-slate-950">{{ $importRun->skipped_count }}</div>
            <div class="mt-1 text-sm {{ $importRun->skipped_count > 0 ? 'text-amber-700' : 'text-slate-500' }}">Fehler oder Dubletten</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Bereitschaft</div>
            <div class="mt-2 text-3xl font-semibold text-slate-950">{{ $readiness['score'] ?? '-' }}{{ isset($readiness['score']) ? '%' : '' }}</div>
            <div class="mt-1 text-sm text-slate-500">vor dem Import</div>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-950">Importdetails</h2>
            </div>
            <div class="divide-y divide-slate-100 text-sm">
                <div class="grid gap-1 px-5 py-4 sm:grid-cols-3">
                    <div class="font-semibold text-slate-900">Ziel</div>
                    <div class="text-slate-600 sm:col-span-2">{{ ($summary['import_goal'] ?? '') ?: 'Nicht angegeben' }}</div>
                </div>
                <div class="grid gap-1 px-5 py-4 sm:grid-cols-3">
                    <div class="font-semibold text-slate-900">Quelle</div>
                    <div class="text-slate-600 sm:col-span-2">{{ $summary['source_profile_label'] ?? 'Excel / freie CSV' }}</div>
                </div>
                <div class="grid gap-1 px-5 py-4 sm:grid-cols-3">
                    <div class="font-semibold text-slate-900">Zugeordnete Felder</div>
                    <div class="flex flex-wrap gap-2 sm:col-span-2">
                        @forelse($mappedFields as $field)
                            <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">{{ $field }}</span>
                        @empty
                            <span class="text-slate-500">Keine Feldliste gespeichert.</span>
                        @endforelse
                    </div>
                </div>
                <div class="grid gap-1 px-5 py-4 sm:grid-cols-3">
                    <div class="font-semibold text-slate-900">Dubletten</div>
                    <div class="text-slate-600 sm:col-span-2">
                        {{ $summary['duplicate_count'] ?? 0 }} mögliche Treffer · {{ $summary['duplicate_strategy_label'] ?? 'Dubletten überspringen' }}
                    </div>
                </div>
                @if($importRun->import_type === 'members')
                    <div class="grid gap-1 px-5 py-4 sm:grid-cols-3">
                        <div class="font-semibold text-slate-900">Mitgliedschaften</div>
                        <div class="text-slate-600 sm:col-span-2">
                            {{ $summary['membership_strategy_label'] ?? 'Beitragswerte nur am Mitglied speichern' }}
                            @if(($summary['created_membership_count'] ?? 0) > 0)
                                · {{ $summary['created_membership_count'] }} neu angelegt
                            @endif
                        </div>
                    </div>
                    <div class="grid gap-1 px-5 py-4 sm:grid-cols-3">
                        <div class="font-semibold text-slate-900">Zusatzspalten</div>
                        <div class="text-slate-600 sm:col-span-2">
                            {{ $summary['custom_field_strategy_label'] ?? 'Ignorierte Spalten nicht übernehmen' }}
                            @if(($summary['created_custom_field_count'] ?? 0) > 0)
                                · {{ $summary['created_custom_field_count'] }} eigene Felder neu angelegt
                            @endif
                        </div>
                    </div>
                @endif
                <div class="grid gap-1 px-5 py-4 sm:grid-cols-3">
                    <div class="font-semibold text-slate-900">Status</div>
                    <div class="text-slate-600 sm:col-span-2">
                        @if($importRun->status === 'undone')
                            Rückgängig gemacht am {{ $importRun->undone_at?->format('d.m.Y H:i') }}
                        @else
                            Import abgeschlossen
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">Nächste Schritte</h2>
            <div class="mt-4 space-y-3">
                @foreach($nextSteps as $step)
                    <div class="flex gap-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                        <x-heroicon-o-check-circle class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                        <span>{{ $step }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @if(count($skippedRows) > 0)
        <section class="rounded-xl border border-amber-200 bg-amber-50 shadow-sm">
            <div class="border-b border-amber-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-amber-950">Übersprungene Zeilen</h2>
                <p class="mt-1 text-sm text-amber-800">Clubano zeigt die ersten 50 Hinweise. Die Originaldatei bleibt die Grundlage für Korrekturen.</p>
            </div>
            <div class="divide-y divide-amber-100">
                @foreach($skippedRows as $row)
                    <div class="grid gap-1 px-5 py-3 text-sm sm:grid-cols-[8rem_1fr]">
                        <div class="font-semibold text-amber-950">Zeile {{ $row['row'] }}</div>
                        <div class="text-amber-800">
                            {{ $row['reason'] }}
                            @if($row['incoming'] ?? null)
                                <span class="text-amber-700">· {{ $row['incoming'] }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if($importRun->isUndoable())
        <section class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-slate-950">Import zurücksetzen</h2>
                <p class="mt-1 text-sm text-slate-500">Solange keine Folgeaktionen an den importierten Daten hängen, kann dieser Import zurückgenommen werden.</p>
            </div>
            <form method="POST" action="{{ route('import.mitglieder.undo', $importRun) }}" onsubmit="return confirm('Diesen Import wirklich rückgängig machen?')">
                @csrf
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-4 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                    Rückgängig machen
                </button>
            </form>
        </section>
    @endif
</div>
@endsection
