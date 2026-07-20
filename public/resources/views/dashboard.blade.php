@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

@php
    \Carbon\Carbon::setLocale('de');
@endphp

<div class="space-y-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <h1 class="text-3xl font-extrabold text-gray-900 mt-4">👋 Willkommen, {{ Auth::user()->name }}!</h1>

    {{-- Übersicht --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6 mt-6">
        
        {{-- Mitglieder --}}
        <a href="{{ route('members.index') }}" class="block bg-gradient-to-br from-green-100 to-green-200 text-green-900 rounded-2xl shadow-xl p-6 hover:shadow-2xl transition-all duration-300 ease-in-out hover:scale-[1.01]">
            <div class="flex items-center space-x-4">
                <div class="text-4xl">👥</div>
                <div>
                    <p class="text-sm font-semibold">Mitglieder</p>
                    <p class="text-2xl font-bold">{{ $membersCount }}</p>
                </div>
            </div>
        </a>

        {{-- Lizenz --}}
        <div class="bg-gradient-to-br from-yellow-100 to-yellow-200 text-yellow-900 rounded-2xl shadow-xl p-6 hover:shadow-2xl transition-all duration-300 ease-in-out">
            <div class="flex items-center space-x-4">
                <div class="text-4xl">🔐</div>
                <div>
                    <p class="text-sm font-semibold">Lizenz</p>
                    <p class="text-2xl font-bold">{{ $licenseType }}</p>
                </div>
            </div>
        </div>

        {{-- Events --}}
        <a href="{{ route('events.index') }}" class="block bg-gradient-to-br from-blue-100 to-blue-200 text-blue-900 rounded-2xl shadow-xl p-6 hover:shadow-2xl transition-all duration-300 ease-in-out hover:scale-[1.01]">
            <div class="flex items-center space-x-4">
                <div class="text-4xl">📅</div>
                <div>
                    <p class="text-sm font-semibold">Kommende Events</p>
                    <p class="text-2xl font-bold">{{ $events->count() }}</p>
                </div>
            </div>
        </a>
    </div>

    {{-- Events-Liste --}}
    <div class="bg-white rounded-xl shadow-md p-6 mt-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">📆 Kommende Veranstaltungen</h2>

        @if ($events->count())
            <ul class="divide-y divide-gray-200">
                @foreach($events as $event)
                    <li class="py-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $event->title }}</p>
                                <p class="text-sm text-gray-600">
                                    {{ \Carbon\Carbon::parse($event->start)->format('d.m.Y H:i') }}
                                    – {{ \Carbon\Carbon::parse($event->end)->format('H:i') }}
                                    @if ($event->location)
                                        · {{ $event->location }}
                                    @endif
                                </p>
                            </div>
                            <span class="text-xs font-medium px-2 py-1 rounded-full 
                                {{ $event->is_public ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                {{ $event->is_public ? 'Öffentlich' : 'Intern' }}
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="text-gray-500 text-center py-6 text-sm">
                🎉 Keine kommenden Events.
            </div>
        @endif
    </div>

    {{-- Neue Info-Tabs --}}
    <div class="bg-white rounded-xl shadow-md p-6" x-data="{ tab: 'entries' }">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">
            📊 Vereinsaktivitäten im {{ \Carbon\Carbon::now()->translatedFormat('F') }}
        </h2>

        <div class="flex space-x-4 border-b mb-6">
            <button @click="tab = 'entries'" class="pb-2" :class="tab === 'entries' ? 'border-b-2 border-blue-600 font-semibold' : 'text-gray-500'">🎉 Eintritte</button>
            <button @click="tab = 'exits'" class="pb-2" :class="tab === 'exits' ? 'border-b-2 border-blue-600 font-semibold' : 'text-gray-500'">😢 Austritte</button>
            <button @click="tab = 'birthdays'" class="pb-2" :class="tab === 'birthdays' ? 'border-b-2 border-blue-600 font-semibold' : 'text-gray-500'">🎂 Geburtstage</button>
            <button @click="tab = 'anniversaries'" class="pb-2" :class="tab === 'anniversaries' ? 'border-b-2 border-blue-600 font-semibold' : 'text-gray-500'">🏅 Jubiläen</button>
        </div>

        {{-- Tab Inhalte --}}
        <div x-show="tab === 'entries'">
            @forelse ($entries as $member)
                <div class="py-2 border-b">{{ $member->first_name }} {{ $member->last_name }} – Eintritt: {{ $member->entry_date->format('d.m.Y') }}</div>
            @empty
                <p class="text-gray-500">Keine Eintritte im aktuellen Monat.</p>
            @endforelse
        </div>

        <div x-show="tab === 'exits'">
            @forelse ($exits as $member)
                <div class="py-2 border-b">{{ $member->first_name }} {{ $member->last_name }} – Austritt: {{ $member->exit_date->format('d.m.Y') }}</div>
            @empty
                <p class="text-gray-500">Keine Austritte im aktuellen Monat.</p>
            @endforelse
        </div>

        <div x-show="tab === 'birthdays'">
            @forelse ($birthdays as $member)
                <div class="py-2 border-b">{{ $member->first_name }} {{ $member->last_name }} – 🎂 {{ $member->birthday->format('d.m.') }}</div>
            @empty
                <p class="text-gray-500">Keine Geburtstage im aktuellen Monat.</p>
            @endforelse
        </div>

        <div x-show="tab === 'anniversaries'">
            @forelse ($anniversaries as $member)
                <div class="py-2 border-b">{{ $member->first_name }} {{ $member->last_name }} – {{ now()->year - $member->entry_date->year }} Jahre Mitglied</div>
            @empty
                <p class="text-gray-500">Keine Jubiläen heute.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
