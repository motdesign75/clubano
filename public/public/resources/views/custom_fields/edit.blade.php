@extends('layouts.sidebar')

@section('title', 'Mitgliederfeld bearbeiten')

@section('content')
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 space-y-6">

        <h1 class="text-2xl font-bold text-gray-800">✏️ Mitgliederfeld bearbeiten</h1>

        @if ($errors->any())
            <div class="bg-red-100 text-red-700 p-4 rounded border border-red-300">
                <ul class="list-disc pl-6 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('custom-fields.update', $customMemberField) }}" method="POST" class="bg-white p-6 rounded-xl shadow space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">

                {{-- Technischer Feldname --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">Feldname (technisch)</label>
                    <input type="text" name="name" value="{{ old('name', $customMemberField->name) }}" class="mt-1 block w-full rounded border-gray-300 shadow-sm" required>
                    <p class="text-xs text-gray-500 mt-1">Nur Buchstaben, Zahlen, Unterstriche. Keine Leerzeichen.</p>
                </div>

                {{-- Sichtbarer Label --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">Label (sichtbar im Formular)</label>
                    <input type="text" name="label" value="{{ old('label', $customMemberField->label) }}" class="mt-1 block w-full rounded border-gray-300 shadow-sm" required>
                </div>

                {{-- Typ --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">Feldtyp</label>
                    <select name="type" class="mt-1 block w-full rounded border-gray-300 shadow-sm" required>
                        <option value="text" {{ old('type', $customMemberField->type) == 'text' ? 'selected' : '' }}>Textfeld</option>
                        <option value="number" {{ old('type', $customMemberField->type) == 'number' ? 'selected' : '' }}>Zahl</option>
                        <option value="date" {{ old('type', $customMemberField->type) == 'date' ? 'selected' : '' }}>Datum</option>
                        <option value="email" {{ old('type', $customMemberField->type) == 'email' ? 'selected' : '' }}>E-Mail</option>
                        <option value="textarea" {{ old('type', $customMemberField->type) == 'textarea' ? 'selected' : '' }}>Textarea</option>
                        <option value="select" {{ old('type', $customMemberField->type) == 'select' ? 'selected' : '' }}>Dropdown</option>
                    </select>
                </div>

                {{-- Optionen (für Select-Feld) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">Optionen (bei Dropdown)</label>
                    <input type="text" name="options" value="{{ old('options', $customMemberField->options) }}" class="mt-1 block w-full rounded border-gray-300 shadow-sm">
                    <p class="text-xs text-gray-500 mt-1">Trenne Optionen mit „|“, z. B.: Klein|Mittel|Groß</p>
                </div>

                {{-- Pflichtfeld --}}
                <div class="flex items-center space-x-2 mt-4">
                    <input type="checkbox" name="required" id="required" {{ old('required', $customMemberField->required) ? 'checked' : '' }} class="rounded border-gray-300">
                    <label for="required" class="text-sm text-gray-700">Pflichtfeld</label>
                </div>

                {{-- Sichtbarkeit --}}
                <div class="flex items-center space-x-2 mt-4">
                    <input type="checkbox" name="visible" id="visible" {{ old('visible', $customMemberField->visible) ? 'checked' : '' }} class="rounded border-gray-300">
                    <label for="visible" class="text-sm text-gray-700">Im Formular anzeigen</label>
                </div>
            </div>

            <div class="flex justify-between items-center mt-6">
                <a href="{{ route('custom-fields.index') }}" class="text-gray-600 hover:text-gray-800 text-sm underline">← Zurück</a>
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded hover:bg-indigo-700 shadow">
                    💾 Änderungen speichern
                </button>
            </div>
        </form>
    </div>
@endsection
