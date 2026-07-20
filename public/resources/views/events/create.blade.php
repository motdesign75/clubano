@extends('layouts.app')

@section('title', 'Neue Veranstaltung')

@section('content')
<div class="py-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

    <div class="mb-8">
        <h1 class="text-3xl font-extrabold text-gray-900 flex items-center gap-2">
            ➕ Neue Veranstaltung erstellen
        </h1>
        <p class="mt-1 text-sm text-gray-500">Bitte gib Titel, Beschreibung, Ort und Zeit für das neue Event ein.</p>
    </div>

    <form action="{{ route('events.store') }}" method="POST" class="space-y-6 bg-white p-8 rounded-2xl shadow-xl border border-gray-200">
        @csrf

        {{-- Titel --}}
        <div>
            <label for="title" class="block text-sm font-medium text-gray-700">📌 Titel</label>
            <input type="text" name="title" id="title"
                   value="{{ old('title') }}"
                   required
                   class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        {{-- Beschreibung --}}
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700">📝 Beschreibung</label>
            <textarea name="description" id="description" rows="4"
                      class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">{{ old('description') }}</textarea>
        </div>

        {{-- Ort --}}
        <div>
            <label for="location" class="block text-sm font-medium text-gray-700">📍 Ort</label>
            <input type="text" name="location" id="location"
                   value="{{ old('location') }}"
                   class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        {{-- Zeiten --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="start" class="block text-sm font-medium text-gray-700">⏱ Beginn</label>
                <input type="datetime-local" name="start" id="start"
                       value="{{ old('start') }}"
                       required
                       class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label for="end" class="block text-sm font-medium text-gray-700">⏱ Ende</label>
                <input type="datetime-local" name="end" id="end"
                       value="{{ old('end') }}"
                       required
                       class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>

        {{-- Sichtbarkeit --}}
        <div>
            <label for="is_public" class="block text-sm font-medium text-gray-700">🔒 Sichtbarkeit</label>
            <select name="is_public" id="is_public"
                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="1" {{ old('is_public') == '1' ? 'selected' : '' }}>Öffentlich</option>
                <option value="0" {{ old('is_public') == '0' ? 'selected' : '' }}>Intern</option>
            </select>
        </div>

        {{-- Aktion --}}
        <div class="flex justify-between items-center pt-4 border-t">
            <a href="{{ route('events.index') }}" class="text-sm text-gray-500 hover:underline">← Zurück zur Übersicht</a>

            <button type="submit"
                    class="inline-flex items-center px-5 py-2 rounded-xl bg-green-600 text-white font-semibold shadow hover:bg-green-700 transition">
                💾 Veranstaltung speichern
            </button>
        </div>
    </form>
</div>
@endsection
