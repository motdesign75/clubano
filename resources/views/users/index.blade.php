@extends('layouts.app')

@section('title', 'Benutzerverwaltung')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-bold text-gray-800">👥 Benutzer</h1>

        <a href="{{ route('users.create') }}"
           class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-blue-700">
            ➕ Neuer Benutzer
        </a>
    </div>

    @if(session('success'))
        <div class="rounded bg-green-100 px-4 py-2 text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded bg-red-100 px-4 py-2 text-red-800">
            {{ session('error') }}
        </div>
    @endif

    @php
        $currentUser = auth()->user();
    @endphp

    <div class="overflow-hidden rounded-md bg-white shadow">
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Name</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">E-Mail</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Rolle</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Erstellt am</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Letzter Login</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-600">Aktionen</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 bg-white">
                @forelse($users as $user)
                    @php
                        $isCurrent = $currentUser && $currentUser->id == $user->id;
                    @endphp

                    <tr class="{{ $isCurrent ? 'border-l-4 border-green-500 bg-green-50' : '' }}">
                        <td class="px-4 py-2 font-medium text-gray-800">
                            {{ $user->name }}
                            @if($isCurrent)
                                <span class="ml-2 text-xs font-semibold text-green-600">(Du)</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $user->email }}</td>
                        <td class="px-4 py-2">{{ $user->roleLabel() }}</td>
                        <td class="px-4 py-2">{{ $user->created_at?->format('d.m.Y') }}</td>
                        <td class="px-4 py-2">
                            @if($user->last_login_at)
                                <div>{{ $user->last_login_at->format('d.m.Y H:i') }}</div>
                                @if($user->last_login_ip)
                                    <div class="text-xs text-gray-500">{{ $user->last_login_ip }}</div>
                                @endif
                            @else
                                <span class="text-gray-400">Noch nie</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('users.edit', $user) }}"
                               class="mr-3 text-sm text-blue-600 hover:underline">
                                Bearbeiten
                            </a>

                            @if(!$isCurrent)
                                <form action="{{ route('users.destroy', $user) }}"
                                      method="POST"
                                      onsubmit="return confirm('Benutzer wirklich löschen?');"
                                      class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-sm text-red-600 hover:underline">
                                        🗑️ Löschen
                                    </button>
                                </form>
                            @else
                                <span class="inline-flex items-center text-sm font-medium text-green-600">
                                    🟢 Aktuell eingeloggt
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-4 text-center text-gray-500">
                            Keine Benutzer vorhanden.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-gray-100 md:hidden">
            @forelse($users as $user)
                @php
                    $isCurrent = $currentUser && $currentUser->id == $user->id;
                @endphp

                <div class="p-4 {{ $isCurrent ? 'border-l-4 border-green-500 bg-green-50' : '' }}">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="font-medium text-gray-900">
                                {{ $user->name }}
                                @if($isCurrent)
                                    <span class="ml-2 text-xs font-semibold text-green-600">(Du)</span>
                                @endif
                            </div>
                            <div class="mt-1 break-all text-sm text-gray-600">{{ $user->email }}</div>
                            <div class="mt-2 text-sm text-gray-600">Rolle: {{ $user->roleLabel() }}</div>
                            <div class="mt-1 text-sm text-gray-600">Erstellt: {{ $user->created_at?->format('d.m.Y') }}</div>
                            <div class="mt-1 text-sm text-gray-600">
                                Letzter Login:
                                @if($user->last_login_at)
                                    {{ $user->last_login_at->format('d.m.Y H:i') }}
                                @else
                                    <span class="text-gray-400">Noch nie</span>
                                @endif
                            </div>
                            @if($user->last_login_ip)
                                <div class="mt-1 text-xs text-gray-500">IP: {{ $user->last_login_ip }}</div>
                            @endif
                        </div>

                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <a href="{{ route('users.edit', $user) }}"
                               class="text-sm text-blue-600 hover:underline">
                                Bearbeiten
                            </a>

                            @if(!$isCurrent)
                                <form action="{{ route('users.destroy', $user) }}"
                                      method="POST"
                                      onsubmit="return confirm('Benutzer wirklich löschen?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-sm text-red-600 hover:underline">
                                        🗑️ Löschen
                                    </button>
                                </form>
                            @else
                                <span class="text-sm font-medium text-green-600">🟢 Aktuell eingeloggt</span>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-4 text-center text-gray-500">
                    Keine Benutzer vorhanden.
                </div>
            @endforelse
        </div>
    </div>

</div>
@endsection
