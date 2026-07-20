@extends('layouts.app')

@section('title', 'Mitglieder-Import')

@section('content')
    <div class="max-w-4xl mx-auto bg-white rounded shadow p-6 space-y-6">
        <h1 class="text-2xl font-bold text-gray-800">📥 Mitgliederimport</h1>

        {{-- Session-Fehlermeldung --}}
        @if(session('error'))
            <div class="bg-red-100 text-red-800 p-4 rounded">
                {{ session('error') }}
            </div>
        @endif

        {{-- Validierungsfehler --}}
        @if($errors->any())
            <div class="bg-red-100 text-red-800 p-4 rounded">
                <strong>Fehler:</strong>
                <ul class="list-disc list-inside mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Upload-Formular --}}
        <form action="{{ route('import.mitglieder.preview') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div>
                <label for="csv_file" class="block font-medium text-sm text-gray-700">CSV-Datei auswählen:</label>
                <input
                    type="file"
                    name="csv_file"
                    id="csv_file"
                    accept=".csv"
                    required
                    class="mt-1 block w-full border rounded p-2 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                >
                <p class="text-sm text-gray-500 mt-1">Format: CSV mit Kopfzeile (z. B. Vorname, Nachname, E-Mail ...)</p>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-indigo-600 text-white font-semibold px-4 py-2 rounded hover:bg-indigo-700 transition">
                    📄 Vorschau anzeigen
                </button>
            </div>
        </form>

        @if(!empty($recentImportRuns) && $recentImportRuns->isNotEmpty())
            <div class="border-t border-gray-200 pt-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">Letzte Importe</h2>
                        <p class="mt-1 text-sm text-gray-500">Hier kannst du die letzten Importvorgaenge nachvollziehen und bei Bedarf rueckgaengig machen.</p>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    @foreach($recentImportRuns as $run)
                        <div class="rounded-xl border border-gray-200 p-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-semibold text-gray-900">
                                            {{ $run->imported_count }} Mitglied{{ $run->imported_count === 1 ? '' : 'er' }}
                                        </span>
                                        @if($run->status === 'undone')
                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                                Rueckgaengig gemacht
                                            </span>
                                        @else
                                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                                Importiert
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mt-1 text-sm text-gray-500">
                                        {{ $run->created_at->format('d.m.Y H:i') }}
                                        @if($run->creator)
                                            · von {{ $run->creator->name }}
                                        @endif
                                    </div>
                                    <div class="mt-1 text-xs text-gray-400">
                                        Quelle: {{ $run->filename ?: 'CSV-Import' }}
                                    </div>
                                </div>

                                @if($run->isUndoable())
                                    <form method="POST" action="{{ route('import.mitglieder.undo', $run) }}" onsubmit="return confirm('Diesen Import wirklich rueckgaengig machen?')">
                                        @csrf
                                        <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100">
                                            Import rueckgaengig machen
                                        </button>
                                    </form>
                                @else
                                    <div class="text-sm text-gray-400">
                                        @if($run->undone_at)
                                            Bereits rueckgaengig gemacht
                                        @else
                                            Nicht mehr automatisch rueckgaengig
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection
