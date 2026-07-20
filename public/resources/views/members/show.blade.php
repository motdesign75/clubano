@extends('layouts.app')

@section('title', 'Mitglied: ' . $member->first_name . ' ' . $member->last_name)

@section('content')

<div class="max-w-7xl mx-auto space-y-6">


    {{-- HEADER --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">

        <div>

            <h1 class="text-2xl font-bold text-[#2954A3]">

                Mitglied

            </h1>

            <p class="text-sm text-gray-500">

                {{ $member->salutation }}
                {{ $member->first_name }}
                {{ $member->last_name }}

            </p>

        </div>


        <div class="flex gap-3">

            <a href="{{ route('members.edit', $member) }}"
               class="px-4 py-2 bg-[#2954A3] text-white rounded-lg shadow hover:bg-[#1E3F7F]">

                ✏️ Bearbeiten

            </a>

            <a href="{{ route('members.index') }}"
               class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">

                Zurück

            </a>

        </div>

    </div>



    {{-- MITGLIED --}}
    <div class="bg-white rounded-xl shadow p-6">

        <h2 class="font-semibold text-[#2954A3] mb-4">

            Mitglied

        </h2>


        <div class="flex flex-col md:flex-row gap-6">


            {{-- FOTO --}}
            @if($member->photo)

                <div>

                    <img
                        src="{{ asset('storage/' . $member->photo) }}"
                        class="w-32 h-32 rounded-full object-cover shadow"
                    >

                </div>

            @endif



            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 w-full">

                <x-member.detail label="Geschlecht" :value="$member->gender" />
                <x-member.detail label="Anrede" :value="$member->salutation" />
                <x-member.detail label="Titel" :value="$member->title" />
                <x-member.detail label="Vorname" :value="$member->first_name" />
                <x-member.detail label="Nachname" :value="$member->last_name" />
                <x-member.detail label="Organisation" :value="$member->organization" />
                <x-member.detail label="Geburtstag" :value="$member->birthday" />

            </div>

        </div>

    </div>



    {{-- MITGLIEDSCHAFT --}}
    <div class="bg-white rounded-xl shadow p-6">

        <h2 class="font-semibold text-green-600 mb-4">

            Mitgliedschaft

        </h2>


        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <x-member.detail
                label="Mitgliedschaft"
                :value="$member->membership
                    ? $member->membership->name . ' – '
                      . number_format($member->membership->fee, 2, ',', '.')
                      . ' € / '
                      . $member->membership->billing_cycle
                    : '–'"
            />

            <x-member.detail label="Mitgliedsnummer" :value="$member->member_id" />
            <x-member.detail label="Eintritt" :value="$member->entry_date" />
            <x-member.detail label="Austritt" :value="$member->exit_date" />
            <x-member.detail label="Kündigungsdatum" :value="$member->termination_date" />

        </div>

    </div>



    {{-- KOMMUNIKATION --}}
    <div class="bg-white rounded-xl shadow p-6">

        <h2 class="font-semibold text-yellow-600 mb-4">

            Kommunikation

        </h2>


        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <x-member.detail
                label="E-Mail"
                :value="$member->email"
                :link="$member->email ? 'mailto:' . $member->email : null"
            />

            <x-member.detail
                label="Mobil"
                :value="$member->mobile"
                :link="$member->mobile ? 'tel:' . $member->mobile : null"
            />

            <x-member.detail
                label="Festnetz"
                :value="$member->landline"
                :link="$member->landline ? 'tel:' . $member->landline : null"
            />

        </div>

    </div>



    {{-- ADRESSE --}}
    <div class="bg-white rounded-xl shadow p-6">

        <h2 class="font-semibold text-purple-600 mb-4">

            Adresse

        </h2>


        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <x-member.detail label="Straße + Nr." :value="$member->street" />
            <x-member.detail label="Adresszusatz" :value="$member->address_addition" />
            <x-member.detail label="PLZ" :value="$member->zip" />
            <x-member.detail label="Ort" :value="$member->city" />
            <x-member.detail
                label="Land"
                :value="config('countries.list')[$member->country] ?? $member->country"
            />
            <x-member.detail label="C/O" :value="$member->care_of" />

        </div>

    </div>



    {{-- CUSTOM FIELDS --}}
    @if($customFields->count())

        <div class="bg-white rounded-xl shadow p-6">

            <h2 class="font-semibold text-pink-600 mb-4">

                Benutzerdefinierte Felder

            </h2>


            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                @foreach($customFields as $field)

                    @php

                        $value = optional(
                            $member->customValues
                                ->firstWhere('custom_member_field_id', $field->id)
                        )->value ?? '–';


                        if ($field->type === 'date' && $value !== '–') {
                            $value = \Carbon\Carbon::parse($value)->format('d.m.Y');
                        }

                        if ($field->type === 'select' && $value !== '–' && $field->options) {

                            $options = explode('|', $field->options);

                            $value = in_array($value, $options)
                                ? $value
                                : '–';
                        }

                    @endphp


                    <x-member.detail
                        :label="$field->label"
                        :value="$value"
                    />

                @endforeach

            </div>

        </div>

    @endif



</div>

@endsection