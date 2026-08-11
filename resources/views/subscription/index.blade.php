@extends('layouts.app')

@section('title', 'Clubano Lizenz')

@section('content')
<div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
    @php($trialDays = (int) config('clubano.trial_days', 14))
    @php($tenant = auth()->user()->tenant)
    @php($hasComplimentaryAccess = $tenant?->hasComplimentaryAccess() ?? false)

    <div class="mx-auto max-w-3xl text-center">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-blue-600">Clubano Lizenz</p>
        <h1 class="mt-3 text-4xl font-semibold tracking-normal text-slate-950">Ein Preis. Zwei Zahlungsrhythmen.</h1>
        <p class="mt-4 text-base leading-7 text-slate-500">
            Alles drin für euren Verein. Bezahlt bequem per Kreditkarte oder SEPA-Lastschrift.
        </p>
    </div>

    @if($hasComplimentaryAccess)
        <div class="mx-auto mt-8 max-w-3xl rounded-2xl border border-emerald-200 bg-emerald-50 px-6 py-5 text-emerald-900">
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

    @if($tenant?->onTrial())
        <div class="mx-auto mt-8 max-w-3xl rounded-2xl border border-amber-200 bg-amber-50 px-6 py-5 text-left text-amber-900">
            <div class="text-sm font-semibold uppercase tracking-wide">Testphase aktiv</div>
            <div class="mt-2 text-2xl font-bold">Kostenlos bis {{ $tenant->trial_ends_at?->format('d.m.Y') }}</div>
            <p class="mt-2 text-sm text-amber-800">
                Ihr könnt Clubano bis dahin ganz normal nutzen. Ein Abo wird erst nach der Testphase berechnet.
            </p>
        </div>
    @endif

    <div class="mt-10 grid gap-5 lg:grid-cols-2">
        @forelse($billingPlans as $plan)
            <form method="POST" action="{{ route('subscription.checkout') }}" class="h-full">
                @csrf
                <input type="hidden" name="price_id" value="{{ $plan['stripe_price_id'] }}">

                <section class="flex h-full flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm {{ ($plan['key'] ?? '') === 'yearly' ? 'ring-2 ring-blue-600' : '' }}">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $plan['name'] }}</div>
                            <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ $plan['label'] }}</h2>
                        </div>
                        @if(!empty($plan['badge']))
                            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">{{ $plan['badge'] }}</span>
                        @endif
                    </div>

                    <div class="mt-6">
                        <span class="text-5xl font-semibold tracking-normal text-slate-950">{{ $plan['price'] }}</span>
                        <span class="text-lg text-slate-500">/ {{ $plan['interval'] }}</span>
                    </div>

                    <p class="mt-4 text-sm leading-6 text-slate-500">{{ $plan['description'] }}</p>

                    <div class="mt-6 space-y-2 text-sm text-slate-700">
                        <p>✓ Mitgliederverwaltung</p>
                        <p>✓ Finanzen & Beiträge</p>
                        <p>✓ Protokolle & Events</p>
                        <p>✓ Nutzerrollen</p>
                        <p>✓ Import & Export</p>
                        <p>✓ Zahlung per Kreditkarte oder SEPA-Lastschrift</p>
                    </div>

                    <button class="mt-8 inline-flex min-h-12 w-full items-center justify-center rounded-2xl bg-blue-700 px-5 text-sm font-semibold text-white transition hover:bg-blue-800">
                        {{ $hasComplimentaryAccess ? 'Trotzdem Abo abschließen' : ($tenant?->onTrial() ? 'Abo vorbereiten' : 'Abo auswählen') }}
                    </button>

                    <p class="mt-4 text-center text-xs leading-5 text-slate-500">
                        @if($tenant?->onTrial())
                            Deine laufende Testphase bleibt bis {{ $tenant->trial_ends_at?->format('d.m.Y') }} bestehen.
                        @else
                            {{ $trialDays }} Tage kostenlos testen · Keine Kündigungsfrist
                        @endif
                    </p>
                </section>
            </form>
        @empty
            <section class="lg:col-span-2 rounded-3xl border border-rose-200 bg-rose-50 p-6 text-rose-900">
                <h2 class="text-xl font-semibold">Keine Stripe-Preise hinterlegt</h2>
                <p class="mt-2 text-sm">Bitte hinterlege die Price-IDs in der Clubano-Konfiguration.</p>
            </section>
        @endforelse
    </div>

    <div class="mx-auto mt-8 max-w-3xl rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm leading-6 text-slate-600">
        Stripe verarbeitet die Zahlung sicher. Bei SEPA-Lastschrift wird der Betrag per Bankeinzug vom angegebenen Konto abgebucht.
    </div>
</div>
@endsection
