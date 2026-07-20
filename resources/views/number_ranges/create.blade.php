<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight">Neuen Nummernkreis erstellen</h2>
    </x-slot>

    <div class="max-w-xl mx-auto mt-6 bg-white p-6 rounded shadow">

        {{-- Fehlermeldungen anzeigen --}}
        @if ($errors->any())
            <div class="mb-4 bg-red-100 border border-red-300 text-red-800 p-3 rounded">
                <strong>Bitte überprüfe deine Eingaben:</strong>
                <ul class="mt-2 list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('number_ranges.store') }}">
            @csrf

            {{-- Typ (z. B. beitrag, spende, rechnung) --}}
            <div class="mb-4">
                <label for="type" class="block text-sm font-medium mb-1">Typ <span class="text-red-600">*</span></label>
                <input type="text" name="type" id="type"
                       class="w-full border-gray-300 rounded-md shadow-sm"
                       placeholder="z. B. beitrag" value="{{ old('type') }}" required>
                @error('type') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Präfix --}}
            <div class="mb-4">
                <label for="prefix" class="block text-sm font-medium mb-1">Präfix</label>
                <input type="text" name="prefix" id="prefix"
                       class="w-full border-gray-300 rounded-md shadow-sm"
                       placeholder="z. B. BEITRAG-" value="{{ old('prefix') }}">
                @error('prefix') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Suffix --}}
            <div class="mb-4">
                <label for="suffix" class="block text-sm font-medium mb-1">Suffix</label>
                <input type="text" name="suffix" id="suffix"
                       class="w-full border-gray-300 rounded-md shadow-sm"
                       placeholder="z. B. -2025" value="{{ old('suffix') }}">
                @error('suffix') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Startnummer --}}
            <div class="mb-4">
                <label for="start_number" class="block text-sm font-medium mb-1">Startnummer <span class="text-red-600">*</span></label>
                <input type="number" name="start_number" id="start_number"
                       class="w-full border-gray-300 rounded-md shadow-sm"
                       value="{{ old('start_number', 1) }}" min="1" required>
                @error('start_number') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Reset jährlich --}}
            <div class="mb-6">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="reset_yearly" class="rounded border-gray-300 text-indigo-600 shadow-sm"
                           {{ old('reset_yearly') ? 'checked' : '' }}>
                    <span class="ml-2 text-sm text-gray-700">Zähler jährlich zurücksetzen</span>
                </label>
            </div>

            {{-- Buttons --}}
            <div class="flex justify-end gap-4">
                <a href="{{ route('number_ranges.index') }}"
                   class="px-4 py-2 rounded border text-gray-600 hover:bg-gray-100">
                    Abbrechen
                </a>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Speichern
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
