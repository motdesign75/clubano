@extends('layouts.sidebar')

@section('title', 'Mitglieder')

@section('content')
<div class="space-y-6">
    <!-- Titel & Button -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
        <h1 class="text-3xl font-extrabold text-gray-800">👥 Mitgliederübersicht</h1>
        <a href="{{ route('members.create') }}"
           class="bg-[#2954A3] text-white px-5 py-2 rounded-xl shadow hover:bg-[#1E3F7F] transition-all">
            ➕ Neues Mitglied
        </a>
    </div>

    <!-- Suchleiste -->
    <form method="GET" action="{{ route('members.index') }}" class="mt-4">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Mitglied suchen..."
               class="w-full md:w-1/3 px-4 py-2 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#2954A3] focus:outline-none">
    </form>

    <!-- Tabelle -->
    @if($members->isEmpty())
        <p class="text-gray-500 mt-6">Keine Mitglieder gefunden.</p>
    @else
        <div class="overflow-auto bg-white rounded-2xl shadow mt-6 ring-1 ring-gray-200">
            <table class="min-w-full text-[15px] md:text-sm text-left">
                <thead class="bg-[#F5F8FF] text-xs font-semibold text-gray-700 uppercase">
                    <tr>
                        <th class="px-4 py-3">Foto</th>
                        <th class="px-4 py-3">Anrede</th>
                        <th class="px-4 py-3">Vorname</th>
                        <th class="px-4 py-3">Nachname</th>
                        <th class="px-4 py-3 hidden md:table-cell">E-Mail</th>
                        <th class="px-4 py-3 hidden md:table-cell">Mobil</th>
                        <th class="px-4 py-3 text-right">Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($members as $member)
                        <tr class="{{ $loop->even ? 'bg-[#FAFBFF]' : 'bg-white' }} hover:bg-[#EFF4FF] transition duration-150">
                            <td class="px-4 py-3">
                                @if($member->photo)
                                    <img src="{{ asset('storage/' . $member->photo) }}"
                                         alt="Profilfoto von {{ $member->first_name }}"
                                         class="w-10 h-10 rounded-full object-cover shadow-sm">
                                @else
                                    <span class="text-gray-400 italic">Kein Bild</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $member->salutation }}</td>
                            <td class="px-4 py-3 font-semibold text-[#2954A3]">
                                <a href="{{ route('members.show', $member) }}"
                                   class="hover:underline">{{ $member->first_name }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $member->last_name }}</td>
                            <td class="px-4 py-3 hidden md:table-cell">{{ $member->email ?? '—' }}</td>
                            <td class="px-4 py-3 hidden md:table-cell">{{ $member->mobile ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end items-center gap-3">
                                    <a href="{{ route('members.show', $member) }}" title="Anzeigen"
                                       class="text-blue-600 hover:text-blue-800 transition">
                                        🔍
                                    </a>
                                    <a href="{{ route('members.edit', $member) }}" title="Bearbeiten"
                                       class="text-yellow-500 hover:text-yellow-600 transition">
                                        ✏️
                                    </a>
                                    <a href="{{ route('members.pdf', $member) }}" title="Datenauskunft erstellen"
                                       target="_blank" rel="noopener noreferrer"
                                       class="text-indigo-600 hover:text-indigo-800 transition">
                                        📄
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-6">
            {{ $members->appends(request()->query())->links() }}
        </div>
    @endif
</div>
@endsection
