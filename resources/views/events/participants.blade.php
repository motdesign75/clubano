@extends('layouts.app')

@section('title', 'Teilnehmer: '.$event->title)

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-2xl bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">Teilnehmer</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $event->title }}</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    Erfasse, korrigiere und exportiere die Teilnehmerliste, ohne den Termin selbst zu überladen.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('events.edit', $event) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-white px-4 text-sm font-semibold text-slate-950 hover:bg-slate-100">
                    Termin bearbeiten
                </a>
                <a href="{{ route('events.show', $event) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-white/20 px-4 text-sm font-semibold text-white hover:bg-white/10">
                    Zur Veranstaltung
                </a>
            </div>
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            Bitte prüfe die markierten Felder.
        </div>
    @endif

    <section class="grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Teilnehmer</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $participantCount }}</div>
            <div class="mt-1 text-sm text-slate-500">insgesamt erfasst</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Buchungen</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $bookingSubmissionCount }}</div>
            <div class="mt-1 text-sm text-slate-500">Online oder manuell</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Umsatz</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format((float) $bookingRevenue, 2, ',', '.') }} {{ strtoupper($event->currency ?: 'EUR') }}</div>
            <div class="mt-1 text-sm text-slate-500">laut Teilnehmerliste</div>
        </div>
    </section>

    @include('events.partials.participants', [
        'event' => $event,
        'eventParticipants' => $eventParticipants,
        'bookingSubmissionCount' => $bookingSubmissionCount,
        'participantCount' => $participantCount,
        'bookingRevenue' => $bookingRevenue,
        'participantFilters' => $participantFilters,
        'canManageManualParticipants' => true,
        'embeddedInEditor' => true,
    ])
</div>
@endsection
