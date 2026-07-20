@extends('layouts.sidebar')

@section('title', 'Konto bearbeiten')

@section('content')
    <div class="max-w-2xl space-y-6">
        <h1 class="text-2xl font-bold text-gray-800">✏️ Konto bearbeiten</h1>

        <form method="POST" action="{{ route('accounts.update', $account) }}" class="bg-white rounded shadow p-6 space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label for="number" class="block text-sm font-medium text-gray-700">Kontonummer</label>
                <input type="text" name="number" id="number" value="{{ old('number', $account->number) }}"
                       class="mt-1 w-full rounded border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
                @error('number') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Kontoname *</label>
                <input type="text" name="name" id="name" required value="{{ old('name', $account->name) }}"
                       class="mt-1 w-full rounded border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
                @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="type" class="block text-sm font-medium text-gray-700">Kontotyp *</label>
                <select name="type" id="type" required
                        class="mt-1 w-full rounded border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">– Bitte wählen –</option>
                    <option value="bank" {{ old('type', $account->type) === 'bank' ? 'selected' : '' }}>Bank</option>
                    <option value="kasse" {{ old('type', $account->type) === 'kasse' ? 'selected' : '' }}>Kasse</option>
                    <option value="einnahme" {{ old('type', $account->type) === 'einnahme' ? 'selected' : '' }}>Einnahme</option>
                    <option value="ausgabe" {{ old('type', $account->type) === 'ausgabe' ? 'selected' : '' }}>Ausgabe</option>
                </select>
                @error('type') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center">
                <input type="checkbox" name="online" id="online" value="1"
                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                       {{ old('online', $account->online) ? 'checked' : '' }}>
                <label for="online" class="ml-2 text-sm text-gray-700">Online-Zugang vorhanden</label>
            </div>

            <div class="flex justify-between mt-6">
                <a href="{{ route('accounts.index') }}"
                   class="inline-flex items-center px-4 py-2 text-sm text-blue-700 bg-blue-100 rounded hover:bg-blue-200">
                    ← Zurück zur Übersicht
                </a>

                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700">
                    💾 Speichern
                </button>
            </div>
        </form>
    </div>
@endsection
