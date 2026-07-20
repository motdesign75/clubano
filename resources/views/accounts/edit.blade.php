@extends('layouts.app')

@section('title', 'Konto bearbeiten')

@section('content')
    <div class="max-w-xl space-y-6">
        <h1 class="text-2xl font-bold text-gray-800">✏️ Konto bearbeiten</h1>

        <form method="POST" action="{{ route('accounts.update', $account) }}" class="space-y-6" aria-label="Konto bearbeiten">
            @csrf
            @method('PUT')

            {{-- Kontonummer --}}
            <div>
                <label for="number" class="block text-sm font-medium text-gray-700">Kontonummer</label>
                <input type="text" id="number" name="number" value="{{ old('number', $account->number) }}"
                       class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                       placeholder="z. B. 1000 oder leer lassen">
                @error('number') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Name --}}
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Kontoname</label>
                <input type="text" id="name" name="name" value="{{ old('name', $account->name) }}"
                       class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm" required>
                @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Typ --}}
            <div>
                <label for="type" class="block text-sm font-medium text-gray-700">Kontotyp</label>
                <select id="type" name="type"
                        class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                        required>
                    <option value="">Bitte wählen</option>
                    <option value="bank" {{ old('type', $account->type) === 'bank' ? 'selected' : '' }}>Bankkonto</option>
                    <option value="kasse" {{ old('type', $account->type) === 'kasse' ? 'selected' : '' }}>Kasse</option>
                    <option value="einnahme" {{ old('type', $account->type) === 'einnahme' ? 'selected' : '' }}>Einnahme</option>
                    <option value="ausgabe" {{ old('type', $account->type) === 'ausgabe' ? 'selected' : '' }}>Ausgabe</option>
                </select>
                @error('type') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- IBAN --}}
            <div>
                <label for="iban" class="block text-sm font-medium text-gray-700">IBAN</label>
                <input type="text" id="iban" name="iban" value="{{ old('iban', $account->iban) }}"
                       class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                @error('iban') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- BIC --}}
            <div>
                <label for="bic" class="block text-sm font-medium text-gray-700">BIC</label>
                <input type="text" id="bic" name="bic" value="{{ old('bic', $account->bic) }}"
                       class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                @error('bic') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Beschreibung --}}
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700">Beschreibung</label>
                <textarea id="description" name="description"
                          class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                          rows="2">{{ old('description', $account->description) }}</textarea>
                @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Anfangsbestand --}}
            <div>
                <label for="balance_start" class="block text-sm font-medium text-gray-700">Anfangsbestand (€)</label>
                <input type="number" step="0.01" id="balance_start" name="balance_start"
                       value="{{ old('balance_start', $account->balance_start) }}"
                       class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                @error('balance_start') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Stichtag Anfangsbestand --}}
            <div>
                <label for="balance_date" class="block text-sm font-medium text-gray-700">Stichtag Anfangsbestand</label>
                <input type="date" id="balance_date" name="balance_date"
                       value="{{ old('balance_date', optional($account->balance_date)->format('Y-m-d')) }}"
                       class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                @error('balance_date') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Optionen --}}
            <div class="flex items-center gap-4">
                <label class="inline-flex items-center">
                    <input type="hidden" name="online" value="0">
                    <input type="checkbox" name="online" class="rounded text-blue-600"
                           value="1"
                           {{ old('online', $account->online) ? 'checked' : '' }}>
                    <span class="ml-2 text-sm text-gray-700">Online abrufbar</span>
                </label>

                <label class="inline-flex items-center">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" class="rounded text-blue-600"
                           value="1"
                           {{ old('active', $account->active) ? 'checked' : '' }}>
                    <span class="ml-2 text-sm text-gray-700">Aktiv</span>
                </label>
            </div>

            {{-- Speichern --}}
            <div class="pt-4">
                <button type="submit"
                        class="w-full inline-flex justify-center items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
                    💾 Änderungen speichern
                </button>
            </div>
        </form>
    </div>
@endsection
