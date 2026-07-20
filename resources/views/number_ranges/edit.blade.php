<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight">Nummernkreis bearbeiten</h2>
    </x-slot>

    <div class="max-w-xl mx-auto mt-6 bg-white p-6 rounded shadow">
        <form method="POST" action="{{ route('number_ranges.update', $number_range) }}">
            @csrf
            @method('PUT')

            {{-- Typ (nur Anzeige, nicht änderbar) --}}
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Typ</label>
                <input type="text" value="{{ $number_range->type }}" disabled
                       class="w-full bg-gray-100 border-gray-300 rounded-md shadow-sm text-gray-600">
            </div>

            {{-- Präfix --}}
            <div class="mb-4">
                <label for="prefix" class="block text-sm font-medium mb-1">Präfix</label>
                <input type="text" name="prefix" id="prefix"
                       class="w-full border-gray-300 rounded-md shadow-sm"
                       value="{{ old('prefix', $number_range->prefix) }}">
                @error('prefix') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Suffix --}}
            <div class="mb-4">
                <label for="suffix" class="block text-sm font-medium mb-1">Suffix</label>
                <input type="text" name="suffix" id="suffix"
                       class="w-full border-gray-300 rounded-md shadow-sm"
                       value="{{ old('suffix', $number_range->suffix) }}">
                @error('suffix') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Reset jährlich --}}
            <div class="mb-6">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="reset_yearly"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm"
                           {{ old('reset_yearly', $number_range->reset_yearly) ? 'checked' : '' }}>
                    <span class="ml-2 text-sm text-gray-700">Zähler jährlich zurücksetzen</span>
                </label>
            </div>

            {{-- Buttons --}}
            <div class="flex justify-end gap-4">
                <a href="{{ route('number_ranges.index') }}"
                   class="px-4 py-2 rounded border text-gray-600 hover:bg-gray-100">
                    Zurück
                </a>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Änderungen speichern
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
