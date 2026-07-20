@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto p-6">
    @php($trialDays = (int) config('clubano.trial_days', 14))
    @php($tenant = auth()->user()->tenant)
    @php($hasComplimentaryAccess = $tenant?->hasComplimentaryAccess() ?? false)
    <h1 class="text-2xl font-semibold mb-6">Abo auswählen</h1>

    @if($hasComplimentaryAccess)
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-900">
            <div class="text-sm font-semibold uppercase tracking-wide">Freigeschaltet</div>
            <div class="mt-1 text-lg font-bold">{{ $tenant->license_mode_label }}</div>
            <p class="mt-1 text-sm text-emerald-800">
                @if($tenant->license_expires_at)
                    Gültig bis {{ $tenant->license_expires_at->format('d.m.Y') }}.
                @else
                    Aktuell ohne Enddatum aktiv.
                @endif
                Du kannst trotzdem schon auf ein reguläres Abo wechseln, wenn du das möchtest.
            </p>
        </div>
    @endif

    <div class="grid md:grid-cols-3 gap-6">
        @foreach($plans as $key => $plan)
            @php
                // erste Price-ID verwenden
                $priceId = $plan['stripe_price_ids'][0] ?? null;
                $name = $plan['name'] ?? ucfirst($key);
                $limit = $plan['member_limit'] ?? null;
            @endphp

            <div class="bg-white rounded-xl shadow p-5">
                <div class="text-lg font-semibold">{{ $name }}</div>

                <div class="text-sm text-gray-600 mt-1">
                    @if(!empty($limit))
                        Bis zu {{ $limit }} Mitglieder
                    @else
                        Unbegrenzte Mitglieder
                    @endif
                </div>

                <div class="mt-3 inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">
                    @if($tenant->onTrial())
                        Testphase aktiv bis {{ $tenant->trial_ends_at?->format('d.m.Y') }}
                    @else
                        {{ $trialDays }} Tage kostenlos testen
                    @endif
                </div>

                @if($priceId)
                    <form method="POST" action="{{ route('billing.subscribe', ['priceId' => $priceId]) }}" class="mt-5">
                        @csrf
                        <button class="w-full py-2 rounded-lg bg-black text-white">
                            {{ $name }} wählen
                        </button>
                    </form>
                @else
                    <div class="mt-5 text-sm text-red-600">
                        Keine Stripe Price-ID hinterlegt.
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-8 text-sm text-gray-500">
        Du wirst zu Stripe weitergeleitet, um das Abo abzuschließen.
    </div>
</div>
@endsection
