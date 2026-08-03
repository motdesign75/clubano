@extends('layouts.app')

@section('title', $config['label'] . '-Import prüfen')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <a href="{{ route($config['index_route']) }}" class="text-sm font-semibold text-slate-500 hover:text-slate-900">{{ $config['title'] }}</a>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Import prüfen</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    {{ $rowCount }} Zeile(n) aus {{ $sourceProfileLabel }} erkannt.
                    @if($fileType === 'csv')
                        Trennzeichen: <span class="font-semibold">{{ $delimiter === "\t" ? 'Tabulator' : $delimiter }}</span>.
                    @else
                        Datei: <span class="font-semibold">{{ strtoupper($fileType) }}</span>.
                    @endif
                </p>
            </div>
            <a href="{{ route($config['index_route']) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Andere Datei wählen
            </a>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-4">
        <div class="rounded-xl border {{ $readiness['state'] === 'ready' ? 'border-emerald-200 bg-emerald-50' : ($readiness['state'] === 'check' ? 'border-amber-200 bg-amber-50' : 'border-rose-200 bg-rose-50') }} p-5">
            <div class="text-xs font-semibold uppercase tracking-[0.22em] {{ $readiness['state'] === 'ready' ? 'text-emerald-700' : ($readiness['state'] === 'check' ? 'text-amber-700' : 'text-rose-700') }}">Import-Bereitschaft</div>
            <div class="mt-2 text-3xl font-semibold text-slate-950">{{ $readiness['score'] }}%</div>
            <div class="mt-1 text-sm text-slate-600">
                @if($readiness['state'] === 'ready')
                    Bereit für den Import.
                @elseif($readiness['state'] === 'check')
                    Bitte Zuordnung prüfen.
                @else
                    Es fehlen wichtige Felder.
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Spalten</div>
            <div class="mt-2 text-3xl font-semibold text-slate-950">{{ $readiness['mapped_count'] }}/{{ $readiness['header_count'] }}</div>
            <div class="mt-1 text-sm text-slate-500">automatisch zugeordnet</div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Datensätze</div>
            <div class="mt-2 text-3xl font-semibold text-slate-950">{{ $readiness['row_count'] }}</div>
            <div class="mt-1 text-sm text-slate-500">in der Datei gefunden</div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Warnungen</div>
            <div class="mt-2 text-3xl font-semibold text-slate-950">{{ $readiness['warning_count'] }}</div>
            <div class="mt-1 text-sm text-slate-500">werden dokumentiert</div>
        </div>
    </section>

    @if(count($readiness['missing_required']) > 0 || count($readiness['missing_recommended']) > 0)
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">Was Clubano noch vermisst</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <div class="text-sm font-semibold text-slate-900">Pflicht für sicheren Import</div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @forelse($readiness['missing_required'] as $label)
                            <span class="rounded-md bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700">{{ $label }}</span>
                        @empty
                            <span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">Alles vorhanden</span>
                        @endforelse
                    </div>
                </div>
                <div>
                    <div class="text-sm font-semibold text-slate-900">Empfohlen für weniger Nacharbeit</div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @forelse($readiness['missing_recommended'] as $label)
                            <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">{{ $label }}</span>
                        @empty
                            <span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">Sehr gute Datei</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if($analysis['warning_count'] > 0)
        <section class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
            <h2 class="font-semibold">{{ $analysis['warning_count'] }} mögliche Probleme erkannt</h2>
            <p class="mt-1 leading-6">Diese Zeilen würden beim Import übersprungen, wenn die Zuordnung so bleibt.</p>
            <div class="mt-3 grid gap-2">
                @foreach($analysis['warnings'] as $warning)
                    <div>Zeile {{ $warning['row'] }}: {{ $warning['reason'] }}</div>
                @endforeach
            </div>
        </section>
    @else
        <section class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-900">
            <span class="font-semibold">Die automatische Prüfung sieht gut aus.</span>
            {{ $analysis['valid_rows'] }} Zeile(n) können mit der aktuellen Zuordnung importiert werden.
        </section>
    @endif

    <form method="POST" action="{{ route($config['confirm_route']) }}" class="space-y-6">
        @csrf
        <input type="hidden" name="path" value="{{ $path }}">
        <input type="hidden" name="source_profile" value="{{ $sourceProfile }}">
        <input type="hidden" name="original_filename" value="{{ $originalFilename }}">
        <input type="hidden" name="import_goal" value="{{ $importGoal }}">

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-950">Feldzuordnung</h2>
                <p class="mt-1 text-sm text-slate-500">Prüfe jede Spalte. Nicht benötigte Spalten bleiben auf „Ignorieren“.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            @foreach($headers as $i => $header)
                                <th class="min-w-56 border-b border-r border-slate-200 px-3 py-3 text-left align-top">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $header ?: 'Spalte ' . ($i + 1) }}</div>
                                    <select name="mapping[{{ $i }}]" class="mt-2 w-full rounded-lg border-slate-300 text-xs focus:border-slate-500 focus:ring-slate-300">
                                        <option value="skip">Ignorieren</option>
                                        @foreach($fieldOptions as $group => $fields)
                                            <optgroup label="{{ $group }}">
                                                @foreach($fields as $field => $label)
                                                    <option value="{{ $field }}" @selected(($suggestedMapping[$i] ?? 'skip') === $field)>{{ $label }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr class="odd:bg-white even:bg-slate-50/60">
                                @foreach($headers as $i => $header)
                                    <td class="max-w-72 border-b border-r border-slate-100 px-3 py-2 text-slate-700">
                                        <div class="truncate">{{ $row[$i] ?? '' }}</div>
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ max(count($headers), 1) }}" class="px-5 py-10 text-center text-slate-500">Keine Vorschauzeilen gefunden.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm leading-6 text-slate-500">
                Dubletten und unvollständige Zeilen werden beim Import übersprungen und im Importlauf dokumentiert.
            </div>
            <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                <x-heroicon-o-check-circle class="h-5 w-5" />
                {{ $config['label'] }} importieren
            </button>
        </section>
    </form>
</div>
@endsection
