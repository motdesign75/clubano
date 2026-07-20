@extends('layouts.sidebar')

@section('title', 'Neues Feld hinzufügen')

@section('content')
<div class="max-w-3xl mx-auto py-8 px-6 bg-white rounded-xl shadow space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">🛠 Neues Mitgliederfeld definieren</h1>

    {{-- Validierungsfehler --}}
    @if ($errors->any())
        <div class="bg-red-100 text-red-700 p-4 rounded border border-red-300">
            <ul class="list-disc pl-6 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('custom-fields.store') }}" method="POST" class="space-y-6">
        @csrf

        {{-- Technischer Name --}}
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Technischer Feldname <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" required
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                   value="{{ old('name') }}">
            <p class="text-sm text-gray-500 mt-1">Nur Kleinbuchstaben, Zahlen und Unterstriche (z. B. <code>mitgliedsnummer</code>).</p>
        </div>

        {{-- Sichtbarer Label --}}
        <div>
            <label for="label" class="block text-sm font-medium text-gray-700">Bezeichnung im Formular <span class="text-red-500">*</span></label>
            <input type="text" name="label" id="label" required
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                   value="{{ old('label') }}">
            <p class="text-sm text-gray-500 mt-1">z. B. Trikotgröße, Allergien, etc.</p>
        </div>

        {{-- Typ --}}
        <div>
            <label for="type" class="block text-sm font-medium text-gray-700">Feldtyp <span class="text-red-500">*</span></label>
            <select name="type" id="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                <option value="">Bitte wählen …</option>
                <option value="text" {{ old('type') == 'text' ? 'selected' : '' }}>Text</option>
                <option value="number" {{ old('type') == 'number' ? 'selected' : '' }}>Zahl</option>
                <option value="date" {{ old('type') == 'date' ? 'selected' : '' }}>Datum</option>
                <option value="email" {{ old('type') == 'email' ? 'selected' : '' }}>E-Mail</option>
                <option value="textarea" {{ old('type') == 'textarea' ? 'selected' : '' }}>Mehrzeilig (Textarea)</option>
                <option value="select" {{ old('type') == 'select' ? 'selected' : '' }}>Auswahlfeld (Select)</option>
            </select>
        </div>

        {{-- Optionen (nur bei Auswahlfeld) --}}
        <div id="select-options" class="{{ old('type') == 'select' ? '' : 'hidden' }}">
            <label for="options" class="block text-sm font-medium text-gray-700">Optionen (nur bei Auswahlfeld)</label>
            <input type="text" name="options" id="options"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                   placeholder="z. B. Klein|Mittel|Groß" value="{{ old('options') }}">
            <p class="text-sm text-gray-500 mt-1">Trenne mit <code>|</code> (Pipe-Zeichen).</p>
        </div>

        {{-- Sichtbar / Pflicht --}}
        <div class="flex gap-6 items-center pt-4">
            <label class="flex items-center">
                <input type="checkbox" name="required" class="rounded border-gray-300" {{ old('required') ? 'checked' : '' }}>
                <span class="ml-2 text-sm text-gray-700">Pflichtfeld</span>
            </label>

            <label class="flex items-center">
                <input type="checkbox" name="visible" class="rounded border-gray-300" {{ old('visible', true) ? 'checked' : '' }}>
                <span class="ml-2 text-sm text-gray-700">Im Formular anzeigen</span>
            </label>
        </div>

        {{-- Speichern --}}
        <div class="pt-6">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded shadow">
                💾 Speichern
            </button>
            <a href="{{ route('custom-fields.index') }}" class="ml-4 text-gray-600 hover:underline">Zurück zur Übersicht</a>
        </div>
    </form>
</div>

{{-- Toggle Options-Field --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const typeSelect = document.getElementById('type');
        const optionsField = document.getElementById('select-options');

        function toggleOptions() {
            if (typeSelect.value === 'select') {
                optionsField.classList.remove('hidden');
            } else {
                optionsField.classList.add('hidden');
            }
        }

        typeSelect.addEventListener('change', toggleOptions);
        toggleOptions();
    });
</script>
@endsection
