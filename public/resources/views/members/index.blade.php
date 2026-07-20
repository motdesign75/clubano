@extends('layouts.app')

@section('title', 'Mitglieder')

@section('content')

<div class="max-w-7xl mx-auto space-y-6">


    {{-- HEADER --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">

        <div>
            <h1 class="text-2xl font-bold text-[#2954A3]">
                Mitglieder
            </h1>

            <p class="text-sm text-gray-500">
                Übersicht aller Vereinsmitglieder
            </p>
        </div>


        <a href="{{ route('members.create') }}"
           class="px-4 py-2 bg-[#2954A3] text-white rounded-lg shadow hover:bg-[#1E3F7F] transition">

            ➕ Neues Mitglied

        </a>

    </div>



    {{-- SEARCH --}}
    <div class="bg-white rounded-xl shadow p-4">

        <form method="GET"
              action="{{ route('members.index') }}"
              class="flex flex-col md:flex-row gap-4">

            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Mitglied suchen…"
                class="border rounded-lg p-2 w-full md:w-1/3"
            >

            <button
                class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">

                Suchen

            </button>

        </form>

    </div>



    @if($members->isEmpty())

        <div class="bg-white rounded-xl shadow p-6 text-gray-500">
            Keine Mitglieder gefunden
        </div>

    @else



    {{-- DESKTOP TABLE --}}
    <div class="hidden md:block bg-white rounded-xl shadow overflow-hidden">

        <table class="w-full text-sm">

            <thead class="bg-gray-100 text-gray-600 text-xs uppercase">

            <tr>

                <th class="p-3">Foto</th>
                <th class="p-3 text-left">Anrede</th>
                <th class="p-3 text-left">Vorname</th>
                <th class="p-3 text-left">Nachname</th>
                <th class="p-3">E-Mail</th>
                <th class="p-3">Mobil</th>
                <th class="p-3 text-right">Aktion</th>

            </tr>

            </thead>



            <tbody>

            @foreach($members as $member)

                <tr class="border-t hover:bg-blue-50">

                    {{-- FOTO --}}
                    <td class="p-3">

                        @if($member->photo)

                            <img
                                src="{{ asset('storage/'.$member->photo) }}"
                                class="w-10 h-10 rounded-full object-cover"
                            >

                        @else

                            <div class="w-10 h-10 rounded-full bg-gray-200"></div>

                        @endif

                    </td>



                    <td class="p-3">

                        {{ $member->salutation }}

                    </td>



                    <td class="p-3 font-semibold text-[#2954A3]">

                        <a href="{{ route('members.show',$member) }}"
                           class="hover:underline">

                            {{ $member->first_name }}

                        </a>

                    </td>



                    <td class="p-3">

                        {{ $member->last_name }}

                    </td>



                    <td class="p-3">

                        {{ $member->email ?? '-' }}

                    </td>



                    <td class="p-3">

                        {{ $member->mobile ?? '-' }}

                    </td>



                    <td class="p-3 text-right">

                        <div class="flex justify-end gap-3">

                            <a href="{{ route('members.show',$member) }}"
                               class="text-blue-600">

                                🔍

                            </a>


                            <a href="{{ route('members.edit',$member) }}"
                               class="text-yellow-600">

                                ✏️

                            </a>


                            <a href="{{ route('members.pdf',$member) }}"
                               target="_blank"
                               class="text-indigo-600">

                                📄

                            </a>

                        </div>

                    </td>

                </tr>

            @endforeach

            </tbody>

        </table>

    </div>



    {{-- MOBILE CARDS --}}
    <div class="md:hidden space-y-4">

        @foreach($members as $member)

            <div class="bg-white rounded-xl shadow p-4 space-y-2">


                <div class="flex gap-3 items-center">

                    @if($member->photo)

                        <img
                            src="{{ asset('storage/'.$member->photo) }}"
                            class="w-12 h-12 rounded-full object-cover"
                        >

                    @else

                        <div class="w-12 h-12 rounded-full bg-gray-200"></div>

                    @endif


                    <div>

                        <div class="font-bold text-[#2954A3]">

                            {{ $member->first_name }}
                            {{ $member->last_name }}

                        </div>

                        <div class="text-sm text-gray-500">

                            {{ $member->email }}

                        </div>

                    </div>

                </div>


                <div class="flex justify-end gap-4 pt-2">

                    <a href="{{ route('members.show',$member) }}">
                        🔍
                    </a>

                    <a href="{{ route('members.edit',$member) }}">
                        ✏️
                    </a>

                    <a href="{{ route('members.pdf',$member) }}">
                        📄
                    </a>

                </div>

            </div>

        @endforeach

    </div>



    {{-- PAGINATION --}}
    <div>

        {{ $members->appends(request()->query())->links() }}

    </div>


    @endif


</div>

@endsection