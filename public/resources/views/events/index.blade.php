@extends('layouts.app')

@section('title', 'Veranstaltungen')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    
    {{-- Button Neues Event --}}
    <div class="mb-6">
        <a href="{{ route('events.create') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow transition">
            ➕ Neues Event
        </a>
    </div>

    {{-- Erfolgsmeldung --}}
    @if(session('success'))
        <div class="mb-4 text-green-700 bg-green-100 p-4 rounded border border-green-200">
            {{ session('success') }}
        </div>
    @endif

    {{-- Eventliste --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        @forelse ($events as $event)
            <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-1">{{ $event->title }}</h3>
                        <p class="text-sm text-gray-600 mb-1">
                            📍 {{ $event->location ?? 'Ort folgt' }}
                        </p>
                        <p class="text-sm text-gray-600">
                            📆 {{ $event->start->format('d.m.Y H:i') }} – {{ $event->end->format('H:i') }}
                        </p>
                    </div>
                    <div class="text-right space-y-1">
                        <a href="{{ route('events.edit', $event) }}"
                           class="text-sm text-blue-600 hover:underline">Bearbeiten</a>
                        <form action="{{ route('events.destroy', $event) }}" method="POST" onsubmit="return confirm('Wirklich löschen?')" class="inline-block">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-red-600 hover:underline">Löschen</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-3 text-gray-500 text-center p-10 bg-gray-50 rounded-lg">
                🎉 Keine Veranstaltungen gefunden.
            </div>
        @endforelse
    </div>
</div>
@endsection
