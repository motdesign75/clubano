@extends('layouts.sidebar')

@section('title', 'Mitglied: ' . $member->first_name . ' ' . $member->last_name)

@section('content')
<div class="max-w-6xl mx-auto space-y-10 text-gray-800">
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-extrabold text-[#2954A3]">
            👤 Mitglied: {{ $member->salutation }} {{ $member->first_name }} {{ $member->last_name }}
        </h1>
        <a href="{{ route('members.edit', $member) }}" class="bg-[#2954A3] text-white px-5 py-2 rounded-2xl shadow hover:bg-[#1E3F7F] transition-all">
            ✏️ Bearbeiten
        </a>
    </div>

    {{-- Block: Mitglied --}}
    <section class="bg-white rounded-2xl shadow-soft p-6 ring-4 ring-[#DBEAFE]">
        <h2 class="text-xl font-semibold text-[#2954A3] mb-4">🧍 Mitglied</h2>
        <div class="flex flex-col md:flex-row gap-6">
            @if($member->photo)
                <div class="w-32 h-32 shrink-0">
                    <img src="{{ asset('storage/' . $member->photo) }}" alt="Profilfoto"
                         class="w-32 h-32 object-cover rounded-full border shadow">
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
    </section>

    {{-- Block: Mitgliedschaft --}}
    <section class="bg-white rounded-2xl shadow-soft p-6 ring-4 ring-[#D1FAE5]">
        <h2 class="text-xl font-semibold text-green-600 mb-4">📝 Mitgliedschaft</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-member.detail label="Mitgliedschaft" :value="$member->membership ? $member->membership->name . ' – ' . number_format($member->membership->fee, 2, ',', '.') . ' € / ' . $member->membership->billing_cycle : '–'" />
            <x-member.detail label="Mitgliedsnummer" :value="$member->member_id" />
            <x-member.detail label="Eintritt" :value="$member->entry_date" />
            <x-member.detail label="Austritt" :value="$member->exit_date" />
            <x-member.detail label="Kündigungsdatum" :value="$member->termination_date" />
        </div>
    </section>

    {{-- Block: Kommunikation --}}
    <section class="bg-white rounded-2xl shadow-soft p-6 ring-4 ring-[#FEF3C7]">
        <h2 class="text-xl font-semibold text-yellow-600 mb-4">📞 Kommunikation</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-member.detail label="E-Mail" :value="$member->email" :link="'mailto:' . $member->email" />
            <x-member.detail label="Mobil" :value="$member->mobile" :link="'tel:' . $member->mobile" />
            <x-member.detail label="Festnetz" :value="$member->landline" :link="'tel:' . $member->landline" />
        </div>
    </section>

    {{-- Block: Adresse --}}
    <section class="bg-white rounded-2xl shadow-soft p-6 ring-4 ring-[#EDE9FE]">
        <h2 class="text-xl font-semibold text-purple-600 mb-4">📍 Adresse</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-member.detail label="Straße + Nr." :value="$member->street" />
            <x-member.detail label="Adresszusatz" :value="$member->address_addition" />
            <x-member.detail label="PLZ" :value="$member->zip" />
            <x-member.detail label="Ort" :value="$member->city" />
            <x-member.detail label="Land" :value="config('countries.list')[$member->country] ?? $member->country" />
            <x-member.detail label="C/O" :value="$member->care_of" />
        </div>
    </section>

    {{-- Block: Benutzerdefinierte Felder --}}
    @if($customFields->count())
    <section class="bg-white rounded-2xl shadow-soft p-6 ring-4 ring-[#FCE7F3]">
        <h2 class="text-xl font-semibold text-pink-600 mb-4">🧩 Benutzerdefinierte Felder</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($customFields as $field)
                @php
                    $value = optional($member->customValues->firstWhere('custom_member_field_id', $field->id))->value ?? '–';

                    if ($field->type === 'date' && $value !== '–') {
                        $value = \Carbon\Carbon::parse($value)->format('d.m.Y');
                    }

                    if ($field->type === 'select' && $value !== '–' && $field->options) {
                        $options = explode('|', $field->options);
                        $value = in_array($value, $options) ? $value : '–';
                    }
                @endphp

                <x-member.detail :label="$field->label" :value="$value" />
            @endforeach
        </div>
    </section>
    @endif

    {{-- Zurück-Link --}}
    <div class="pt-4">
        <a href="{{ route('members.index') }}" class="text-sm text-gray-600 hover:text-[#2954A3] underline">
            ← Zur Mitgliederübersicht
        </a>
    </div>
</div>
@endsection
