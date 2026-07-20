@extends('layouts.sidebar')

@section('title', 'Mitglieder-Import')

@section('content')
    <div class="max-w-4xl mx-auto bg-white rounded shadow p-6 space-y-6">
        <h1 class="text-2xl font-bold text-gray-800">📥 Mitgliederimport</h1>

        @if(session('error'))
            <div class="bg-red-100 text-red-800 p-4 rounded">
                {{ session('error') }}
            </div>
        @endif

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

        <form action="{{ route('import.mitglieder.preview') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div>
                <label for="csv_file" class="block font-medium text-sm text-gray-700">CSV-Datei auswählen:</label>
                <input type="file" name="csv_file" id="csv_file" accept=".csv" required class="mt-1 block w-full border rounded p-2">
                <p class="text-sm text-gray-500 mt-1">Format: CSV mit Kopfzeile (z. B. Name, Vorname, E-Mail ...)</p>
            </div>

            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                Vorschau anzeigen
            </button>
        </form>
    </div>
@endsection
