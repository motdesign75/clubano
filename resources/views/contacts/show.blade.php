@extends('layouts.app')

@section('content')
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight">
                    {{ $contact->display_name }}
                </h2>

                <p class="mt-1 text-sm text-gray-500">
                    Kontakt anzeigen
                </p>
            </div>

            <div class="flex gap-2">
                @if($contact->primary_email)
                    <a href="{{ route('mail.create', ['contacts' => $contact->id]) }}"
                       class="inline-flex items-center justify-center rounded-md border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100">
                        Mail senden
                    </a>
                @endif

                <a href="{{ route('letters.create', ['contacts' => $contact->id]) }}"
                   class="inline-flex items-center justify-center rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100">
                    Brief erstellen
                </a>

                @can('update', $contact)
                    <a href="{{ route('contacts.edit', $contact) }}"
                       class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                        Bearbeiten
                    </a>
                @endcan

                <a href="{{ route('contacts.index') }}"
                   class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Zurück
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto mt-6 px-4 sm:px-6 lg:px-8 space-y-6">

        @if(session('success'))
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        {{-- Kopfkarte --}}
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-2xl font-bold text-indigo-700">
                        {{ mb_substr($contact->display_name, 0, 1) }}
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-2xl font-bold text-gray-900">
                                {{ $contact->display_name }}
                            </h3>

                            @if($contact->is_favorite)
                                <span class="inline-flex rounded-full bg-yellow-100 px-2 py-1 text-xs font-medium text-yellow-800">
                                    Favorit
                                </span>
                            @endif

                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium
                                {{ $contact->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                {{ $contact->status_label }}
                            </span>
                        </div>

                        <div class="mt-2 flex flex-wrap gap-2 text-sm text-gray-600">
                            <span>
                                {{ $contact->contact_type === 'organization' ? 'Organisation' : 'Person' }}
                            </span>

                            @if($contact->category)
                                <span>·</span>
                                <span>{{ ucfirst($contact->category) }}</span>
                            @endif

                            @if($contact->relationship)
                                <span>·</span>
                                <span>{{ $contact->relationship }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-2 text-sm md:text-right">
                    @if($contact->primary_email)
                        <a href="mailto:{{ $contact->primary_email }}"
                           class="text-indigo-600 hover:text-indigo-800">
                            {{ $contact->primary_email }}
                        </a>
                    @endif

                    @if($contact->primary_phone)
                        <a href="tel:{{ $contact->primary_phone }}"
                           class="text-gray-700 hover:text-indigo-700">
                            {{ $contact->primary_phone }}
                        </a>
                    @endif

                    @if($contact->website)
                        <a href="{{ $contact->website }}"
                           target="_blank"
                           rel="noopener"
                           class="text-gray-700 hover:text-indigo-700">
                            {{ $contact->website }}
                        </a>
                    @endif
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

            {{-- Person / Organisation --}}
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">
                    Person & Organisation
                </h3>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Organisation</dt>
                        <dd class="col-span-2 text-gray-900">
                            {{ $contact->organization ?: $contact->company ?: '-' }}
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Abteilung</dt>
                        <dd class="col-span-2 text-gray-900">
                            {{ $contact->department ?: '-' }}
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Position</dt>
                        <dd class="col-span-2 text-gray-900">
                            {{ $contact->position ?: '-' }}
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Anrede</dt>
                        <dd class="col-span-2 text-gray-900">
                            {{ $contact->salutation ?: '-' }}
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Titel</dt>
                        <dd class="col-span-2 text-gray-900">
                            {{ $contact->title ?: '-' }}
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Vorname</dt>
                        <dd class="col-span-2 text-gray-900">
                            {{ $contact->first_name ?: '-' }}
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Nachname</dt>
                        <dd class="col-span-2 text-gray-900">
                            {{ $contact->last_name ?: '-' }}
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Geburtstag</dt>
                        <dd class="col-span-2 text-gray-900">
                            {{ $contact->birthday ? $contact->birthday->format('d.m.Y') : '-' }}
                        </dd>
                    </div>
                </dl>
            </section>

            {{-- Kommunikation --}}
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">
                    Kommunikation
                </h3>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">E-Mail</dt>
                        <dd class="col-span-2 text-gray-900">
                            @if($contact->email)
                                <a href="mailto:{{ $contact->email }}" class="text-indigo-600 hover:text-indigo-800">
                                    {{ $contact->email }}
                                </a>
                            @else
                                -
                            @endif
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Zweite E-Mail</dt>
                        <dd class="col-span-2 text-gray-900">
                            @if($contact->secondary_email)
                                <a href="mailto:{{ $contact->secondary_email }}" class="text-indigo-600 hover:text-indigo-800">
                                    {{ $contact->secondary_email }}
                                </a>
                            @else
                                -
                            @endif
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Mobil</dt>
                        <dd class="col-span-2 text-gray-900">
                            @if($contact->mobile ?: $contact->phone_mobile)
                                <a href="tel:{{ $contact->mobile ?: $contact->phone_mobile }}" class="text-gray-900 hover:text-indigo-700">
                                    {{ $contact->mobile ?: $contact->phone_mobile }}
                                </a>
                            @else
                                -
                            @endif
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Telefon</dt>
                        <dd class="col-span-2 text-gray-900">
                            @if($contact->phone ?: $contact->phone_landline)
                                <a href="tel:{{ $contact->phone ?: $contact->phone_landline }}" class="text-gray-900 hover:text-indigo-700">
                                    {{ $contact->phone ?: $contact->phone_landline }}
                                </a>
                            @else
                                -
                            @endif
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Fax</dt>
                        <dd class="col-span-2 text-gray-900">
                            {{ $contact->fax ?: '-' }}
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Website</dt>
                        <dd class="col-span-2 text-gray-900">
                            @if($contact->website)
                                <a href="{{ $contact->website }}"
                                   target="_blank"
                                   rel="noopener"
                                   class="text-indigo-600 hover:text-indigo-800">
                                    {{ $contact->website }}
                                </a>
                            @else
                                -
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>

            {{-- Adresse --}}
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">
                    Adresse
                </h3>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">c/o</dt>
                        <dd class="col-span-2 text-gray-900">
                            {{ $contact->care_of ?: '-' }}
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Straße</dt>
                        <dd class="col-span-2 text-gray-900">
                            {{ $contact->street ?: '-' }}
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Adresszusatz</dt>
                        <dd class="col-span-2 text-gray-900">
                            {{ $contact->address_addition ?: $contact->street_addition ?: '-' }}
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">PLZ / Ort</dt>
                        <dd class="col-span-2 text-gray-900">
                            @if($contact->zip || $contact->postal_code || $contact->city)
                                {{ trim(($contact->zip ?: $contact->postal_code ?: '') . ' ' . ($contact->city ?: '')) }}
                            @else
                                -
                            @endif
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Region</dt>
                        <dd class="col-span-2 text-gray-900">
                            {{ $contact->state ?: '-' }}
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Land</dt>
                        <dd class="col-span-2 text-gray-900">
                            {{ $contact->country_name ?: '-' }}
                        </dd>
                    </div>
                </dl>
            </section>

            {{-- Beziehung / Datenschutz --}}
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">
                    Beziehung & Datenschutz
                </h3>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Beziehung</dt>
                        <dd class="col-span-2 text-gray-900">
                            {{ $contact->relationship ?: '-' }}
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Quelle</dt>
                        <dd class="col-span-2 text-gray-900">
                            {{ $contact->source ?: '-' }}
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Letzter Kontakt</dt>
                        <dd class="col-span-2 text-gray-900">
                            {{ $contact->last_contacted_at ? $contact->last_contacted_at->format('d.m.Y') : '-' }}
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Einwilligung am</dt>
                        <dd class="col-span-2 text-gray-900">
                            {{ $contact->consent_given_at ? $contact->consent_given_at->format('d.m.Y') : '-' }}
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <dt class="text-gray-500">Erlaubt</dt>
                        <dd class="col-span-2 flex flex-wrap gap-2">
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $contact->consent_email ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                E-Mail
                            </span>

                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $contact->consent_phone ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                Telefon
                            </span>

                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $contact->consent_post ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                Post
                            </span>
                        </dd>
                    </div>
                </dl>
            </section>
        </div>

        {{-- Notizen --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">
                    Notizen
                </h3>

                <div class="mt-4 whitespace-pre-line text-sm text-gray-800">
                    {{ $contact->notes ?: 'Keine Notizen vorhanden.' }}
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">
                    Interne Notizen
                </h3>

                <div class="mt-4 whitespace-pre-line text-sm text-gray-800">
                    {{ $contact->internal_notes ?: 'Keine internen Notizen vorhanden.' }}
                </div>
            </section>
        </div>

        {{-- Aktionen unten --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('contacts.index') }}"
               class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Zurück zur Übersicht
            </a>

            <div class="flex gap-2">
                @if($contact->primary_email)
                    <a href="{{ route('mail.create', ['contacts' => $contact->id]) }}"
                       class="inline-flex items-center justify-center rounded-md border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100">
                        Mail senden
                    </a>
                @endif

                <a href="{{ route('letters.create', ['contacts' => $contact->id]) }}"
                   class="inline-flex items-center justify-center rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100">
                    Brief erstellen
                </a>

                @can('update', $contact)
                    <a href="{{ route('contacts.edit', $contact) }}"
                       class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                        Bearbeiten
                    </a>
                @endcan

                @can('delete', $contact)
                    <form method="POST"
                          action="{{ route('contacts.destroy', $contact) }}"
                          onsubmit="return confirm('Kontakt wirklich löschen?');">
                        @csrf
                        @method('DELETE')

                        <button type="submit"
                                class="inline-flex items-center justify-center rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">
                            Löschen
                        </button>
                    </form>
                @endcan
            </div>
        </div>
    </div>
@endsection
