@extends('layouts.app')

@section('title', 'Neues Konto anlegen')

@section('content')
    <div class="max-w-xl space-y-6">
        <h1 class="text-2xl font-bold text-gray-800">➕ Neues Konto</h1>

        <form method="POST" action="{{ route('accounts.store') }}" class="space-y-6" aria-label="Konto anlegen">
            @csrf

            <div>
                <label for="number" class="block text-sm font-medium text-gray-700">Kontonummer</label>
                <input type="text" id="number" name="number"
                       value="{{ old('number') }}"
                       class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                       placeholder="z. B. 1000 oder leer lassen">

                @error('number')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Kontoname <span class="text-red-600">*</span></label>
                <input type="text" id="name" name="name"
                       value="{{ old('name') }}"
                       required
                       class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                       placeholder="z. B. Kasse, Bank, Porto…">

                @error('name')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="type" class="block text-sm font-medium text-gray-700">Kontotyp <span class="text-red-600">*</span></label>
                <select id="type" name="type"
                        required
                        class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                    <option value="">Bitte wählen</option>
                    <option value="kasse" {{ old('type') === 'kasse' ? 'selected' : '' }}>Kasse</option>
                    <option value="bank" {{ old('type') === 'bank' ? 'selected' : '' }}>Bank</option>
                    <option value="einnahme" {{ old('type') === 'einnahme' ? 'selected' : '' }}>Einnahme</option>
                    <option value="ausgabe" {{ old('type') === 'ausgabe' ? 'selected' : '' }}>Ausgabe</option>
                </select>

                @error('type')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" id="online" name="online" value="1"
                       {{ old('online') ? 'checked' : '' }}
                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">

                <label for="online" class="text-sm text-gray-700">Online-Konto (z. B. für Bankimport)</label>
            </div>

            <div class="pt-4">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
                    💾 Speichern
                </button>

                <a href="{{ route('accounts.index') }}"
                   class="ml-3 text-sm text-blue-600 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-400">
                    ⬅️ Zurück zur Übersicht
                </a>
            </div>
        </form>
    </div>
@endsection
