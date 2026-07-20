@extends('layouts.sidebar')

@section('title', 'Vereinsprofil')

@section('content')
    <div class="max-w-4xl mx-auto space-y-6">
        <h1 class="text-2xl font-bold text-gray-800">🏢 Vereinsprofil</h1>

        <div class="bg-white p-6 rounded shadow space-y-4">
            <p><strong>Name:</strong> {{ $tenant->name }}</p>
            <p><strong>E-Mail:</strong> {{ $tenant->email }}</p>
            <p><strong>Adresse:</strong> {{ $tenant->address }}, {{ $tenant->zip }} {{ $tenant->city }}</p>
            <p><strong>Telefon:</strong> {{ $tenant->phone }}</p>
            <p><strong>Registernummer:</strong> {{ $tenant->register_number }}</p>
            @if ($tenant->logo)
                <div>
                    <strong>Logo:</strong><br>
                    <img src="{{ Storage::url($tenant->logo) }}" alt="Vereinslogo" class="h-24 rounded shadow mt-2">
                </div>
            @endif
        </div>

        <div class="text-right">
            <a href="{{ route('tenant.edit') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                ✏️ Bearbeiten
            </a>
        </div>
    </div>
@endsection
