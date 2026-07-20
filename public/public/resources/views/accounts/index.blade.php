@extends('layouts.sidebar')

@section('title', 'Konten')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-2xl font-bold text-gray-800">📒 Kontenübersicht</h1>

            <a href="{{ route('accounts.create') }}"
               class="mt-3 sm:mt-0 inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
                ➕ Neues Konto
            </a>
        </div>

        <div class="overflow-auto bg-white rounded shadow ring-1 ring-gray-200" role="region" aria-labelledby="table-heading">
            <table class="min-w-full text-sm text-left" aria-describedby="Kontenliste">
                <thead class="bg-gray-100 text-xs font-semibold text-gray-600 uppercase">
                    <tr>
                        <th scope="col" class="px-4 py-3">Nr</th>
                        <th scope="col" class="px-4 py-3">Name</th>
                        <th scope="col" class="px-4 py-3">Typ</th>
                        <th scope="col" class="px-4 py-3 sr-only">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $account)
                        <tr class="{{ $loop->odd ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50 transition">
                            <td class="px-4 py-3 font-mono">{{ $account->number }}</td>
                            <td class="px-4 py-3">{{ $account->name }}</td>
                            <td class="px-4 py-3 capitalize">{{ $account->type }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('accounts.edit', $account) }}"
                                   class="text-sm text-blue-600 hover:underline focus:outline focus:ring-2 focus:ring-blue-400">
                                    ✏️ Bearbeiten
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-center text-gray-500">Keine Konten vorhanden.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
