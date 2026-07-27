@extends('layouts.app')

@section('title', 'Spende')

@section('content')
@php
    $readiness = $tenant->loadMissing('donationFreistellungDocument')->donationCertificateReadiness();
@endphp

<div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Spende</div>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ $donation->donor_name }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $donation->donated_at->format('d.m.Y') }} · {{ number_format($donation->amount, 2, ',', '.') }} €</p>
        </div>
        <a href="{{ route('donations.index') }}" class="inline-flex justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">Zur Übersicht</a>
    </div>

    @unless($readiness['can_issue'])
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
            <div class="font-semibold">PDF-Erstellung gesperrt: Gemeinnützigkeitsnachweis fehlt oder ist unvollständig.</div>
            <p class="mt-1">Clubano erstellt erst dann eine Zuwendungsbestätigung, wenn ein gültiger Freistellungsbescheid hinterlegt ist.</p>
            <a href="{{ route('donations.settings') }}" class="mt-3 inline-flex rounded-xl bg-amber-900 px-4 py-2 text-sm font-medium text-white hover:bg-amber-800">Einstellungen öffnen</a>
        </div>
    @endunless

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <section class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">Zuwendung</h2>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm text-slate-500">Status</dt>
                        <dd class="mt-1 font-semibold text-slate-950">{{ $donation->status_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-slate-500">Bescheinigungsnummer</dt>
                        <dd class="mt-1 font-semibold text-slate-950">{{ $donation->certificate_number ?: 'Noch nicht erstellt' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-slate-500">Zweck</dt>
                        <dd class="mt-1 font-semibold text-slate-950">{{ $donation->purpose ?: 'Allgemeine Spende' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-slate-500">Zahlungsart</dt>
                        <dd class="mt-1 font-semibold text-slate-950">{{ $donation->payment_method ? ucfirst($donation->payment_method) : 'Nicht angegeben' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">Spenderdaten</h2>
                <div class="mt-5 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    <div class="font-semibold text-slate-950">{{ $donation->donor_name }}</div>
                    @if($donation->donor_street)<div class="mt-1">{{ $donation->donor_street }}</div>@endif
                    @if($donation->donor_zip || $donation->donor_city)<div>{{ $donation->donor_zip }} {{ $donation->donor_city }}</div>@endif
                    @if($donation->donor_email)<div class="mt-2">{{ $donation->donor_email }}</div>@endif
                </div>
                @if($donation->member)
                    <a href="{{ route('members.show', $donation->member) }}" class="mt-4 inline-flex rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Mitglied öffnen</a>
                @endif
            </div>

            @if($donation->notes)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Interne Notiz</h2>
                    <p class="mt-3 whitespace-pre-line text-sm text-slate-600">{{ $donation->notes }}</p>
                </div>
            @endif
        </section>

        <aside class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-950">Nächster Schritt</h2>
                <p class="mt-2 text-sm text-slate-500">Erstelle die PDF-Bestätigung erst, wenn die Vereinsangaben geprüft sind.</p>
                <a href="{{ $readiness['can_issue'] ? route('donations.pdf', $donation) : route('donations.settings') }}" class="mt-4 inline-flex w-full justify-center rounded-xl {{ $readiness['can_issue'] ? 'bg-slate-900 text-white hover:bg-slate-800' : 'bg-amber-900 text-white hover:bg-amber-800' }} px-4 py-2.5 text-sm font-medium">
                    PDF erstellen
                </a>
                @if($donation->status !== 'sent')
                    <form method="POST" action="{{ route('donations.mark-sent', $donation) }}" class="mt-3">
                        @csrf
                        @method('PATCH')
                        <button type="submit" @disabled(! $readiness['can_issue']) class="inline-flex w-full justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium {{ $readiness['can_issue'] ? 'text-slate-600 hover:bg-slate-50' : 'cursor-not-allowed bg-slate-100 text-slate-400' }}">Als versendet markieren</button>
                    </form>
                @endif
            </div>

            @if($donation->transaction)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-semibold text-slate-950">Buchung</h2>
                    <p class="mt-2 text-sm text-slate-500">Diese Spende ist mit einer Finanzbuchung verbunden.</p>
                    <a href="{{ route('transactions.index', ['search' => $donation->donor_name]) }}" class="mt-4 inline-flex w-full justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">Buchung suchen</a>
                </div>
            @endif

            @if($donation->status !== 'cancelled')
                <form method="POST" action="{{ route('donations.cancel', $donation) }}" class="rounded-2xl border border-rose-200 bg-rose-50 p-5">
                    @csrf
                    @method('PATCH')
                    <h2 class="text-base font-semibold text-rose-950">Stornieren</h2>
                    <p class="mt-2 text-sm text-rose-700">Die Spende bleibt nachvollziehbar, wird aber nicht mehr als aktive Zuwendung gewertet.</p>
                    <button type="submit" class="mt-4 inline-flex w-full justify-center rounded-xl bg-rose-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-rose-800">Spende stornieren</button>
                </form>
            @endif
        </aside>
    </div>
</div>
@endsection
