@extends('layouts.app')

@section('title', 'Mein Profil')

@section('content')

<div class="max-w-4xl mx-auto space-y-8 py-10">

    {{-- HEADER --}}
    <div>
        <h2 class="text-2xl font-bold text-[#2954A3]">
            Mein Profil
        </h2>
        <p class="text-sm text-gray-500">
            Verwalte deine persönlichen Daten und Sicherheitseinstellungen
        </p>
    </div>

    {{-- Profilinformationen --}}
    <section class="bg-white p-6 rounded-lg shadow">

        <h3 class="text-lg font-medium mb-4">
            Profilinformationen
        </h3>

        <form id="send-verification" method="post" action="{{ route('verification.send') }}">
            @csrf
        </form>

        <form method="post" action="{{ route('profile.update') }}" class="space-y-6">
            @csrf
            @method('patch')

            {{-- Name --}}
            <div>
                <label class="text-sm text-gray-700">Name</label>
                <input name="name"
                       type="text"
                       class="mt-1 w-full border rounded p-2"
                       value="{{ old('name', auth()->user()->name) }}">
            </div>

            {{-- E-Mail --}}
            <div>
                <label class="text-sm text-gray-700">E-Mail</label>
                <input name="email"
                       type="email"
                       class="mt-1 w-full border rounded p-2"
                       value="{{ old('email', auth()->user()->email) }}">

                @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !auth()->user()->hasVerifiedEmail())
                    <div class="mt-2 text-sm text-gray-600">
                        <p>E-Mail nicht verifiziert</p>
                        <button form="send-verification"
                            class="text-blue-600 underline">
                            Verifizierung senden
                        </button>
                    </div>
                @endif
            </div>

            <button class="bg-blue-600 text-white px-4 py-2 rounded">
                Speichern
            </button>
        </form>
    </section>

    {{-- Passwort --}}
    <section class="bg-white p-6 rounded-lg shadow">
        <h3 class="text-lg font-medium mb-4">Passwort ändern</h3>
        @include('profile.partials.update-password-form')
    </section>

    {{-- Account löschen --}}
    <section class="bg-white p-6 rounded-lg shadow">
        <h3 class="text-lg font-medium text-red-600 mb-4">
            Account löschen
        </h3>
        @include('profile.partials.delete-user-form')
    </section>

</div>

@endsection