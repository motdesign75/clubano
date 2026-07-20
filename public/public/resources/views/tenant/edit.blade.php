@extends('layouts.sidebar')

@section('title', 'Vereinsdaten bearbeiten')

@section('content')
    <div class="max-w-4xl mx-auto space-y-6">
        <h1 class="text-2xl font-bold text-gray-800">✏️ Vereinsdaten bearbeiten</h1>

        <form method="POST" action="{{ route('tenant.update') }}" enctype="multipart/form-data" class="space-y-6 bg-white p-6 rounded shadow">
            @csrf
            @method('PATCH')

            {{-- Logo mit Vorschau --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Logo</label>
                @if($tenant->logo)
                    <img src="{{ Storage::url($tenant->logo) }}" alt="Vereinslogo" class="h-16 mb-2 rounded shadow">
                @endif
                <input type="file" name="logo" class="mt-1 block w-full border-gray-300 rounded shadow-sm">
            </div>

            {{-- Vereinsname --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                <input type="text" name="name" value="{{ old('name', $tenant->name) }}" required class="mt-1 block w-full border-gray-300 rounded shadow-sm">
            </div>

            {{-- Slug (optional) --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
                <input type="text" name="slug" value="{{ old('slug', $tenant->slug) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm">
                <p class="text-xs text-gray-500 mt-1">Der Slug identifiziert den Verein eindeutig (z. B. für URLs).</p>
            </div>

            {{-- Grid für Adresse & Kontakt --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Adresse</label>
                    <input type="text" name="address" value="{{ old('address', $tenant->address) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">PLZ</label>
                    <input type="text" name="zip" value="{{ old('zip', $tenant->zip) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ort</label>
                    <input type="text" name="city" value="{{ old('city', $tenant->city) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefon</label>
                    <input type="text" name="phone" value="{{ old('phone', $tenant->phone) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail *</label>
                    <input type="email" name="email" required value="{{ old('email', $tenant->email) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Registernummer</label>
                    <input type="text" name="register_number" value="{{ old('register_number', $tenant->register_number) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm">
                </div>
            </div>

            {{-- Submit --}}
            <div class="pt-4">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded shadow">
                    💾 Speichern
                </button>
            </div>
        </form>
    </div>
@endsection
