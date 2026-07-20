@extends('layouts.app')

@section('title', 'Rollenverwaltung')

@section('content')
<div class="max-w-6xl mx-auto space-y-8">
    <h1 class="text-2xl font-bold text-gray-800">🛡️ Rollenverwaltung</h1>

    <div class="bg-white rounded shadow p-6">
        <p class="text-gray-600 mb-4">
            Hier kannst du steuern, welche Rollen welche Zugriffsrechte im Verein besitzen. Künftig kannst du auch Benutzer einzelnen Rollen zuweisen.
        </p>

        <div class="overflow-x-auto">
            <table class="min-w-full table-auto text-sm text-left border border-gray-200">
                <thead class="bg-blue-50 text-blue-800 uppercase text-xs font-semibold">
                    <tr>
                        <th class="px-4 py-2 border-b">Modul</th>
                        <th class="px-4 py-2 border-b text-center">Lesen</th>
                        <th class="px-4 py-2 border-b text-center">Erstellen</th>
                        <th class="px-4 py-2 border-b text-center">Bearbeiten</th>
                        <th class="px-4 py-2 border-b text-center">Löschen</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700">
                    @foreach ($permissions as $module => $actions)
                        <tr class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }}">
                            <td class="px-4 py-2 font-medium">{{ $module }}</td>
                            @foreach ($actions as $action => $enabled)
                                <td class="px-4 py-2 text-center">
                                    <input type="checkbox"
                                           name="permissions[{{ $module }}][{{ $action }}]"
                                           {{ $enabled ? 'checked' : '' }}
                                           class="h-4 w-4 text-blue-600 rounded focus:ring-blue-500 focus:outline-none">
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6 text-right">
            <button class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition">
                💾 Änderungen speichern
            </button>
        </div>
    </div>
</div>
@endsection
