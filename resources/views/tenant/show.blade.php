@extends('layouts.app')

@section('title', 'Vereinsprofil')

@section('content')
<div class="max-w-4xl mx-auto space-y-10 text-gray-800 py-10">

    <h1 class="text-3xl font-extrabold text-[#2954A3]">
        🏢 Vereinsprofil
    </h1>

    <div class="bg-white rounded-2xl shadow-xl p-8 ring-1 ring-gray-200 space-y-6">

        {{-- Logo --}}
        @if ($tenant->logo_url)
            <div class="flex justify-center">
                <img src="{{ route('tenant.logo') }}"
                     alt="Logo des Vereins {{ $tenant->name }}"
                     class="h-28 object-contain rounded shadow">
            </div>
        @endif

        {{-- Stammdaten --}}
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <dt class="text-sm font-semibold text-gray-600">📛 Name</dt>
                <dd class="text-base">{{ $tenant->name }}</dd>
            </div>
            <div>
                <dt class="text-sm font-semibold text-gray-600">📧 E-Mail</dt>
                <dd class="text-base">
                    <a href="mailto:{{ $tenant->email }}" class="text-[#2954A3] hover:underline">
                        {{ $tenant->email }}
                    </a>
                </dd>
            </div>
            <div>
                <dt class="text-sm font-semibold text-gray-600">📍 Adresse</dt>
                <dd class="text-base">{{ $tenant->address }}, {{ $tenant->zip }} {{ $tenant->city }}</dd>
            </div>
            <div>
                <dt class="text-sm font-semibold text-gray-600">📞 Telefon</dt>
                <dd class="text-base">
                    <a href="tel:{{ $tenant->phone }}" class="text-[#2954A3] hover:underline">
                        {{ $tenant->phone }}
                    </a>
                </dd>
            </div>
            <div>
                <dt class="text-sm font-semibold text-gray-600">🧾 Registernummer</dt>
                <dd class="text-base">{{ $tenant->register_number }}</dd>
            </div>
            @if($tenant->chairman_name)
                <div>
                    <dt class="text-sm font-semibold text-gray-600">👤 Vorsitzende/r</dt>
                    <dd class="text-base">{{ $tenant->chairman_name }}</dd>
                </div>
            @endif
        </dl>

        {{-- Bankdaten --}}
        @if($tenant->iban || $tenant->bic || $tenant->bank_name)
        <div class="pt-4">
            <h2 class="text-xl font-semibold text-indigo-700 mb-4">🏦 Bankverbindung</h2>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @if($tenant->iban)
                    <div>
                        <dt class="text-sm font-semibold text-gray-600">IBAN</dt>
                        <dd class="text-base">{{ $tenant->iban }}</dd>
                    </div>
                @endif
                @if($tenant->bic)
                    <div>
                        <dt class="text-sm font-semibold text-gray-600">BIC</dt>
                        <dd class="text-base">{{ $tenant->bic }}</dd>
                    </div>
                @endif
                @if($tenant->bank_name)
                    <div>
                        <dt class="text-sm font-semibold text-gray-600">Bankname</dt>
                        <dd class="text-base">{{ $tenant->bank_name }}</dd>
                    </div>
                @endif
            </dl>
        </div>
        @endif

        {{-- Briefbogen --}}
        @if ($tenant->pdf_template)
            @php
                $ext = strtolower(pathinfo($tenant->pdf_template, PATHINFO_EXTENSION));
            @endphp

            <div class="pt-4">
                <dt class="text-sm font-semibold text-gray-600">📄 Briefbogen</dt>
                <dd class="mt-2">
                    @if(in_array($ext, ['jpg', 'jpeg', 'png']))
                        <img src="{{ route('tenant.letterhead') }}"
                             alt="Briefbogen"
                             class="w-full max-h-64 object-contain border border-gray-300 shadow rounded">
                    @else
                        <a href="{{ route('tenant.letterhead') }}"
                           target="_blank"
                           class="text-[#2954A3] hover:underline">
                            PDF anzeigen / herunterladen
                        </a>
                    @endif
                </dd>
            </div>
        @endif

        {{-- Briefbogen-Schalter --}}
        <div>
            <dt class="text-sm font-semibold text-gray-600">📝 Briefbogen verwenden</dt>
            <dd class="text-base">
                @if($tenant->use_letterhead)
                    ✅ Ja
                @else
                    ❌ Nein
                @endif
            </dd>
        </div>

        {{-- Bearbeiten-Button --}}
        <div class="text-right pt-6">
            <a href="{{ route('tenant.edit') }}"
               class="bg-[#2954A3] hover:bg-[#1E3F7F] text-white font-semibold px-6 py-3 rounded-xl shadow-md transition-all"
               aria-label="Vereinsdaten bearbeiten">
                ✏️ Bearbeiten
            </a>
        </div>
    </div>
</div>
@endsection
