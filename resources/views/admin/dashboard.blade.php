@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<div class="max-w-6xl mx-auto py-10 space-y-8">

    {{-- HEADER --}}
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Admin Dashboard</h1>
        <p class="text-gray-600">Übersicht über alle Registrierungen</p>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ session('error') }}
        </div>
    @endif

    {{-- KPI CARDS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <div class="bg-white p-6 rounded-2xl shadow">
            <p class="text-sm text-gray-500">Vereine gesamt</p>
            <p class="text-3xl font-bold mt-2">{{ $totalTenants }}</p>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow">
            <p class="text-sm text-gray-500">Benutzer gesamt</p>
            <p class="text-3xl font-bold mt-2">{{ $totalUsers }}</p>
        </div>

    </div>

    {{-- NEUE VEREINE --}}
    <div class="bg-white rounded-2xl shadow">
        <div class="p-6 border-b">
            <h2 class="text-xl font-semibold">Neueste Vereine</h2>
        </div>

        <table class="w-full text-sm">
    <thead class="bg-gray-50 text-left">
        <tr>
            <th class="p-3">Name</th>
            <th class="p-3">E-Mail</th>
            <th class="p-3">Lizenz</th>
            <th class="p-3">Erstellt am</th>
            <th class="p-3">Aktion</th>
        </tr>
    </thead>
    <tbody>
        @forelse($latestTenants as $tenant)
            <tr class="border-t">
                <td class="p-3 font-medium">{{ $tenant->name ?? '-' }}</td>

                <td class="p-3">
                    {{ $tenant->email ?? '-' }}
                </td>

                <td class="p-3">
                    <form method="POST" action="{{ route('admin.tenants.license', $tenant) }}" class="space-y-2 min-w-[260px]">
                        @csrf
                        @method('PATCH')

                        <select name="license_mode" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="standard" @selected(($tenant->license_mode ?? 'standard') === 'standard')>Standard</option>
                            <option value="beta" @selected(($tenant->license_mode ?? 'standard') === 'beta')>Pilotlizenz</option>
                            <option value="gifted" @selected(($tenant->license_mode ?? 'standard') === 'gifted')>Freilizenz</option>
                        </select>

                        <input
                            type="date"
                            name="license_expires_at"
                            value="{{ optional($tenant->license_expires_at)->format('Y-m-d') }}"
                            class="w-full rounded-lg border-gray-300 text-sm"
                        >

                        <div class="text-xs text-gray-500">
                            Aktuell: {{ $tenant->license_mode_label }}
                            @if($tenant->hasComplimentaryAccess() && $tenant->license_expires_at)
                                bis {{ $tenant->license_expires_at->format('d.m.Y') }}
                            @elseif($tenant->hasComplimentaryAccess())
                                ohne Enddatum
                            @endif
                        </div>

                        <button type="submit"
                                class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">
                            Lizenz speichern
                        </button>
                    </form>
                </td>

                <td class="p-3">
                    {{ optional($tenant->created_at)->format('d.m.Y H:i') }}
                </td>

                <td class="p-3">
                    <div class="flex flex-col gap-2">
                        @if($tenant->email)
                            <a href="mailto:{{ $tenant->email }}?subject=Willkommen bei Clubano&body=Hallo {{ urlencode($tenant->name) }},%0D%0A%0D%0Avielen Dank für Ihre Registrierung bei Clubano.%0D%0A%0D%0AWenn Sie Fragen haben oder Unterstützung beim Einrichten benötigen, stehe ich Ihnen gerne persönlich zur Verfügung.%0D%0A%0D%0AViele Grüße%0D%0AMaik-Oliver Towet"
                               class="inline-block bg-blue-600 text-white text-xs px-3 py-2 rounded-lg hover:bg-blue-700">
                                Kontakt aufnehmen
                            </a>
                        @else
                            <span class="text-gray-400 text-xs">keine E-Mail</span>
                        @endif

                        <form method="POST"
                              action="{{ route('admin.tenants.destroy', $tenant) }}"
                              onsubmit="const confirmation = prompt('Zum Loeschen bitte DELETE eingeben.'); if (confirmation !== 'DELETE') { return false; } this.querySelector('input[name=confirmation]').value = confirmation; return confirm('Verein wirklich endgueltig loeschen?');">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="confirmation" value="">
                            <button type="submit"
                                    class="inline-flex items-center rounded-lg bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-700">
                                Verein loeschen
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="p-4 text-gray-500 text-center">
                    Noch keine Registrierungen vorhanden
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
    </div>

    {{-- NEUE USER --}}
    <div class="bg-white rounded-2xl shadow">
        <div class="p-6 border-b">
            <h2 class="text-xl font-semibold">Neueste Benutzer</h2>
        </div>

        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left">
                <tr>
                    <th class="p-3">Name</th>
                    <th class="p-3">E-Mail</th>
                    <th class="p-3">Erstellt am</th>
                </tr>
            </thead>
            <tbody>
                @forelse($latestUsers as $user)
                    <tr class="border-t">
                        <td class="p-3 font-medium">{{ $user->name ?? '-' }}</td>
                        <td class="p-3">{{ $user->email ?? '-' }}</td>
                        <td class="p-3">
                            {{ optional($user->created_at)->format('d.m.Y H:i') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="p-4 text-gray-500 text-center">
                            Noch keine Benutzer vorhanden
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
