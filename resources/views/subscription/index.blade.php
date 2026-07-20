@extends('layouts.app')

@section('title', 'Clubano Lizenz')

@section('content')
<div class="max-w-2xl mx-auto py-12 text-center">
    @php($trialDays = (int) config('clubano.trial_days', 14))
    @php($tenant = auth()->user()->tenant)
    @php($hasComplimentaryAccess = $tenant?->hasComplimentaryAccess() ?? false)

    @if($hasComplimentaryAccess)
        <div class="mb-8 rounded-2xl border border-emerald-200 bg-emerald-50 px-6 py-5 text-left text-emerald-900">
            <div class="text-sm font-semibold uppercase tracking-wide">Zugang aktiv</div>
            <div class="mt-2 text-2xl font-bold">{{ $tenant->license_mode_label }}</div>
            <p class="mt-2 text-sm text-emerald-800">
                @if($tenant->license_expires_at)
                    Dieser Verein ist bis zum {{ $tenant->license_expires_at->format('d.m.Y') }} freigeschaltet.
                @else
                    Dieser Verein ist aktuell ohne Enddatum freigeschaltet.
                @endif
            </p>
        </div>
    @endif

    <h1 class="text-3xl font-bold mb-4">
        Ein Preis. Alles drin.
    </h1>

    <p class="text-gray-600 mb-8">
        Keine versteckten Kosten. Keine komplizierten Tarife.
    </p>

    @if($tenant?->onTrial())
        <div class="mb-8 rounded-2xl border border-amber-200 bg-amber-50 px-6 py-5 text-left text-amber-900">
            <div class="text-sm font-semibold uppercase tracking-wide">Testphase aktiv</div>
            <div class="mt-2 text-2xl font-bold">Kostenlos bis {{ $tenant->trial_ends_at?->format('d.m.Y') }}</div>
            <p class="mt-2 text-sm text-amber-800">
                Ihr könnt Clubano bis dahin ganz normal nutzen. Ein Abo ist erst danach notwendig.
            </p>
        </div>
    @endif

    <form method="POST" action="{{ route('subscription.checkout') }}">
        @csrf

        {{-- 🔥 DEINE STRIPE PRICE ID --}}
        <input type="hidden" name="price_id" value="price_1TMm3iLTnGBaGb0l8O7P19vr">

        <div class="bg-white p-8 rounded-3xl shadow-lg">

            <h2 class="text-2xl font-semibold mb-2">Clubano</h2>

            <p class="text-4xl font-bold mb-6">
                19,99 € <span class="text-lg font-normal">/ Monat</span>
            </p>

            <div class="text-left space-y-2 text-gray-700 mb-6">
                <p>✔ Mitgliederverwaltung</p>
                <p>✔ Finanzen & Beiträge</p>
                <p>✔ Protokolle & Events</p>
                <p>✔ DSGVO-Export</p>
                <p>✔ Nutzerrollen</p>
                <p>✔ Import & Export</p>
            </div>

            <button class="w-full bg-blue-600 text-white py-3 rounded-xl text-lg hover:bg-blue-700">
                {{ $hasComplimentaryAccess ? 'Trotzdem Abo abschließen' : ($tenant?->onTrial() ? 'Abo für später vorbereiten' : 'Jetzt kostenlos starten') }}
            </button>

            <p class="text-xs text-gray-500 mt-4">
                @if($tenant?->onTrial())
                    Deine laufende Testphase bleibt bis {{ $tenant->trial_ends_at?->format('d.m.Y') }} bestehen.
                @else
                    {{ $trialDays }} Tage kostenlos testen · Keine Kündigungsfrist
                @endif
            </p>

        </div>

    </form>

</div>
@endsection
