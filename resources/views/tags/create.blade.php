@extends('layouts.app')

@section('title', 'Neuen Tag erstellen')

@section('content')
<div class="max-w-xl mx-auto space-y-6 text-gray-800">
    <h1 class="text-2xl font-bold">➕ Neuer Tag</h1>

    @if($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-2 rounded">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('tags.store') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="block font-semibold">Name</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}"
                   class="w-full mt-1 rounded border-gray-300 shadow-sm" required>
        </div>

        <div>
            <label for="color" class="block font-semibold">Farbe</label>
            <input type="color" name="color" id="color" value="{{ old('color', '#4f46e5') }}"
                   class="w-16 h-10 mt-1 rounded border-gray-300 shadow-sm">
        </div>

        <div class="text-right">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-2 rounded-xl shadow">
                💾 Tag speichern
            </button>
        </div>
    </form>
</div>
@endsection
