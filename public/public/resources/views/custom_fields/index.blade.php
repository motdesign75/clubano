@extends('layouts.sidebar')

@section('title', 'Mitgliederfelder')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <div class="flex justify-between items-center mt-4">
            <h1 class="text-2xl font-bold text-gray-800">🧩 Eigene Mitgliederfelder</h1>
            <a href="{{ route('custom-fields.create') }}"
               class="inline-flex items-center bg-indigo-600 text-white px-4 py-2 rounded-lg shadow hover:bg-indigo-700 transition">
                ➕ Neues Feld
            </a>
        </div>

        @if(session('success'))
            <div class="bg-green-100 text-green-800 px-4 py-3 rounded border border-green-200">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white shadow rounded-xl overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-left text-sm font-semibold text-gray-600">
                    <tr>
                        <th class="px-6 py-3">Feldname</th>
                        <th class="px-6 py-3">Label</th>
                        <th class="px-6 py-3">Typ</th>
                        <th class="px-6 py-3">Pflicht</th>
                        <th class="px-6 py-3">Sichtbar</th>
                        <th class="px-6 py-3 text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm text-gray-800">
                    @forelse($fields as $field)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">{{ $field->name }}</td>
                            <td class="px-6 py-4">{{ $field->label }}</td>
                            <td class="px-6 py-4 capitalize">{{ $field->type }}</td>
                            <td class="px-6 py-4">{{ $field->required ? '✅ Ja' : '—' }}</td>
                            <td class="px-6 py-4">{{ $field->visible ? '👁 Sichtbar' : '🙈 Versteckt' }}</td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <a href="{{ route('custom-fields.edit', $field) }}"
                                   class="text-indigo-600 hover:underline text-sm">✏️ Bearbeiten</a>
                                <form action="{{ route('custom-fields.destroy', $field) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Feld wirklich löschen?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline text-sm">🗑️ Löschen</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-6 text-center text-gray-500">
                                ⚠️ Es wurden noch keine benutzerdefinierten Mitgliederfelder angelegt.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
