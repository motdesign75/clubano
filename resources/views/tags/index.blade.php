@extends('layouts.app')

@section('title', 'Tags verwalten')

@section('content')
<div class="max-w-4xl mx-auto space-y-6 text-gray-800">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold">🏷️ Tags verwalten</h1>
        <a href="{{ route('tags.create') }}"
           class="bg-indigo-600 text-white px-4 py-2 rounded-xl shadow hover:bg-indigo-700 transition">
            ➕ Neuer Tag
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-2 rounded">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white shadow rounded-xl overflow-hidden">
        <table class="min-w-full table-auto">
            <thead class="bg-gray-100 text-gray-600 text-sm uppercase">
                <tr>
                    <th class="px-6 py-3 text-left">Name</th>
                    <th class="px-6 py-3 text-right">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tags as $tag)
                    <tr class="border-b">
                        <td class="px-6 py-4">{{ $tag->name }}</td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <a href="{{ route('tags.edit', $tag) }}" class="text-indigo-600 hover:underline text-sm">Bearbeiten</a>
                            <form action="{{ route('tags.destroy', $tag) }}" method="POST" class="inline-block" onsubmit="return confirm('Wirklich löschen?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline text-sm">Löschen</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="px-6 py-4 text-gray-500 italic">Noch keine Tags vorhanden.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
