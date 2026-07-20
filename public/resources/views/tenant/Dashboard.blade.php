<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Vereinsprofil') }}
        </h2>
    </x-slot>

    <div class="py-12 max-w-4xl mx-auto space-y-4">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

            @if ($tenant->logo)
                <div class="mb-4">
                    <img src="{{ asset('storage/' . $tenant->logo) }}" alt="Vereinslogo" class="h-24">
                </div>
            @endif

            <p><strong>Name:</strong> {{ $tenant->name }}</p>
            <p><strong>Slug:</strong> {{ $tenant->slug }}</p>
            <p><strong>E-Mail:</strong> {{ $tenant->email }}</p>
            <p><strong>Adresse:</strong> {{ $tenant->address }}</p>
            <p><strong>PLZ / Ort:</strong> {{ $tenant->zip }} {{ $tenant->city }}</p>
            <p><strong>Telefon:</strong> {{ $tenant->phone }}</p>
            <p><strong>Registernummer:</strong> {{ $tenant->register_number }}</p>

            <div class="mt-6">
                <a href="{{ route('tenant.edit') }}" class="text-blue-600 hover:underline">Vereinsdaten bearbeiten</a>
            </div>

        </div>
    </div>
</x-app-layout>
