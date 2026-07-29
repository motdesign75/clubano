@extends('layouts.app')

@section('title', 'Termin bearbeiten')

@section('content')
@php
    $publicEventUrl = route('events.public.show', $event->id);
    $bookingUrl = $event->activeBookingForm ? route('forms.public.show', $event->activeBookingForm->slug) : null;
    $publicListUrl = route('events.public.index', $event->tenant->slug);
    $embedListUrl = route('events.public.embed', $event->tenant->slug);
    $selectedCategoryEmbed = $event->category ? $embedListUrl.'?category='.$event->category->slug : null;
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-2xl bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">Event-Editor</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $event->title }}</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    Bearbeite die wichtigsten Informationen zuerst. Veröffentlichung, Dienstplan und Anmeldungen folgen darunter.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('events.show', $event) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-white px-4 text-sm font-semibold text-slate-950 hover:bg-slate-100">
                    Ansehen
                </a>
                <a href="{{ route('events.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-white/20 px-4 text-sm font-semibold text-white hover:bg-white/10">
                    Zum Kalender
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

    <form action="{{ route('events.update', $event) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        @include('events.partials.form-fields', ['event' => $event])

        <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('events.show', $event) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Abbrechen
            </a>
            <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                <x-heroicon-o-check-circle class="h-5 w-5" />
                Änderungen speichern
            </button>
        </div>
    </form>

    <section class="rounded-xl border border-rose-200 bg-rose-50 p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-rose-950">Veranstaltung löschen</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-rose-800">
                    Entfernt den Termin aus Kalender, öffentlicher Veranstaltungsliste und zugehöriger Organisation. Bitte nur löschen, wenn diese Veranstaltung wirklich nicht mehr benötigt wird.
                </p>
            </div>
            <form method="POST" action="{{ route('events.destroy', $event) }}" onsubmit="return confirm('Veranstaltung wirklich löschen? Dieser Schritt kann nicht rückgängig gemacht werden.');" class="shrink-0">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-rose-300 bg-white px-5 text-sm font-semibold text-rose-700 hover:bg-rose-100 sm:w-auto">
                    Veranstaltung löschen
                </button>
            </form>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-950">Öffentliche Darstellung</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                    Alle Links, die du für Website, Vorschau oder Anmeldung brauchst.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ $publicEventUrl }}" target="_blank" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                    Vorschau
                </a>
                @if($bookingUrl)
                    <a href="{{ route('forms.submissions', $event->activeBookingForm) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Anmeldungen
                    </a>
                @endif
            </div>
        </div>

        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div>
                <label class="text-sm font-semibold text-slate-900">Öffentlicher Link</label>
                <input type="text" readonly value="{{ $publicEventUrl }}" class="mt-2 w-full rounded-lg border-slate-300 bg-slate-50 text-sm">
            </div>

            <div>
                <label class="text-sm font-semibold text-slate-900">Buchungslink</label>
                <input type="text" readonly value="{{ $bookingUrl ?? 'Wird aktiv, sobald Anmeldung eingeschaltet ist.' }}" class="mt-2 w-full rounded-lg border-slate-300 bg-slate-50 text-sm">
            </div>

            <div>
                <label class="text-sm font-semibold text-slate-900">Veranstaltungsliste</label>
                <input type="text" readonly value="{{ $publicListUrl }}" class="mt-2 w-full rounded-lg border-slate-300 bg-slate-50 text-sm">
            </div>

            <div>
                <label class="text-sm font-semibold text-slate-900">Embed-Liste</label>
                <input type="text" readonly value="{{ $selectedCategoryEmbed ?? $embedListUrl }}" class="mt-2 w-full rounded-lg border-slate-300 bg-slate-50 text-sm">
            </div>
        </div>

        <div class="mt-4">
            <label class="text-sm font-semibold text-slate-900">Iframe-Code</label>
            <textarea readonly rows="3" class="mt-2 w-full rounded-lg border-slate-300 bg-slate-50 text-sm">{{ '<iframe src="' . ($selectedCategoryEmbed ?? $embedListUrl) . '" width="100%" height="980" style="border:0;max-width:100%;" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>' }}</textarea>
        </div>
    </section>

    @include('events.partials.schedule', [
        'event' => $event,
        'eventShifts' => $eventShifts,
        'assignableMembers' => $assignableMembers,
        'scheduleStats' => $scheduleStats,
    ])

    @include('events.partials.participants', [
        'event' => $event,
        'eventBookings' => $eventBookings,
        'bookingSubmissionCount' => $bookingSubmissionCount,
        'participantCount' => $participantCount,
        'bookingRevenue' => $bookingRevenue,
        'canManageManualParticipants' => true,
    ])
</div>
@endsection

@push('scripts')
<script src="/tinymce/tinymce.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    tinymce.init({
        selector: '#description',
        license_key: 'gpl',
        height: 280,
        menubar: false,
        plugins: 'lists link table code fullscreen',
        toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link | code fullscreen',
        content_style: 'body { font-family: Inter, system-ui, sans-serif; font-size: 14px; line-height: 1.6; }'
    });

    document.querySelector('form[action="{{ route('events.update', $event) }}"]')?.addEventListener('submit', function () {
        tinymce.triggerSave();
    });
});
</script>
@endpush
